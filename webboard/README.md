# Webboard (PHP 8 + Apache + MySQL)

## Start
1. Copy `.env.example` to `.env`. Set unique DB passwords and a random `APP_SECRET` of at least 32 characters. Keep `.env` private.
2. Run `docker compose up -d --build`.
3. Create an admin: `docker compose exec web php bin/create_admin.php admin 'a-unique-password-of-at-least-16-chars'`.
4. Open http://localhost. Use HTTPS before deployment beyond an isolated classroom network. If TLS terminates at a trusted reverse proxy, set `APP_HTTPS=true` in `.env` so session cookies use the Secure flag (ensure the proxy blocks direct HTTP access).

MySQL init runs only for a new volume. Removing it with `docker compose down -v` deletes the database.

## File protections
- Compose/Dockerfile: DB is not published, isolated backend network, read-only app, no-new-privileges and non-root Apache workers.
- `db/schema.sql`, `db/02-app-user.sql`: foreign keys and only SELECT/INSERT/UPDATE/DELETE grants for the app account.
- `src/includes/db.php`: PDO native prepared statements.
- `src/includes/helpers.php`: output escaping, CSRF checks and ownership/admin checks on server.
- `src/includes/upload.php`: MIME/size/dimensions checks, image decode/re-encode, random names outside web root.
- `src/public/index.php`: password hashing, login throttling, session ID rotation, POST-only mutations and validation.
- Apache/PHP configs: hide version/errors, disable directory listing, security headers/CSP.

## Authorized self-test plan
Use only your own local deployment and a throwaway database.
1. Try SQL injection strings in login; authentication must fail.
2. Store `<script>alert(1)</script>` in content; it must render as text.
3. Omit or alter CSRF token on POST; expect HTTP 400.
4. Attempt another user's edit/delete ID via Burp; expect 403 and no change.
5. Try PHP, SVG, malformed, oversized and polyglot uploads; only small valid JPEG/PNG/GIF should pass and files must stay outside public.
6. After ten failed logins in 15 minutes, expect HTTP 429.
7. Check DB has no published port and app DB user cannot create/drop tables.
8. Inspect CSP, nosniff and generic error responses.

Known limitations to disclose: port 80 is plaintext; Secure cookies require HTTPS. No registration/post rate limiting or 2FA yet. Animated GIFs may be flattened. Admin creation is a one-time manual task. Add TLS, backups and monitoring for deployment.
