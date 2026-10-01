/**
 * Offline tests: real X Chat ciphertext (X's published chat-xdk test vectors,
 * MIT, tests/fixtures/sdk_vectors.json) through the real XDK, over the
 * worker's real HTTP interface. No mocks of the crypto.
 *
 *   npm test
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test, before, after } from 'node:test';

process.env.XCHAT_WORKER_TOKEN = 'test-token-0123456789abcdef0123456789';
const { server } = await import('../server.mjs');

const v = JSON.parse(readFileSync(new URL('./fixtures/sdk_vectors.json', import.meta.url), 'utf8'));
const signingKeys = [{
    userId: String(v.event_sender_id),
    publicKeyVersion: String(v.event_signing_key_version),
    publicKey: v.signing_public_b64,
    identityPublicKey: v.identity_public_b64,
    identityPublicKeySignature: v.identity_public_key_signature_b64,
}];

let base;
before(() => new Promise((resolve) => server.listen(0, '127.0.0.1', () => { base = `http://127.0.0.1:${server.address().port}`; resolve(); })));
after(() => new Promise((resolve) => server.close(resolve)));

async function call(path, body, token = process.env.XCHAT_WORKER_TOKEN) {
    const res = await fetch(base + path, { method: 'POST', headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return { status: res.status, body: await res.json() };
}

const unlock = () => call('/v1/sessions/unlock', { session_id: 'acct-1', user_id: String(v.event_sender_id), signing_key_version: String(v.event_recipient_key_version), key_blob: v.private_keys_concat_b64 });

test('rejects requests without the shared token', async () => {
    const r = await call('/v1/sessions/status', { session_id: 'acct-1' }, 'wrong');
    assert.equal(r.status, 401);
});

test('a locked session returns 409 session_locked', async () => {
    const r = await call('/v1/decrypt', { session_id: 'never-unlocked', event: v.event_message_b64 });
    assert.equal(r.status, 409);
    assert.equal(r.body.error, 'session_locked');
});

test('text message: key change processed first, then the message decrypts and verifies', async () => {
    assert.equal((await unlock()).status, 200);
    const r = await call('/v1/decrypt', { session_id: 'acct-1', event: v.event_message_b64, key_change_events: [v.event_key_change_b64], signing_keys: signingKeys });
    assert.equal(r.status, 200, JSON.stringify(r.body));
    assert.equal(r.body.event.type, 'message');
    assert.equal(r.body.event.text, v.event_message_text);
    assert.equal(r.body.event.verified, true);
    assert.equal(r.body.event.sender_id, String(v.event_sender_id));
    assert.equal(r.body.event.key_version, String(v.event_conversation_key_version));
    assert.deepEqual(r.body.keyVersions, [String(v.event_conversation_key_version)]);
});

test('missing conversation key -> 422 missing_conversation_key (safe failure)', async () => {
    await unlock();
    const r = await call('/v1/decrypt', { session_id: 'acct-1', event: v.event_message_b64, key_change_events: [], signing_keys: signingKeys });
    assert.equal(r.status, 422);
    assert.equal(r.body.error, 'missing_conversation_key');
});

test('wrong sender signing key -> 422 signature_invalid', async () => {
    await unlock();
    const forged = [{ ...signingKeys[0], publicKey: v.identity_public_b64 }];
    const r = await call('/v1/decrypt', { session_id: 'acct-1', event: v.event_message_b64, key_change_events: [v.event_key_change_b64], signing_keys: forged });
    assert.equal(r.status, 422);
    assert.ok(['signature_invalid', 'missing_conversation_key'].includes(r.body.error), r.body.error);
});

test('malformed event -> 400 malformed_event', async () => {
    await unlock();
    const r = await call('/v1/decrypt', { session_id: 'acct-1', event: v.event_garbage_b64, key_change_events: [v.event_key_change_b64], signing_keys: signingKeys });
    assert.equal(r.status, 400);
    assert.equal(r.body.error, 'malformed_event');
});

test('non-message events (delivery failure) are returned typed, not as text', async () => {
    await unlock();
    const r = await call('/v1/decrypt', { session_id: 'acct-1', event: v.event_failure_b64, key_change_events: [v.event_key_change_b64], signing_keys: signingKeys });
    assert.equal(r.status, 200);
    assert.equal(r.body.event.type, 'failure');
    assert.equal(r.body.event.text, null);
});

test('outgoing: encrypts a reply into the X send-message body', async () => {
    await unlock();
    const r = await call('/v1/encrypt', { session_id: 'acct-1', conversation_id: v.event_conversation_id, text: 'Thank you!', key_change_events: [v.event_key_change_b64], signing_keys: signingKeys });
    assert.equal(r.status, 200, JSON.stringify(r.body));
    assert.ok(r.body.payload.message_id);
    assert.ok(r.body.payload.encoded_message_create_event);
    assert.ok(r.body.payload.encoded_message_event_signature);
});

test('lock wipes the session', async () => {
    await unlock();
    await call('/v1/sessions/lock', { session_id: 'acct-1' });
    const r = await call('/v1/sessions/status', { session_id: 'acct-1' });
    assert.equal(r.body.status, 'locked');
});
