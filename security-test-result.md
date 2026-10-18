
# Webboard security test results

**Assessment date:** 2026-10-18  
**Scope:** Repository review of the root deployment and the second copy in `webboard/`. No production or external target was contacted. The checked-in `.env` file was confirmed by its Git path only; its contents were not opened or reproduced.

## Summary

The root deployment has useful protections in its current source: prepared SQL statements, HTML escaping, CSRF tokens, ownership checks, Argon2id passwords, image re-encoding, and container hardening. This review found two actionable configuration/repository risks and one conditional proxy-trust risk. The `webboard/` directory contains an older, materially less hardened copy whose Docker configuration publishes HTTP on all interfaces.

Runtime behavior remains **unverified**: Docker Desktop was unavailable, so the application smoke test could not reach a running app. Source inspection is not proof that deployed settings or behavior match the files reviewed.

## Findings

### F-01 — `.env` is tracked in Git

**Severity: High if it contains active credentials; confirmed repository exposure, credential validity unknown.**

`git ls-files .env webboard/.env` returned `.env`, confirming that the root environment file is tracked. Environment files commonly contain the application signing secret and database credentials. I intentionally did not read or reproduce its values. Anyone with access to repository history may also have copies even if the file is later removed.

**Action:** Treat values in the tracked file as exposed: rotate the application secret and both database passwords, remove `.env` from version control while retaining a local ignored copy, and audit repository history and access. Keep `.env.example` limited to unmistakable placeholders.

### F-02 — The root example environment contains weak, fixed database credentials

**Severity: High if copied without replacement.**

The root `.env.example` contains literal database passwords (`123456`) and a fixed application secret. The README instructs operators to replace them, but the example is unsafe if that step is missed. In contrast, the nested example uses placeholders.

**Action:** Replace all example values with unmistakable placeholders and fail closed unless strong unique values are supplied. Confirm the application connects using the restricted `webapp` database account rather than root.

### F-03 — Older nested deployment is broadly exposed over plain HTTP

**Severity: High when the nested copy is deployed on an untrusted network.**

`webboard/docker-compose.yml` maps `80:80` (all host interfaces) and does not configure TLS or a HTTPS proxy. Its `.env.example` sets `APP_HTTPS=false`. The root deployment instead binds to `127.0.0.1:8080` and has a separate optional Cloudflare tunnel. The nested Dockerfile also uses floating tags (`php:8.3-apache`, `mysql:8.4`) rather than the root copy's version-pinned tags. The nested copy is therefore easy to expose with plaintext credentials and does not receive predictable image updates.

**Action:** Remove or clearly retire the duplicate deployment, or bring its network exposure, HTTPS, proxy, and image-version controls in line with the root deployment before using it.

### F-04 — Cloudflare client-IP and HTTPS headers are trusted based on configuration alone

**Severity: Medium; conditional on exposing the web origin outside the intended tunnel path.**

When `APP_TRUST_CF_HEADERS=true`, `src/includes/security.php` accepts any syntactically valid `CF-Connecting-IP`, and `src/includes/bootstrap.php` uses `CF-Visitor` to enable HTTPS cookie/HSTS behavior. Neither checks that the TCP peer is a trusted Cloudflare proxy. The root Compose file currently binds the web port to localhost and routes its optional tunnel through the internal frontend network, which limits exposure in the provided setup. If the origin is published directly or another untrusted peer can reach it, clients may spoof those headers, bypass IP-based throttles, and affect proxy-derived HTTPS handling.

**Action:** Only trust these headers when the immediate peer is an explicitly trusted proxy; otherwise derive the client address and scheme from the connection. Keep the origin unreachable directly when using the tunnel.

## Checks attempted

| Check | Result | Evidence and limits |
|---|---|---|
| SQL injection review | **No issue found in reviewed root routes** | User-controlled query values use PDO prepared statements; the only interpolated SQL table names are selected from fixed allowlists. No live injection request was made. |
| Reflected/stored XSS review | **No issue found in reviewed root rendering paths** | User-supplied names, titles, bodies, and search terms are HTML-escaped with `htmlspecialchars`; no browser execution test was possible. |
| CSRF review | **Controls present; runtime unverified** | Mutating routes call `post()`, which requires a session token and constant-time compares it. The documented invalid-token POST was not run because the app was unavailable. |
| Broken access control / IDOR review | **Checks present; runtime unverified** | Edit/delete call `allowed()` against the owner ID or admin role; answer/thread tables are allowlisted. No multi-account authorization test was possible. |
| Upload validation and traversal review | **Controls present; runtime unverified** | Root upload handler checks upload status/size, detected MIME and image dimensions, decodes/re-encodes allowed formats to random names outside the public root; image route restricts names to a fixed pattern. No multipart upload was sent. |
| Authentication and brute-force controls review | **Controls present; configuration risk found** | Root source uses Argon2id, dummy-hash verification for unknown users, and persisted IP/account failure limits. The tracked `.env` and unsafe example credentials are reported above. No login attempts were sent. |
| Security headers / session review | **Configured in source; runtime unverified** | Root source configures a restrictive CSP, HttpOnly/SameSite cookies, conditional Secure/HSTS, and Apache hardening headers. No response headers were captured. |
| Container/network configuration review | **Finding** | Root Compose limits the published web port to loopback and isolates MySQL; the nested copy binds HTTP to all interfaces. See F-03. |
| Secret handling review | **Finding** | Git tracks `.env`; values were deliberately not read. Root `.env.example` also has fixed weak credentials. See F-01 and F-02. |
| Live smoke test availability | **Blocked / not run** | `docker compose ps` failed because the Docker Desktop Linux engine pipe was unavailable. Consequently, `tests/security-smoke.ps1` was not run and none of its HTTP checks should be treated as passing. |

## Assessment limits

This was a source/configuration review plus an environment availability check, not a penetration test or deployment audit. No authenticated testing, destructive actions, load testing, dependency/image CVE scan, or external scanning was performed. Re-run the repository smoke script against a rebuilt local instance, then test the deployed configuration in staging, especially both credentials and proxy/origin reachability.
