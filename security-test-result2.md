# PHP authorization and API security review

**Date:** 2026-10-18  
**Scope:** PHP source in `src/` (root deployment) and the duplicate `webboard/src/` copy. Review focused on authentication, session authorization, and thread/reply edit and delete endpoints.  
**Execution:** Source review plus one read-only `GET http://127.0.0.1:8080/login` attempt. No records were changed and no password cracking or load testing was attempted.

## Result summary

The source currently checks ownership for edits and deletes. A normal member changing an `id` in Burp should not gain access to another member's post: the handlers call `allowed()` before displaying/editing/deleting, and the edit handler checks again on POST. Mutating routes also require a CSRF token. I found no source-level path for an ordinary member to promote their role by changing a form field or request parameter.

There is an authorization weakness in the admin role lifecycle: the PHP session stores the user's role at login, and `allowed()` trusts that cached role. If an admin is demoted or disabled while still logged in, the old session continues to authorize admin edits and deletes until it expires or is invalidated. A user with that existing admin session can replay the regular edit/delete API calls through Burp. This is a stale authorization decision, not a demonstrated unauthenticated privilege escalation.

The proposed PHP changes below are included **for review and application only**; this report does not change project PHP files.

## Findings

### A-01 — Admin authorization trusts a role cached at login

**Severity: Medium (admin privilege remains after role revocation).**

In `src/public/index.php`, successful login copies `$account['role']` into `$_SESSION['role']`. In `src/includes/helpers.php`, `allowed()` grants access when that session value is `admin`. Consequently, changing the user's role in the database does not revoke authorization from an already-authenticated session. The duplicate `webboard/` implementation uses the same session-role pattern.

**Abuse conditions:** An attacker needs a valid admin session (for example, a compromised/stolen session), and an administrator must revoke that account's role while the session remains active. The attacker can then submit authenticated edit/delete requests until the session is invalidated or expires. Burp can replay/modify such requests; it cannot by itself create a valid session or bypass the existing ownership check for a regular member.

### Existing authorization strengths observed

- `/edit` and `/delete` accept only `threads` or `answers` as the dynamic table choice.
- Both handlers check the object owner; the admin exception is currently based on the stale session role described above.
- Edit rechecks authorization on POST; delete checks authorization before selecting/deleting the row.
- SQL values are parameterized, including object IDs. The IDs are not concatenated into SQL.
- Mutations call `post()`, which requires a session CSRF token.
- Login regenerates the PHP session ID after successful authentication.

These are source observations, not successful live API tests.

## PHP hardening to apply

### 1. Replace `allowed()` so it reads the current role from the database

Replace the existing `allowed()` in `src/includes/helpers.php` (and the equivalent function in `webboard/src/includes/helpers.php` if that copy remains deployable) with:

```php
function allowed(string $table, int $id): bool
{
    $u = user();
    if (!$u || $id < 1 || !in_array($table, ['threads', 'answers'], true)) {
        return false;
    }

    // $table is safe to interpolate only because it is allowlisted above.
    // Do not authorize from the role cached in the PHP session.
    $sql = "SELECT EXISTS (
        SELECT 1
        FROM {$table} AS item
        WHERE item.id = ?
          AND (
              item.user_id = ?
              OR EXISTS (
                  SELECT 1 FROM users AS actor
                  WHERE actor.id = ? AND actor.role = 'admin'
              )
          )
    )";
    $q = db()->prepare($sql);
    $q->execute([$id, $u['id'], $u['id']]);
    return (bool) $q->fetchColumn();
}
```

This makes role revocation effective on the next authorization check. Keep the existing `login_required()` and CSRF check; this is an authorization fix, not a replacement for authentication or CSRF protection.

### 2. Make the authorization condition part of each write

For defense against a role change between the check and the write, also constrain the actual update/delete statement. Keep `$table` restricted to `threads` or `answers`, as the handlers already do. For the edit route, replace the unconditional `UPDATE` with:

```php
$q = db()->prepare("UPDATE {$table}
    SET title = ?, body = ?, image_name = ?
    WHERE id = ?
      AND (
          user_id = ?
          OR EXISTS (
              SELECT 1 FROM users
              WHERE id = ? AND role = 'admin'
          )
      )");
$q->execute([$title, $body, $image, $id, user()['id'], user()['id']]);
```

For delete, replace the unconditional `DELETE` with:

```php
$q = db()->prepare("DELETE FROM {$table}
    WHERE id = ?
      AND (
          user_id = ?
          OR EXISTS (
              SELECT 1 FROM users
              WHERE id = ? AND role = 'admin'
          )
      )");
$q->execute([$id, user()['id'], user()['id']]);
if ($q->rowCount() !== 1) {
    // Do not remove associated image files unless the database delete succeeded.
    fail('No permission or item not found', 403);
}
```

Keep image cleanup after a successful delete, as in the current route. For edit, a zero `rowCount()` can mean either authorization failed or the submitted values were unchanged, depending on MySQL settings; do not treat it alone as proof of denial. If the application needs a distinct response, perform a fresh `allowed()` check when no row was changed. Review the SQL against the MySQL version used by the deployment before rollout.

## API checks and results

The app was not listening on localhost, so the HTTP outcomes below are **source-derived expectations**, not observed responses. They should be repeated against a running isolated test instance using two member accounts and one admin account.

| Check | Request / manipulation to try | Expected/current source result | Assessment |
|---|---|---|---|
| Unauthenticated edit | `GET /edit?type=thread&id=<known-id>` with no session | `303` redirect to `/login` via `login_required()` | Control present in source; live response unavailable. |
| Cross-user edit IDOR | As member A, request `GET /edit?type=thread&id=<post-owned-by-B>` | `403` from `allowed()` | Existing source check should block the modified ID. Confirm on live app. |
| Cross-user edit by POST | As member A, submit a valid CSRF token to `/edit?type=thread&id=<B-id>` with changed fields | `403`; POST path rechecks `allowed()` before update | Existing source check should block. No write was attempted. |
| Cross-user delete IDOR | As member A, submit `POST /delete` with valid CSRF, `type=thread`, and B's ID | `403`; authorization check precedes delete | Existing source check should block. No delete was attempted. |
| Member edits own post | As owner, valid CSRF POST to edit own ID | Accepted if fields validate | Source permits; not exercised. |
| Admin manages another member's post | Admin session with valid CSRF to edit/delete another user's ID | Accepted while session role says `admin` | Intended feature; not exercised. |
| Admin revocation while logged in | Log in as admin; change that user's DB role to `member`; replay edit/delete using the old session | **Before proposed fix:** still authorized because `$_SESSION['role']` remains `admin`. **After fix:** denied on next check/write | Confirmed weakness by tracing login assignment and `allowed()` logic; runtime reproduction unavailable. |
| Client-supplied role tampering | Add/change `role=admin` in query/form fields as a member | No role is read from those request fields in the reviewed handlers | No direct request-parameter escalation found. A forged/stolen PHP session is outside this check. |
| CSRF omission | Send POST to edit/delete without a valid token | Rejected by `post()` with HTTP 400 | Source control present; live response unavailable. |
| SQL injection via ID/type | Try `id=1 OR 1=1`, and unexpected `type` values | ID is integer-cast and parameterized; table selected from fixed choices | No injection path found in reviewed handlers; no live payload sent. |

## Execution log and limitations

- **Attempted:** `GET http://127.0.0.1:8080/login` (read-only availability check).
- **Observed:** Connection refused / unable to connect to the remote server. The app was not running at that address.
- **Not performed:** Authenticated Burp replay, account-to-account IDOR requests, admin revocation reproduction, CSRF POSTs, password cracking, destructive edits/deletes, or automated scanners. No test accounts or live service were available.
- **Coverage limit:** Static review covered the relevant PHP login, helper, edit, and delete paths in both copies. It cannot establish deployed routing, database contents, session expiry behavior, reverse-proxy behavior, or whether either copy is actually in use.

## Recommended verification after applying the patch

On an isolated instance, create member A, member B, and an admin; create content owned by A and B; then replay the table's cross-user and admin-revocation requests with valid CSRF tokens. Verify cross-user operations return `403` and leave content unchanged, owners can still manage their own content, admins can manage all content while their DB role is admin, and the same admin session loses that ability immediately after demotion. Run checks against each deployment copy that can be started.
