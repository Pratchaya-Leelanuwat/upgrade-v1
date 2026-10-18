# Webboard security assessment — results 3

**Assessment date:** 2026-10-18  
**Target:** Running service at `http://127.0.0.1:8080` plus the root project source and deployment configuration  
**Scope:** Read-only HTTP checks, one rejected CSRF POST, and source/configuration review. No account data or `.env` contents were read. No successful login, post creation, edit, delete, or upload was attempted.

## Summary

The application was running and returned responses on localhost, so this assessment includes live HTTP evidence unlike the two earlier reports. The tested anonymous routes enforced login redirects, invalid CSRF was rejected, a traversal-style image name was rejected, and the login response included the expected security headers and secure session-cookie flags.

The assessment is **not a full penetration test**. No test accounts were available, so ownership checks and the stale-admin-session issue were reviewed in source but not reproduced with authenticated requests. The Docker Compose CLI was unavailable in this environment, so the running container could not be tied to the current source revision or its effective environment values.

Two deployment risks remain visible in the repository: `.env` is tracked by Git, and `.env.example` configures the PHP app to connect as MySQL `root` with the fixed password `123456`. The root Compose file separately creates a restricted `webapp` MySQL account, but `src/includes/db.php` uses `DB_USER` from the app environment; the example therefore does not use that restricted account.

## Findings

### F-01 — Tracked `.env` may expose deployment secrets

**Severity: High if the tracked values are active; exposure is confirmed, validity is unknown.**

`git ls-files --error-unmatch .env` confirms that `.env` is tracked. Its contents were not opened or reproduced in this assessment. A tracked environment file may expose active database credentials and the application signing secret to anyone with repository or history access.

**Recommended action:** Treat any real values committed in `.env` or its history as exposed. Rotate the application secret and database passwords, remove `.env` from Git while retaining a local ignored copy, and review repository access/history.

### F-02 — Example app configuration uses MySQL root and a fixed weak password

**Severity: High if copied as supplied.**

The checked-in `.env.example` sets `DB_USER=root`, `DB_PASSWORD=123456`, and `DB_ROOT_PASSWORD=123456`, and contains a fixed application secret. `src/includes/db.php` connects using `DB_USER` and `DB_PASSWORD`. Although `docker-compose.yml` initializes a restricted `webapp` account and grants it only SELECT/INSERT/UPDATE/DELETE, the example app configuration does not select that account. Following the example without replacing these values gives the PHP app root database credentials.

**Recommended action:** Use `DB_USER=webapp`, generate unique strong database credentials and an application secret, and replace example secrets with unmistakable placeholders. Verify the effective app environment uses the restricted user. Do not reuse the example values.

### F-03 — Admin role is cached in the PHP session

**Severity: Medium; requires an existing admin session and subsequent role revocation.**

On login, `src/public/index.php` copies the database role into `$_SESSION['role']`. `user()` returns this session value, and `allowed()` in `src/includes/helpers.php` grants admin access based on it. Demoting an admin in the database therefore does not revoke permissions from an already active session. An old admin session may still edit or delete other users’ posts until that session ends.

**Recommended action:** Check the current role in the database during authorization, or otherwise invalidate all sessions when a role changes. Test demotion using a controlled admin account and the same existing session.

### F-04 — Cloudflare headers are trusted without validating the connecting proxy

**Severity: Medium when an untrusted client can reach the origin directly.**

When `APP_TRUST_CF_HEADERS=true`, `client_ip()` in `src/includes/security.php` accepts any valid `CF-Connecting-IP`. `src/includes/bootstrap.php` also uses `CF-Visitor` to infer HTTPS. The source does not verify that the TCP peer is a trusted Cloudflare proxy. The provided Compose file binds the web port to localhost and the optional tunnel uses an internal network, which reduces exposure in that deployment. Directly publishing the origin could let clients spoof the IP used by rate limits and influence proxy-derived HTTPS behavior.

**Recommended action:** Keep the origin reachable only through the trusted proxy, or validate the immediate peer against trusted proxy addresses before using forwarded headers.

## Live checks

| Check | Observed result | Assessment |
|---|---|---|
| `GET /` | HTTP 200 | Pass |
| `GET /login`, `GET /register` | HTTP 200 | Pass |
| Unauthenticated `GET /profile`, `/new`, `/edit?type=thread&id=1` | HTTP 303 to `/login` | Pass; authentication gate observed |
| `GET /logout` | HTTP 405 | Pass; logout rejects GET |
| `GET /image?name=..%2F..%2Fetc%2Fpasswd` | HTTP 404 | Pass; traversal-style image name rejected |
| `GET /thread?id=2%20OR%201%3D1` | HTTP 404, no SQL/PHP error text | Pass for this probe; not a general SQL-injection proof |
| `POST /login` without CSRF token | HTTP 400 | Pass; invalid request rejected before login processing |
| Login response session cookie | `Secure`, `HttpOnly`, `SameSite=Strict` present | Pass |
| Login response security headers | CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy present | Pass |

The observed response identified Apache but did not expose its version. These checks made no successful authentication or application data change.

## Bundled smoke test

Ran `powershell -ExecutionPolicy Bypass -File tests/security-smoke.ps1`. It passed the home page, stylesheet cache-busting, home search/sort controls, and search request status checks, then stopped because its search-body assertion expected mojibake text (`à¸...`) instead of the rendered search text. The remaining bundled assertions did not run. The test script was not modified. Focused checks above were run separately against the live service.

## Source review and unverified areas

- Prepared SQL statements are used for user-provided values in the reviewed routes; dynamic table names are selected from fixed `threads`/`answers` choices.
- The source escapes output through `e()` in the reviewed rendering paths. No browser-based XSS test was performed.
- `post()` requires a valid CSRF token; the invalid-token behavior was also observed live.
- `allowed()` checks post ownership, but its admin exception uses the cached session role (F-03). Cross-account edit/delete requests were not attempted.
- `save_image()` checks upload status, size, MIME type and dimensions, decodes/re-encodes accepted formats, and writes to a private upload directory. No upload was attempted.
- Password hashing, dummy-hash login verification, and persisted rate limits are present in source. No password guessing or rate-limit exhaustion was attempted.
- The runtime returned expected headers and routes, but Docker Compose was unavailable (`docker compose` was not a recognized command), so the running service’s exact source/image version, container network, and effective environment could not be independently verified.

## Overall assessment

The running service passed the anonymous HTTP checks performed here. Address F-01 and F-02 before sharing or deploying the application, and resolve F-03 and F-04 before relying on admin revocation or proxy-based IP controls. Repeat authenticated authorization checks in an isolated environment with separate member and admin accounts after confirming which source revision is running.
