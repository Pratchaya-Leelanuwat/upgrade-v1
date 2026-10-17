<?php
declare(strict_types=1);
require_once __DIR__.'/db.php'; require_once __DIR__.'/helpers.php'; require_once __DIR__.'/upload.php';
if(session_status()!==PHP_SESSION_ACTIVE){$https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||envv('APP_HTTPS')==='true';session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Strict','path'=>'/']);session_start();}
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
