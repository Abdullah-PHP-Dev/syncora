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
import { createChat } from '@xdevplatform/chat-xdk';

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
    encrypt({ conversationId, text, keyChangeEvents = [], signingKeys = [], replyToEvent = null }) {
        if (!conversationId || typeof text !== 'string' || text === '') {
            throw new XChatError('malformed_event', 'conversationId and text are required.');
        }
        const batch = this.#chat.decryptEvents(keyChangeEvents, signingKeys);
        const version = batch.conversationKeys?.latestVersion;
        const key = version ? batch.conversationKeys.keys[version] : null;
        if (!key) throw new XChatError('missing_conversation_key', 'No verified conversation key is available for this conversation.');

        const params = { conversationId, text, conversationKey: key, conversationKeyVersion: version };
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

    lock() {
        try { this.#chat.lock(); } catch { /* already locked */ }
    }
}

/** Reduce the XDK Event to the fields Laravel stores. Plain text only - no keys. */
export function normalize(ev) {
    const content = ev?.content ?? {};
    const attachments = Array.isArray(ev?.attachments) ? ev.attachments : [];
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
        attachments: attachments.map((a) => ({
            type: a?.type ?? a?.kind ?? 'file',
            mime_type: a?.mimeType ?? a?.mime_type ?? null,
            file_name: a?.filename ?? a?.fileName ?? null,
            url: a?.url ?? null,
        })),
        reply_preview_validation: ev?.replyPreviewValidation ?? null,
        failure: ev?.failure ?? null,
    };
}
