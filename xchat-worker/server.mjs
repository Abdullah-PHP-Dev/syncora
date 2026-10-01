/**
 * Socialeaz X Chat worker - localhost-only HTTP service the Laravel app calls
 * (App\Services\MessagingServices\XChat\XChatWorkerClient). It exists because
 * X ships the Chat XDK for Python/JS/Rust/Go/.NET/JVM but not PHP; all crypto
 * stays inside X's official library.
 *
 * Env:
 *   XCHAT_WORKER_TOKEN   required - shared secret (same value in Laravel .env)
 *   XCHAT_WORKER_HOST    default 127.0.0.1 (never expose publicly)
 *   XCHAT_WORKER_PORT    default 8790
 *   XCHAT_SESSION_IDLE_MINUTES  default 720 - unlocked sessions are locked
 *                               (keys wiped from memory) after this idle time
 *
 * Endpoints (JSON, POST, Authorization: Bearer <token>):
 *   /health                     GET, no auth - liveness only
 *   /v1/sessions/unlock         { session_id, user_id, signing_key_version, juicebox_config, pin } | { ..., key_blob }
 *   /v1/sessions/status         { session_id }
 *   /v1/sessions/lock           { session_id }
 *   /v1/decrypt                 { session_id, event, key_change_events[], signing_keys[] }
 *   /v1/encrypt                 { session_id, conversation_id, text, key_change_events[], signing_keys[], reply_to_event? }
 *
 * A request for a session that isn't unlocked returns 409 session_locked;
 * Laravel then calls /v1/sessions/unlock and retries.
 */
import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { XChatSession, XChatError, classifyError } from './src/xchat.mjs';

const TOKEN = process.env.XCHAT_WORKER_TOKEN ?? '';
const HOST = process.env.XCHAT_WORKER_HOST ?? '127.0.0.1';
const PORT = Number(process.env.XCHAT_WORKER_PORT ?? 8790);
const IDLE_MS = Number(process.env.XCHAT_SESSION_IDLE_MINUTES ?? 720) * 60_000;
const MAX_BODY = 2 * 1024 * 1024;

if (TOKEN.length < 32) {
    console.error('[xchat-worker] XCHAT_WORKER_TOKEN must be set (32+ characters). Refusing to start.');
    process.exit(1);
}

/** session_id (Laravel social_account id) -> { session, lastUsed } */
const sessions = new Map();

function log(event, fields = {}) {
    // Structured, secret-free log line: ids, codes and counts only.
    console.log(JSON.stringify({ ts: new Date().toISOString(), event, ...fields }));
}

function authorized(req) {
    const header = req.headers.authorization ?? '';
    const given = Buffer.from(header.startsWith('Bearer ') ? header.slice(7) : '');
    const expected = Buffer.from(TOKEN);
    return given.length === expected.length && timingSafeEqual(given, expected);
}

function send(res, status, body) {
    res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    res.end(JSON.stringify(body));
}

function readJson(req) {
    return new Promise((resolve, reject) => {
        let size = 0;
        const chunks = [];
        req.on('data', (c) => {
            size += c.length;
            if (size > MAX_BODY) { reject(new XChatError('malformed_event', 'Request body too large.')); req.destroy(); return; }
            chunks.push(c);
        });
        req.on('end', () => {
            try { resolve(chunks.length ? JSON.parse(Buffer.concat(chunks).toString('utf8')) : {}); }
            catch { reject(new XChatError('malformed_event', 'Invalid JSON body.')); }
        });
        req.on('error', reject);
    });
}

function sessionFor(id) {
    const entry = sessions.get(String(id));
    if (!entry) throw new XChatError('session_locked', 'X Chat session is locked - unlock required.');
    entry.lastUsed = Date.now();
    return entry.session;
}

const routes = {
    async '/v1/sessions/unlock'(b) {
        const id = String(b.session_id ?? '');
        if (!id || !b.user_id || !b.signing_key_version) throw new XChatError('malformed_event', 'session_id, user_id and signing_key_version are required.');
        const session = b.key_blob
            ? await XChatSession.fromKeyBlob({ userId: b.user_id, signingKeyVersion: b.signing_key_version, keyBlob: b.key_blob })
            : await XChatSession.unlockWithPin({ userId: b.user_id, signingKeyVersion: b.signing_key_version, juiceboxConfig: b.juicebox_config, pin: b.pin });
        sessions.get(id)?.session.lock();
        sessions.set(id, { session, lastUsed: Date.now() });
        log('session_unlocked', { session_id: id, method: b.key_blob ? 'key_blob' : 'pin' });
        return { status: 'unlocked' };
    },
    async '/v1/sessions/status'(b) {
        return { status: sessions.has(String(b.session_id ?? '')) ? 'unlocked' : 'locked' };
    },
    async '/v1/sessions/lock'(b) {
        const id = String(b.session_id ?? '');
        sessions.get(id)?.session.lock();
        sessions.delete(id);
        log('session_locked', { session_id: id, reason: 'requested' });
        return { status: 'locked' };
    },
    async '/v1/decrypt'(b) {
        const result = sessionFor(b.session_id).decrypt({
            event: b.event,
            keyChangeEvents: Array.isArray(b.key_change_events) ? b.key_change_events : [],
            signingKeys: Array.isArray(b.signing_keys) ? b.signing_keys : [],
        });
        log('decrypted', { session_id: String(b.session_id), type: result.event.type, content_type: result.event.content_type, verified: result.event.verified });
        return { status: 'ok', ...result };
    },
    async '/v1/encrypt'(b) {
        const payload = sessionFor(b.session_id).encrypt({
            conversationId: b.conversation_id,
            text: b.text,
            keyChangeEvents: Array.isArray(b.key_change_events) ? b.key_change_events : [],
            signingKeys: Array.isArray(b.signing_keys) ? b.signing_keys : [],
            replyToEvent: b.reply_to_event ?? null,
        });
        log('encrypted', { session_id: String(b.session_id) });
        return { status: 'ok', payload };
    },
};

const STATUS_BY_CODE = { session_locked: 409, malformed_event: 400, unlock_failed: 422, missing_conversation_key: 422, signature_invalid: 422, decrypt_failed: 422 };

export const server = createServer(async (req, res) => {
    if (req.method === 'GET' && req.url === '/health') return send(res, 200, { status: 'ok', sessions: sessions.size });
    if (!authorized(req)) return send(res, 401, { error: 'unauthorized' });
    const handler = req.method === 'POST' ? routes[req.url] : null;
    if (!handler) return send(res, 404, { error: 'not_found' });
    try {
        return send(res, 200, await handler(await readJson(req)));
    } catch (err) {
        const e = classifyError(err);
        log('error', { route: req.url, code: e.code });
        return send(res, STATUS_BY_CODE[e.code] ?? 500, { error: e.code, message: e.message });
    }
});

// Wipe keys from memory for idle sessions.
setInterval(() => {
    const cutoff = Date.now() - IDLE_MS;
    for (const [id, entry] of sessions) {
        if (entry.lastUsed < cutoff) {
            entry.session.lock();
            sessions.delete(id);
            log('session_locked', { session_id: id, reason: 'idle' });
        }
    }
}, 60_000).unref();

if (process.argv[1] && import.meta.url.endsWith(process.argv[1].split('/').pop())) {
    server.listen(PORT, HOST, () => log('listening', { host: HOST, port: PORT }));
}
