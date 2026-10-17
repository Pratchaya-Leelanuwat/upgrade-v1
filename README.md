# Webboard (PHP 8 + Apache + MySQL)

## Fresh install
1. Copy `.env.example` to `.env`. Set unique DB passwords and a random `APP_SECRET` of at least 32 characters. Keep `.env` private.
2. Run `docker compose up -d --build`.
3. Create an admin with a hidden password prompt: `docker compose exec web php bin/create_admin.php admin@example.com "Admin Name"`.
4. Open `http://localhost:8080`.

Members register with full name, email, profile photo (optional), and a password of at least 12 characters. Email is their login and cannot be changed. At `/profile`, members can change their name, photo, and password. Forum posts and replies show the member name, profile photo, and posting time; authors can edit or delete their own content.

## Upgrade an existing install without deleting data
Do not run `docker compose down -v`: that removes the database and uploaded photos. Back up the database first. Apply each migration only when its schema change is not already present.

1. From the project folder, run `docker compose up -d db`.
2. Run `docker compose exec db sh` to open the database container shell.
3. Check the profile migration: `mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -Nse "SHOW COLUMNS FROM webboard.users LIKE 'email';"`. If no row appears, run `mysql -uroot -p"$MYSQL_ROOT_PASSWORD" webboard < /migrations/migrate-profile.sql`.
4. Apply the security migration (safe to re-run): `mysql -uroot -p"$MYSQL_ROOT_PASSWORD" webboard < /migrations/migrate-security.sql`.
5. Run `exit` to leave the container shell.
6. Rebuild with `docker compose up -d --build --force-recreate web`.
7. Check `docker compose ps`; both `webboard-db-1` and `webboard-web-1` should be `Up`.

The application stores only failed login attempts, expires old records, and applies signup/post limits by source IP. When traffic comes through the included Cloudflare tunnel, the app uses Cloudflare's `CF-Connecting-IP` and HTTPS visitor headers; the web port remains bound to localhost. Do not expose the origin port directly if you enable trusted Cloudflare headers.

## Security and uploads
- MySQL is not published and uses a private backend network. App DB user is limited to SELECT/INSERT/UPDATE/DELETE.
- Passwords use Argon2id. Mutations require CSRF tokens; ownership is checked server-side.
- Images accept only JPEG/PNG/GIF, are decoded and re-encoded, stored outside the public root under random image-only filenames, and old files are removed only after database changes succeed. The image route only returns the fixed image MIME type; uploaded content is never placed where Apache can execute it.
- Unknown-account logins verify against a random Argon2id dummy hash baked into each image, reducing account-enumeration timing differences.
- Apache sends common security headers and one-day cache headers for static assets; PHP OPcache is enabled for the immutable container code.
- Container tags are version-pinned. Rebuild regularly as security patches are released.
- HTTPS responses use Secure, HttpOnly, SameSite=Strict session cookies. The Cloudflare tunnel provides HTTPS to visitors; direct local HTTP access uses non-Secure cookies.

## Temporary internet demo
The optional `internet-share` profile starts a temporary Cloudflare HTTPS tunnel. Anyone holding its temporary URL can access the forum, register, and post. Do not enter private information.

1. Start it with `docker compose --profile internet-share up -d cloudflared`.
2. In a second terminal, read the URL with `docker compose --profile internet-share logs -f cloudflared`.
3. Share the URL with your friend. Stop sharing with `docker compose --profile internet-share stop cloudflared`.

## Security scan and maintenance
OWASP Top 10 is an awareness baseline, not a complete verification standard. Run `powershell -ExecutionPolicy Bypass -File tests/security-smoke.ps1` after rebuilding. It performs only read-only requests plus a rejected CSRF POST. Use automated PHP static analysis, container/dependency vulnerability scans, OWASP ZAP against staging, and load tests before production deployment. Back up and test restore regularly; never delete the database volume to solve a migration issue.

For an existing local setup, set `APP_HTTPS=true` in `.env` before recreating the web container so Secure cookies and HSTS are also present on localhost. With the Cloudflare tunnel, HTTPS is detected from Cloudflare's visitor headers as well.
