<?php
declare(strict_types=1);
function e(?string $s):string{return htmlspecialchars($s??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function token():string{return '<input type="hidden" name="csrf" value="'.e(csrf()).'">';}
function post():void{if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){http_response_code(400);exit('Invalid request');}}
function user():?array{return empty($_SESSION['uid'])?null:['id'=>(int)$_SESSION['uid'],'name'=>(string)$_SESSION['uname'],'role'=>(string)$_SESSION['role']];}
function login_required():void{if(!user()){header('Location:/login',true,303);exit;}}
function allowed(string $table,int $id):bool{if(!in_array($table,['threads','answers'],true))return false;$s=db()->prepare("SELECT user_id FROM $table WHERE id=?");$s->execute([$id]);$owner=$s->fetchColumn();$u=user();return $u&&$owner!==false&&($u['role']==='admin'||(int)$owner===$u['id']);}
function page(string $title,string $body):void{$u=user();echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.e($title).'</title><link rel="stylesheet" href="/style.css"><header><a href="/">Webboard</a> '.($u?e($u['name']).'<form class="inline" method="post" action="/logout">'.token().'<button>ออกจากระบบ</button></form>':'<a href="/login">ล็อกอิน</a> <a href="/register">สมัคร</a>').'</header><main><h1>'.e($title).'</h1>'.$body.'</main></html>';}
function fail(string $s,int $code=400):never{http_response_code($code);page('ข้อผิดพลาด','<p>'.e($s).'</p>');exit;}
function fields(string $title='',string $body=''):string{return '<label>หัวข้อ<input name="title" required maxlength="160" value="'.e($title).'"></label><label>รายละเอียด<textarea name="body" maxlength="10000" required>'.e($body).'</textarea></label><label>รูป (JPEG/PNG/GIF ไม่เกิน 3MB)<input type="file" name="image" accept="image/jpeg,image/png,image/gif"></label>';}
function actions(string $type,array $r):string{$table=$type==='thread'?'threads':'answers';if(!user()||!allowed($table,(int)$r['id']))return '';return '<a href="/edit?type='.$type.'&id='.(int)$r['id'].'">แก้ไข</a><form class="inline" method="post" action="/delete">'.token().'<input type="hidden" name="type" value="'.$type.'"><input type="hidden" name="id" value="'.(int)$r['id'].'"><button>ลบ</button></form>';}
