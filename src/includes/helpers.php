<?php
declare(strict_types=1);
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// มี algorithm เช็ครหัสผ่าน ให้มันไม่ตั้งโง่ๆได้
function password_is_strong(string $password): bool
{
    if (strlen($password) < 12 || strlen($password) > 200) {
        return false;
    }

    $groups = 0;
    $groups += preg_match('/[a-z]/', $password) === 1 ? 1 : 0;
    $groups += preg_match('/[A-Z]/', $password) === 1 ? 1 : 0;
    $groups += preg_match('/[0-9]/', $password) === 1 ? 1 : 0;
    $groups += preg_match('/[^a-zA-Z0-9]/', $password) === 1 ? 1 : 0;

    return $groups >= 3;
}

function csrf(): string
{
    if (empty($_SESSION['csrf']))
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function token(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}
function post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }
    if (!hash_equals(csrf(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request');
    }
}
function user(): ?array
{
    return empty($_SESSION['uid']) ? null : ['id' => (int) $_SESSION['uid'], 'name' => (string) $_SESSION['uname']];
}
function display_name(array $r): string
{
    return (string) (($r['full_name'] ?? '') ?: ($r['username'] ?? 'สมาชิก'));
}
function avatar(?string $name, string $alt = ''): string
{
    return $name ? '<img class="avatar" src="/image?name=' . e($name) . '" alt="' . e($alt) . '">' : '';
}
function header_avatar(): string
{
    $u = user();
    if (!$u)
        return '';
    if (!array_key_exists('profile_image', $_SESSION)) {
        try {
            $q = db()->prepare('SELECT profile_image FROM users WHERE id=?');
            $q->execute([$u['id']]);
            $image = $q->fetchColumn();
            $_SESSION['profile_image'] = is_string($image) ? $image : null;
        } catch (Throwable $e) {
            $_SESSION['profile_image'] = null;
        }
    }
    $image = $_SESSION['profile_image'] ?? null;
    if (is_string($image) && preg_match('/^[a-f0-9]{32}\.(jpg|png|gif)$/', $image))
        return '<img class="account-avatar" src="/image?name=' . e($image) . '" alt="">';
    return '<span class="account-dot">' . e(mb_substr($u['name'], 0, 1)) . '</span>';
}
function login_required(): void
{
    if (!user()) {
        header('Location:/login', true, 303);
        exit;
    }
}
function allowed(string $table, int $id): bool
{
    $u = user();
    if (!$u || $id < 1 || !in_array($table, ['threads', 'answers'], true)) {
        return false;
    }

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

function page(string $title, string $body): void
{
    $u = user();
    $search = e(trim((string) ($_GET['q'] ?? '')));
    $sort = (($_GET['sort'] ?? '') === 'popular') ? 'popular' : 'latest';
    $authNav = $u
        ? '<a class="account-link" href="/profile">' . header_avatar() . '<span class="account-name">' . e($u['name']) . '</span></a><form class="inline logout-form" method="post" action="/logout">' . token() . '<button>ออกจากระบบ</button></form>'
        : '<a class="account-link" href="/login">เข้าสู่ระบบ</a><a class="signup-link" href="/register">สมัครสมาชิก</a>';
    $writeUrl = $u ? '/new' : '/login';
    $mobileLast = $u ? '<a href="/profile"><span aria-hidden="true">◉</span>โปรไฟล์</a>' : '<a href="/register"><span aria-hidden="true">◉</span>สมัครสมาชิก</a>';
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#171626"><title>' . e($title) . ' · Webboard</title><link rel="stylesheet" href="/style.css?v=20261008-ui5"></head><body>';
    echo '<a class="skip-link" href="#main-content">ข้ามไปยังเนื้อหา</a><header class="site-header"><div class="header-top"><a class="brand" href="/" aria-label="Webboard หน้าแรก"><span class="brand-mark">W</span><span>Webboard</span></a>';
    echo '<form class="site-search" method="get" action="/" role="search"><label class="visually-hidden" for="site-search-input">ค้นหากระทู้</label><input id="site-search-input" type="search" name="q" maxlength="100" value="' . $search . '" placeholder="ค้นหากระทู้ในชุมชน"><button type="submit" aria-label="ค้นหา">⌕</button></form><div class="account-nav">' . $authNav . '</div></div>';
    echo '<nav class="feed-nav" aria-label="เมนูเว็บบอร์ด"><a class="nav-pill' . ($sort === 'latest' ? ' is-active' : '') . '" href="/?sort=latest">หน้าแรก</a><a class="nav-pill write-pill" href="' . $writeUrl . '">＋ ตั้งกระทู้</a></nav></header>';
    echo '<main id="main-content" class="page-shell"><div class="page-title"><span class="page-title-mark" aria-hidden="true"></span><h1>' . e($title) . '</h1></div>' . $body . '</main>';
    echo '<nav class="mobile-dock" aria-label="เมนูด้านล่าง"><a href="/"><span aria-hidden="true">⌂</span>หน้าแรก</a><a href="' . $writeUrl . '"><span aria-hidden="true">＋</span>ตั้งกระทู้</a>' . $mobileLast . '</nav></body></html>';
}

function fail(string $s, int $code = 400): never
{
    http_response_code($code);
    page('ข้อผิดพลาด', '<p>' . e($s) . '</p>');
    exit;
}
function fields(string $title = '', string $body = ''): string
{
    return '<label>หัวข้อ<input name="title" required maxlength="160" value="' . e($title) . '"></label><label>รายละเอียด<textarea name="body" maxlength="10000" required>' . e($body) . '</textarea></label><label>รูป (JPEG/PNG/GIF ไม่เกิน 3MB)<input type="file" name="image" accept="image/jpeg,image/png,image/gif"></label>';
}
function actions(string $type, array $r): string
{
    $table = $type === 'thread' ? 'threads' : 'answers';
    if (!user() || !allowed($table, (int) $r['id']))
        return '';
    return '<div class="post-actions"><a href="/edit?type=' . $type . '&id=' . (int) $r['id'] . '">แก้ไข</a><form class="inline" method="post" action="/delete">' . token() . '<input type="hidden" name="type" value="' . $type . '"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><button>ลบ</button></form></div>';
}
