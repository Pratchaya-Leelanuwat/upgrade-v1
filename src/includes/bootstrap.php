<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/upload.php';
require_once __DIR__.'/security.php';
$requestId=bin2hex(random_bytes(12));
$_SERVER['REQUEST_ID']=$requestId;
header('X-Request-ID: '.$requestId);
if(strlen(envv('APP_SECRET'))<32){http_response_code(500);exit('Application secret is not configured.');}
$dummyLoginHash=@file_get_contents('/etc/webboard-dummy-password-hash');
if(!is_string($dummyLoginHash)||!str_starts_with($dummyLoginHash,'$argon2id$')){http_response_code(500);exit('Application authentication is not configured.');}
define('DUMMY_LOGIN_HASH',trim($dummyLoginHash));
if(session_status()!==PHP_SESSION_ACTIVE){
 $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||envv('APP_HTTPS')==='true';
 if(envv('APP_TRUST_CF_HEADERS')==='true'&&filter_var($_SERVER['HTTP_CF_CONNECTING_IP']??'',FILTER_VALIDATE_IP)){
  $visitor=json_decode((string)($_SERVER['HTTP_CF_VISITOR']??''),true);
  $https=$https||(is_array($visitor)&&($visitor['scheme']??'')==='https');
 }
 session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Strict','path'=>'/']);
 session_start();
 if($https)header('Strict-Transport-Security: max-age=31536000');
}
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Permitted-Cross-Domain-Policies: none');
