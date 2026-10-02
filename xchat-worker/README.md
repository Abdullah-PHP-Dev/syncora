# Socialeaz X Chat worker

Decrypts incoming **X Chat** (end-to-end encrypted DM) webhook events and
encrypts replies, using **X's official Chat XDK** (`@xdevplatform/chat-xdk`).
X ships the Chat XDK for Python/JS/Rust/Go/.NET/JVM - not PHP - so Laravel
talks to this small localhost service instead of doing any cryptography
itself (`App\Services\MessagingServices\XChat\XChatWorkerClient`).

## Run

```bash
cd xchat-worker
npm ci --omit=dev          # Node 18+
XCHAT_WORKER_TOKEN=<same value as Laravel .env> node server.mjs
```

| Env | Default | |
|---|---|---|
| `XCHAT_WORKER_TOKEN` | read from `../.env` | Shared secret (32+ chars). If not set in the environment, the worker reads `XCHAT_WORKER_TOKEN` from the Laravel `.env` one folder up, so it only needs to be set there |
| `XCHAT_WORKER_HOST` | `127.0.0.1` | Never bind a public interface |
| `XCHAT_WORKER_PORT` | `8790` | Laravel: `XCHAT_WORKER_URL=http://127.0.0.1:8790` |
| `XCHAT_SESSION_IDLE_MINUTES` | `720` | Unlocked keys are wiped from memory after this idle time |

Generate a token: `openssl rand -hex 32`.

### Production on Laravel Forge

Forge -> site -> **Background processes** -> **+**:

| Field | Value |
|---|---|
| Command | `node server.mjs` |
| Directory | `/home/forge/<site>/xchat-worker` |
| User | `forge` |
| Processes | `1` |

No environment needed: the token is read from the site's `.env`. Restart the
process after a deploy that changes `xchat-worker/`.

### Production (plain Supervisor)

```ini
[program:xchat-worker]
directory=/var/www/socialeaz/xchat-worker
command=node server.mjs
environment=XCHAT_WORKER_TOKEN="...",XCHAT_WORKER_PORT="8790"
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/supervisor/xchat-worker.log
redirect_stderr=true
```

A restart only drops the in-memory unlocked keys: Laravel re-unlocks the
account from its stored (encrypted) X Chat PIN on the next event.

## Test

```bash
npm test
```

Runs **X's published Chat XDK test vectors** (real ciphertext, MIT, X Corp -
`test/fixtures/sdk_vectors.json`) through the real XDK over the worker's HTTP
API: key change -> decrypt + verify, missing key, bad signature, malformed
event, non-message events, encrypting a reply, auth and session locking.

## Security notes

- Keys are recovered from X's secure key backup (Juicebox) with the account
  owner's X Chat PIN and held **only in this process's memory**; nothing
  secret is written to disk.
- Logs are structured and contain ids, codes and event types only - never
  PINs, keys, tokens or message text.
- Signature verification is the XDK default (`reject_unverified`) and is never
  disabled.
