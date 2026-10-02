/**
 * X Chat crypto core - a thin wrapper around X's official Chat XDK
 * (@xdevplatform/chat-xdk). Every cryptographic operation (key recovery,
 * conversation-key unwrapping, signature verification, decryption,
 * encryption) is performed by the XDK; this file only wires inputs/outputs
 * and never touches key material directly.
 *
 * Two documented ways to load an account's private keys
 * (https://docs.x.com/xchat/getting-started, step 2):
 *   - PIN + secure key backup (Juicebox): createChat({ juiceboxConfig,
 *     getAuthToken }) then unlock(pin). This is the path for an existing X
 *     account whose keys were created by X's own apps - the case for a
 *     seller's business account. Mirrors X's reference bot
 *     (chat-xdk/examples/python/run.py, CHAT_PIN).
 *   - Exported key blob (import_keys): X's "bot / automation on your
 *     infrastructure" path, and what the offline tests use with X's
 *     published test vectors.
 *
 * Unlocked sessions live only in this process's memory. Nothing secret is
 * written to disk or logged.
 */
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createChat, base64ToBytes, bytesToBase64, detectMimeType, detectImageDimensions } from '@xdevplatform/chat-xdk';

const require = createRequire(import.meta.url);

let coreModule;

/**
 * The package's public entry exposes the PIN-based ChatWithJuicebox only; the
 * key-blob `Chat` class (import_keys) is the same bundled WASM engine, loaded
 * from the package's own pkg/ directory exactly as the package itself does.
 */
async function blobChatClass() {
    if (!coreModule) {
        const pkgDir = join(dirname(require.resolve('@xdevplatform/chat-xdk')), 'pkg');
        const mod = await import(pathToFileURL(join(pkgDir, 'chat_xdk_wasm.js')).href);
        if (typeof globalThis.crypto === 'undefined') {
            globalThis.crypto = (await import('node:crypto')).webcrypto;
        }
        await mod.default({ module_or_path: readFileSync(join(pkgDir, 'chat_xdk_wasm_bg.wasm')) });
        coreModule = mod;
    }
    return coreModule;
}

/** Realm auth tokens come from juicebox_config.token_map (X API public-keys response). */
function realmTokenGetter(juiceboxConfig) {
    const parsed = typeof juiceboxConfig === 'string' ? JSON.parse(juiceboxConfig) : juiceboxConfig;
    const tokens = new Map();
    for (const entry of parsed?.token_map ?? parsed?.tokenMap ?? []) {
        const realm = String(entry?.key ?? '').toLowerCase();
        const token = entry?.value?.token;
        if (realm && typeof token === 'string') tokens.set(realm, token);
    }
    return async (realmId) => tokens.get(String(realmId).toLowerCase()) ?? '';
}

export class XChatError extends Error {
    constructor(code, message) {
        super(message);
        this.code = code;
    }
}

/** Map XDK error text to a stable code Laravel can act on (retry, refresh keys, ...). */
export function classifyError(err) {
    const msg = String(err?.message ?? err);
    if (err instanceof XChatError) return err;
    if (/no matching key found|conversation key/i.test(msg)) return new XChatError('missing_conversation_key', msg);
    if (/signature/i.test(msg)) return new XChatError('signature_invalid', msg);
    if (/juicebox|recovery failed|invalid pin|guess/i.test(msg)) return new XChatError('unlock_failed', msg);
    if (/base64|serialization|deserializ|thrift/i.test(msg)) return new XChatError('malformed_event', msg);
    return new XChatError('decrypt_failed', msg);
}

export class XChatSession {
    #chat;

    constructor(chat, userId, signingKeyVersion) {
        this.#chat = chat;
        this.userId = String(userId);
        this.signingKeyVersion = String(signingKeyVersion);
        // Identity is required for encrypt/prepare (sender id + signing key version).
        this.#chat.setIdentity(this.userId, this.signingKeyVersion);
    }

    /** Production path: recover keys from X's secure key backup with the account's X Chat PIN. */
    static async unlockWithPin({ userId, signingKeyVersion, juiceboxConfig, pin }) {
        if (!juiceboxConfig) throw new XChatError('unlock_failed', 'juicebox_config is missing for this account.');
        if (!pin) throw new XChatError('unlock_failed', 'X Chat PIN is missing.');
        const configJson = typeof juiceboxConfig === 'string' ? juiceboxConfig : JSON.stringify(juiceboxConfig);
        try {
            const chat = await createChat({ juiceboxConfig: configJson, getAuthToken: realmTokenGetter(configJson) });
            await chat.unlock(pin);
            return new XChatSession(chat, userId, signingKeyVersion);
        } catch (err) {
            throw new XChatError('unlock_failed', String(err?.message ?? err));
        }
    }

    /** Server/bot path: an export_keys blob (base64) from a secret store. */
    static async fromKeyBlob({ userId, signingKeyVersion, keyBlob }) {
        const { Chat, base64ToBytes } = await blobChatClass();
        const bytes = base64ToBytes(keyBlob);
        if (!bytes) throw new XChatError('unlock_failed', 'Key blob is not valid base64.');
        const chat = new Chat();
        try {
            chat.importKeys(bytes, String(signingKeyVersion));
        } finally {
            bytes.fill(0);
        }
        return new XChatSession(chat, userId, signingKeyVersion);
    }

    /**
     * Decrypt one live event. Key-change events are processed FIRST through
     * decryptEvents (which verifies each change's signature before trusting
     * its key), and the resulting verified key map is passed explicitly to
     * decryptEvent - stateless per call, as X recommends for multi-instance
     * servers.
     */
    decrypt({ event, keyChangeEvents = [], signingKeys = [] }) {
        if (typeof event !== 'string' || event === '') throw new XChatError('malformed_event', 'encoded_event is missing.');

        let conversationKeys = {};
        let keyChangeErrors = {};
        if (keyChangeEvents.length) {
            const batch = this.#chat.decryptEvents(keyChangeEvents, signingKeys);
            conversationKeys = batch.conversationKeys?.keys ?? {};
            keyChangeErrors = batch.errors ?? {};
        }

        let decrypted;
        try {
            decrypted = this.#chat.decryptEvent(event, conversationKeys, signingKeys);
        } catch (err) {
            throw classifyError(err);
        }

        return {
            event: normalize(decrypted),
            keyVersions: Object.keys(conversationKeys),
            keyChangeErrors: Object.keys(keyChangeErrors).length,
        };
    }

    /** Encrypt + sign an outgoing text message (optionally a threaded reply). */
    encrypt({ conversationId, text, keyChangeEvents = [], signingKeys = [], replyToEvent = null, attachments = null }) {
        const hasAttachments = Array.isArray(attachments) && attachments.length > 0;
        if (!conversationId || typeof text !== 'string' || (text === '' && !hasAttachments)) {
            throw new XChatError('malformed_event', 'conversationId and text (or an attachment) are required.');
        }
        const { key, version } = this.#latestKey(keyChangeEvents, signingKeys);

        const params = { conversationId, text, conversationKey: key, conversationKeyVersion: version };
        // Media attachments (encryptMedia + X Chat media upload) are encrypted
        // under the same latest key, so message and media versions match.
        if (hasAttachments) params.attachments = attachments;
        const payload = replyToEvent
            ? this.#chat.encryptReply({ ...params, replyToEvent })
            : this.#chat.encryptMessage(params);

        return {
            message_id: payload.messageId,
            encoded_message_create_event: payload.encryptedContent,
            encoded_message_event_signature: payload.encodedEventSignature,
            conversation_key_version: String(payload.conversationKeyVersion ?? version),
        };
    }

    /** Verified conversation keys from key-change events (signatures checked by the XDK). */
    #keys(keyChangeEvents, signingKeys) {
        const batch = this.#chat.decryptEvents(keyChangeEvents, signingKeys);
        return batch.conversationKeys ?? { keys: {}, latestVersion: null };
    }

    #latestKey(keyChangeEvents, signingKeys) {
        const { keys, latestVersion } = this.#keys(keyChangeEvents, signingKeys);
        const key = latestVersion ? keys[latestVersion] : null;
        if (!key) throw new XChatError('missing_conversation_key', 'No verified conversation key is available for this conversation.');
        return { key, version: latestVersion };
    }

    /**
     * Decrypt an attachment downloaded from GET /2/chat/media/{id}/{hash}.
     * Uses the key of the MESSAGE's key version (not the latest) - X's media
     * guide: "Pick the key by the event's key version".
     */
    decryptMedia({ ciphertextB64, keyVersion, keyChangeEvents = [], signingKeys = [] }) {
        const { keys } = this.#keys(keyChangeEvents, signingKeys);
        const key = keyVersion ? keys[String(keyVersion)] : null;
        if (!key) throw new XChatError('missing_conversation_key', `No verified conversation key for version ${keyVersion}.`);
        const ciphertext = base64ToBytes(ciphertextB64);
        if (!ciphertext) throw new XChatError('malformed_event', 'Media ciphertext is not valid base64.');

        let plaintext;
        try {
            plaintext = this.#chat.decryptStream(ciphertext, key);
        } catch (err) {
            throw new XChatError('decrypt_failed', `Media decryption failed: ${err?.message ?? err}`);
        }
        const dims = detectImageDimensions(plaintext);
        return {
            plaintext_b64: bytesToBase64(plaintext),
            mime_type: detectMimeType(plaintext) ?? 'application/octet-stream',
            width: dims?.width ?? null,
            height: dims?.height ?? null,
            size: plaintext.byteLength,
        };
    }

    /** Encrypt file bytes for upload to the X Chat media store (latest key). */
    encryptMedia({ plaintextB64, keyChangeEvents = [], signingKeys = [] }) {
        const { key, version } = this.#latestKey(keyChangeEvents, signingKeys);
        const plaintext = base64ToBytes(plaintextB64);
        if (!plaintext) throw new XChatError('malformed_event', 'File bytes are not valid base64.');
        const dims = detectImageDimensions(plaintext);
        const ciphertext = this.#chat.encryptStream(plaintext, key);
        return {
            ciphertext_b64: bytesToBase64(ciphertext),
            key_version: String(version),
            mime_type: detectMimeType(plaintext) ?? 'application/octet-stream',
            width: dims?.width ?? 0,
            height: dims?.height ?? 0,
            plaintext_size: plaintext.byteLength,
            ciphertext_size: ciphertext.byteLength,
        };
    }

    lock() {
        try { this.#chat.lock(); } catch { /* already locked */ }
    }
}

/** Reduce the XDK Event to the fields Laravel stores. Plain text only - no keys. */
export function normalize(ev) {
    const content = ev?.content ?? {};
    const attachments = Array.isArray(ev?.attachments) && ev.attachments.length
        ? ev.attachments
        : (Array.isArray(content.attachments) ? content.attachments : []);
    // Media hash keys the XDK derived from the attachments - used when an
    // attachment entry itself doesn't carry one.
    const mediaHashes = (Array.isArray(ev?.mediaHashes) ? ev.mediaHashes : []).map((m) => m?.mediaHashKey).filter(Boolean);
    return {
        type: ev?.type ?? 'unknown',                       // message | keyChange | failure | readReceipt | ...
        message_id: ev?.id ?? null,
        sequence_id: ev?.sequenceId ?? null,
        sender_id: ev?.senderId ?? null,
        conversation_id: ev?.conversationId ?? null,
        created_at_msec: ev?.createdAtMsec ?? null,
        key_version: ev?.keyVersion ?? null,
        verified: ev?.verified === true,
        content_type: content.contentType ?? (typeof content.text === 'string' ? 'text' : null),
        text: typeof content.text === 'string' ? content.text : (typeof content.newText === 'string' ? content.newText : null),
        emoji: content.emoji ?? null,
        target_message_id: content.targetMessageId ?? null,
        attachment_count: attachments.length,
        attachments: attachments.map((a, i) => ({
            attachment_type: a?.attachmentType ?? a?.attachment_type ?? (a?.mediaHashKey || mediaHashes[i] ? 'media' : null), // media | url | post | unifiedCard | money
            media_hash_key: a?.mediaHashKey ?? a?.media_hash_key ?? mediaHashes[i] ?? null,
            media_type: a?.mediaType ?? null,
            file_name: a?.filename ?? null,
            filesize_bytes: a?.filesizeBytes ?? null,
            width: a?.dimensions?.width ?? null,
            height: a?.dimensions?.height ?? null,
            duration_millis: a?.durationMillis ?? null,
            url: a?.url ?? a?.postUrl ?? null,
            title: a?.displayTitle ?? null,
        })),
        reply_preview_validation: ev?.replyPreviewValidation ?? null,
        failure: ev?.failure ?? null,
    };
}
