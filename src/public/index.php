<?php
declare(strict_types=1);
require __DIR__.'/../includes/bootstrap.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
try {
 switch($path) {
  case '/':
   $pageNumber=max(1,min(1000,(int)($_GET['page']??1)));$pageSize=20;$offset=($pageNumber-1)*$pageSize;
   $search=mb_substr(trim((string)($_GET['q']??'')),0,100);
   $sortInput=(string)($_GET['sort']??'latest');
   $sort=in_array($sortInput,['popular','unanswered'],true)?$sortInput:'latest';
   $filters=[];$params=[];
   if($search!==''){$filters[]='(t.title LIKE ? OR t.body LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
   if($sort==='unanswered')$filters[]='NOT EXISTS (SELECT 1 FROM answers pending WHERE pending.thread_id=t.id)';
   $where=$filters?' WHERE '.implode(' AND ',$filters):'';
   $order=$sort==='popular'?'n DESC,t.created_at DESC,t.id DESC':'t.created_at DESC,t.id DESC';
   $sql='SELECT t.*,u.username,u.full_name,u.profile_image,(SELECT COUNT(*) FROM answers a WHERE a.thread_id=t.id) n FROM threads t JOIN users u ON u.id=t.user_id'.$where.' ORDER BY '.$order.' LIMIT ? OFFSET ?';
   $q=db()->prepare($sql);$q->execute([...$params,$pageSize+1,$offset]);$rows=$q->fetchAll();$hasNext=count($rows)>$pageSize;if($hasNext)$rows=array_slice($rows,0,$pageSize);
   $query=$search!==''?'&q='.rawurlencode($search):'';
   $unansweredCount=(int)db()->query('SELECT COUNT(*) FROM threads t WHERE NOT EXISTS (SELECT 1 FROM answers a WHERE a.thread_id=t.id)')->fetchColumn();
   $body='<section class="feed-intro"><div><p class="eyebrow">ชุมชนเว็บบอร์ด</p><h2>พื้นที่พูดคุยของคุณ</h2><p class="muted">ตั้งคำถาม แบ่งปันเรื่องราว และร่วมตอบในหัวข้อที่สนใจ</p></div><a class="help-callout" href="/?sort=unanswered"><span class="help-icon" aria-hidden="true">?</span><span><small>ชวนร่วมตอบ</small><strong>'.$unansweredCount.' กระทู้รอคำตอบ</strong></span><span class="help-arrow" aria-hidden="true">→</span></a></section>';
   $body.='<div class="feed-controls"><div class="feed-tabs" aria-label="เรียงกระทู้"><a class="feed-tab'.($sort==='latest'?' is-active':'').'" href="/?sort=latest'.($search!==''?'&q='.rawurlencode($search):'').'">ล่าสุด</a><a class="feed-tab'.($sort==='popular'?' is-active':'').'" href="/?sort=popular'.($search!==''?'&q='.rawurlencode($search):'').'">ตอบเยอะ</a><a class="feed-tab'.($sort==='unanswered'?' is-active':'').'" href="/?sort=unanswered'.($search!==''?'&q='.rawurlencode($search):'').'">รอคำตอบ</a></div><span class="feed-count">'.($search!==''?'ผลค้นหา: '.e($search):($sort==='unanswered'?'กระทู้ที่ยังไม่มีคำตอบ':'กระทู้จากสมาชิก')).'</span></div>';
   $body.='<div class="thread-list">';
   foreach($rows as $row){
    $body.='<article class="thread-card"><div class="thread-card-main"><div class="thread-card-meta"><span class="topic-badge">กระทู้</span><span>'.e($row['created_at']).'</span></div>';
    $body.='<h2><a href="/thread?id='.(int)$row['id'].'">'.e($row['title']).'</a></h2>';
    $body.='<p class="thread-excerpt">'.e(mb_substr($row['body'],0,240)).(mb_strlen($row['body'])>240?'…':'').'</p>';
    $body.='<div class="thread-card-footer"><p class="byline">'.avatar($row['profile_image'],display_name($row)).'<span>'.e(display_name($row)).'</span></p><a class="reply-count" href="/thread?id='.(int)$row['id'].'">ตอบ <strong>'.(int)$row['n'].'</strong></a></div></div>';
    if(!empty($row['image_name']))$body.='<a class="thread-thumb-link" href="/thread?id='.(int)$row['id'].'" aria-label="เปิดกระทู้ '.e($row['title']).'"><img class="thread-thumb" src="/image?name='.e($row['image_name']).'" alt=""></a>';
    $body.='</article>';
   }
   if(!$rows)$body.='<section class="empty-state"><span class="empty-mark">⌕</span><h2>'.($search!==''?'ไม่พบกระทู้ที่ค้นหา':($sort==='unanswered'?'ไม่มีคำถามที่รอคำตอบ':'ยังไม่มีกระทู้')).'</h2><p class="muted">'.($search!==''?'ลองใช้คำค้นอื่น หรือกลับไปดูกระทู้ล่าสุด':($sort==='unanswered'?'ทุกกระทู้มีคำตอบแล้ว ลองดูกระทู้อื่น':'เริ่มบทสนทนาแรกของชุมชนได้เลย')).'</p><a class="primary-link" href="'.($search!==''||$sort==='unanswered'?'/?sort=latest':'/new').'">'.($search!==''||$sort==='unanswered'?'ดูกระทู้ล่าสุด':'ตั้งกระทู้แรก').'</a></section>';
   $body.='</div><nav class="pagination" aria-label="เปลี่ยนหน้ากระทู้">';
   if($pageNumber>1)$body.='<a href="/?page='.($pageNumber-1).'&sort='.$sort.$query.'">← ก่อนหน้า</a>';
   if($hasNext)$body.='<a href="/?page='.($pageNumber+1).'&sort='.$sort.$query.'">ถัดไป →</a>';
   $body.='</nav>';
   page('เว็บบอร์ด',$body);break;

  case '/register':
   if($_SERVER['REQUEST_METHOD']==='POST'){
    post();request_limit('register',5,3600);
    $name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
    if($name===''||mb_strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||strlen($password)<12||strlen($password)>200||!hash_equals($password,$confirm))fail('ตรวจสอบชื่อ อีเมล และรหัสผ่าน (อย่างน้อย 12 ตัว) อีกครั้ง');
    $image=save_image('avatar');
    try{$q=db()->prepare('INSERT INTO users(username,email,full_name,password_hash,profile_image) VALUES(?,?,?,?,?)');$q->execute([$email,$email,$name,password_hash($password,PASSWORD_ARGON2ID),$image]);}
    catch(Throwable $e){remove_image($image);throw $e;}
    header('Location:/login?registered=1',true,303);exit;
   }
   $body='<p class="muted">สมัครด้วยอีเมล อีเมลจะใช้เข้าสู่ระบบและแก้ไขภายหลังไม่ได้</p><form method="post" enctype="multipart/form-data">'.token().'<label>ชื่อ-นามสกุล<input name="full_name" required maxlength="120" autocomplete="name"></label><label>อีเมล (ชื่อเข้าสู่ระบบ)<input type="email" name="email" required maxlength="254" autocomplete="email"></label><label>รูปโปรไฟล์ (ไม่บังคับ)<input type="file" name="avatar" accept="image/jpeg,image/png,image/gif"></label><label>รหัสผ่าน (อย่างน้อย 12 ตัว)<input type="password" name="password" required minlength="12" maxlength="200" autocomplete="new-password"></label><label>ยืนยันรหัสผ่าน<input type="password" name="confirm_password" required minlength="12" maxlength="200" autocomplete="new-password"></label><button>สร้างบัญชี</button></form>';
   page('สมัครสมาชิก',$body);break;

  case '/login':
   if($_SERVER['REQUEST_METHOD']==='POST'){
    post();$login=strtolower(trim((string)($_POST['email']??'')));$secret=envv('APP_SECRET');
    if(strlen($secret)<32)fail('ตั้งค่า APP_SECRET ก่อนใช้งาน',500);
    $ipHash=hash_hmac('sha256',client_ip(),$secret);$nameHash=hash_hmac('sha256',$login,$secret);
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 15 MINUTE');
    $q=db()->prepare('SELECT (SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND attempted_at>NOW()-INTERVAL 15 MINUTE) AS ip_count,(SELECT COUNT(*) FROM login_attempts WHERE username_hash=? AND attempted_at>NOW()-INTERVAL 15 MINUTE) AS account_count');$q->execute([$ipHash,$nameHash]);$attempts=$q->fetch();
    if((int)$attempts['ip_count']>=100||(int)$attempts['account_count']>=10){security_event('login_rate_limited');header('Retry-After: 900');fail('ลองใหม่ภายหลัง',429);}
    if(filter_var($login,FILTER_VALIDATE_EMAIL)){$q=db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$q->execute([$login]);}
    else{$q=db()->prepare('SELECT * FROM users WHERE email IS NULL AND username=? LIMIT 1');$q->execute([$login]);}
    $account=$q->fetch();
    $passwordHash=$account?$account['password_hash']:DUMMY_LOGIN_HASH;
    $passwordValid=password_verify((string)($_POST['password']??''),$passwordHash);
    if(!$account||!$passwordValid){
     security_event('login_failure');$q=db()->prepare('INSERT INTO login_attempts(ip_hash,username_hash) VALUES(?,?)');$q->execute([$ipHash,$nameHash]);
     fail('อีเมลหรือรหัสผ่านไม่ถูกต้อง',401);
    }
    $q=db()->prepare('DELETE FROM login_attempts WHERE username_hash=?');$q->execute([$nameHash]);
    security_event('login_success');session_regenerate_id(true);$_SESSION['uid']=(int)$account['id'];$_SESSION['uname']=display_name($account);$_SESSION['role']=$account['role'];$_SESSION['profile_image']=$account['profile_image']??null;
    header('Location:/',true,303);exit;
   }
   $notice=isset($_GET['registered'])?'<p class="success">สมัครสำเร็จ เข้าสู่ระบบได้เลย</p>':'';
   page('เข้าสู่ระบบ',$notice.'<form method="post">'.token().'<label>อีเมล<input type="text" name="email" required autocomplete="username"></label><label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password"></label><button>เข้าสู่ระบบ</button></form><p class="muted">บัญชีแอดมินเดิมที่ยังไม่มีอีเมล ใช้ชื่อผู้ใช้เดิมเข้าสู่ระบบได้</p>');break;

  case '/profile':
   login_required();$q=db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([user()['id']]);$me=$q->fetch();if(!$me)fail('ไม่พบบัญชี',404);
   if($_SERVER['REQUEST_METHOD']==='POST'){
    post();request_limit('profile',20,3600);$action=(string)($_POST['action']??'');
    if($action==='profile'){
     $name=trim((string)($_POST['full_name']??''));if($name===''||mb_strlen($name)>120)fail('กรอกชื่อ-นามสกุลไม่เกิน 120 ตัวอักษร');
     $email=$me['email'];if($email===null){$email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)fail('กรอกอีเมลให้ถูกต้อง');}
     $oldImage=$me['profile_image'];$removeAvatar=!empty($_POST['remove_avatar']);$image=$removeAvatar?null:(save_image('avatar')??$oldImage);
     try{$q=db()->prepare('UPDATE users SET username=?,email=?,full_name=?,profile_image=? WHERE id=?');$q->execute([$email??$me['username'],$email,$name,$image,user()['id']]);}
     catch(Throwable $e){if($image!==$oldImage)remove_image($image);throw $e;}
     if($oldImage&&$image!==$oldImage)remove_image($oldImage);
     $_SESSION['uname']=$name;$_SESSION['profile_image']=$image;header('Location:/profile?saved=1',true,303);exit;
    }
    if($action==='password'){
     $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
     if(!password_verify($current,$me['password_hash']))fail('รหัสผ่านปัจจุบันไม่ถูกต้อง',401);
     if(strlen($new)<12||strlen($new)>200||!hash_equals($new,$confirm))fail('รหัสผ่านใหม่ต้องยาวอย่างน้อย 12 ตัวและกรอกยืนยันให้ตรงกัน');
     $q=db()->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([password_hash($new,PASSWORD_ARGON2ID),user()['id']]);session_regenerate_id(true);header('Location:/profile?saved=1',true,303);exit;
    }
    fail('คำขอไม่ถูกต้อง');
   }
   $body=isset($_GET['saved'])?'<p class="success">บันทึกข้อมูลแล้ว</p>':'';
   $body.='<section class="profile-card">'.avatar($me['profile_image'],display_name($me)).'<div><h2>'.e(display_name($me)).'</h2><p class="muted">'.e($me['email']??'บัญชีเดิม — เพิ่มอีเมลได้ครั้งเดียว').'</p></div></section>';
   $body.='<form class="inline profile-logout" method="post" action="/logout">'.token().'<button>ออกจากระบบ</button></form>';
   $body.='<h2>ข้อมูลโปรไฟล์</h2><form method="post" enctype="multipart/form-data">'.token().'<input type="hidden" name="action" value="profile"><label>ชื่อ-นามสกุล<input name="full_name" required maxlength="120" value="'.e($me['full_name']).'" autocomplete="name"></label>';
   if($me['email']===null)$body.='<label>อีเมล (ตั้งได้ครั้งเดียว)<input type="email" name="email" required maxlength="254" autocomplete="email"></label>';
   else $body.='<p class="readonly-field"><strong>อีเมล</strong><br>'.e($me['email']).'<br><small>อีเมลใช้เข้าสู่ระบบและเปลี่ยนไม่ได้</small></p>';
   $body.='<label>เปลี่ยนรูปโปรไฟล์<input type="file" name="avatar" accept="image/jpeg,image/png,image/gif"></label>'.($me['profile_image']?'<label class="check-row"><input type="checkbox" name="remove_avatar" value="1"> ลบรูปโปรไฟล์</label>':'').'<button>บันทึกโปรไฟล์</button></form>';
   $body.='<h2>เปลี่ยนรหัสผ่าน</h2><form method="post">'.token().'<input type="hidden" name="action" value="password"><label>รหัสผ่านปัจจุบัน<input type="password" name="current_password" required autocomplete="current-password"></label><label>รหัสผ่านใหม่ (อย่างน้อย 12 ตัว)<input type="password" name="new_password" required minlength="12" maxlength="200" autocomplete="new-password"></label><label>ยืนยันรหัสผ่านใหม่<input type="password" name="confirm_password" required minlength="12" maxlength="200" autocomplete="new-password"></label><button>เปลี่ยนรหัสผ่าน</button></form>';
   page('โปรไฟล์',$body);break;

  case '/logout':
   post();$_SESSION=[];session_destroy();header('Location:/login',true,303);exit;

  case '/new':
   login_required();
   if($_SERVER['REQUEST_METHOD']==='POST'){
    post();request_limit('thread',10,3600);$title=trim((string)($_POST['title']??''));$body=trim((string)($_POST['body']??''));
    if($title===''||mb_strlen($title)>160||$body===''||mb_strlen($body)>10000)fail('ข้อมูลไม่ถูกต้อง');
    $image=save_image();
    try{$q=db()->prepare('INSERT INTO threads(user_id,title,body,image_name) VALUES(?,?,?,?)');$q->execute([user()['id'],$title,$body,$image]);$id=(int)db()->lastInsertId();}
    catch(Throwable $e){remove_image($image);throw $e;}
    header('Location:/thread?id='.$id,true,303);exit;
   }
   page('ตั้งกระทู้','<form method="post" enctype="multipart/form-data">'.token().fields().'<button>โพสต์</button></form>');break;

  case '/thread':
   $id=(int)($_GET['id']??0);$q=db()->prepare('SELECT t.*,u.username,u.full_name,u.profile_image FROM threads t JOIN users u ON u.id=t.user_id WHERE t.id=?');$q->execute([$id]);$thread=$q->fetch();if(!$thread)fail('ไม่พบกระทู้',404);
   $body='<article><h2>'.e($thread['title']).'</h2><p class="byline">'.avatar($thread['profile_image'],display_name($thread)).' <span>'.e(display_name($thread)).' · '.e($thread['created_at']).'</span></p><p>'.nl2br(e($thread['body'])).'</p>'.image_tag($thread['image_name']).actions('thread',$thread).'</article>';
   if(user())$body.='<h2>เขียนคำตอบ</h2><form method="post" action="/answer" enctype="multipart/form-data">'.token().'<input type="hidden" name="thread_id" value="'.$id.'">'.fields().'<button>ส่งคำตอบ</button></form>';
   else $body.='<p><a href="/login">เข้าสู่ระบบเพื่อตอบ</a></p>';
   $answerPage=max(1,min(1000,(int)($_GET['answers']??1)));$answerSize=50;$answerOffset=($answerPage-1)*$answerSize;
   $q=db()->prepare('SELECT a.*,u.username,u.full_name,u.profile_image FROM answers a JOIN users u ON u.id=a.user_id WHERE a.thread_id=? ORDER BY a.created_at,a.id LIMIT ? OFFSET ?');$q->execute([$id,$answerSize+1,$answerOffset]);$answerRows=$q->fetchAll();$answerHasNext=count($answerRows)>$answerSize;if($answerHasNext)$answerRows=array_slice($answerRows,0,$answerSize);
   foreach($answerRows as $answer)$body.='<article><h3>'.e($answer['title']).'</h3><p class="byline">'.avatar($answer['profile_image'],display_name($answer)).' <span>'.e(display_name($answer)).' · '.e($answer['created_at']).'</span></p><p>'.nl2br(e($answer['body'])).'</p>'.image_tag($answer['image_name']).actions('answer',$answer).'</article>';
   if($answerPage>1)$body.='<a href="/thread?id='.$id.'&answers='.($answerPage-1).'">← คำตอบก่อนหน้า</a> ';if($answerHasNext)$body.='<a href="/thread?id='.$id.'&answers='.($answerPage+1).'">คำตอบถัดไป →</a>';
   page($thread['title'],$body);break;

  case '/answer':
   login_required();post();request_limit('answer',30,3600);$threadId=(int)($_POST['thread_id']??0);$title=trim((string)($_POST['title']??''));$body=trim((string)($_POST['body']??''));
   if($threadId<1||$title===''||mb_strlen($title)>160||$body===''||mb_strlen($body)>10000)fail('ข้อมูลไม่ถูกต้อง');
   $image=save_image();
   try{$q=db()->prepare('INSERT INTO answers(thread_id,user_id,title,body,image_name) VALUES(?,?,?,?,?)');$q->execute([$threadId,user()['id'],$title,$body,$image]);}
   catch(Throwable $e){remove_image($image);throw $e;}
   header('Location:/thread?id='.$threadId,true,303);exit;

  case '/edit':
   login_required();$type=($_GET['type']??'')==='answer'?'answer':'thread';$table=$type==='answer'?'answers':'threads';$id=(int)($_GET['id']??0);
   if(!allowed($table,$id))fail('ไม่มีสิทธิ์หรือไม่พบรายการ',403);
   $q=db()->prepare("SELECT * FROM $table WHERE id=?");$q->execute([$id]);$row=$q->fetch();
   if($_SERVER['REQUEST_METHOD']==='POST'){
    post();if(!allowed($table,$id))fail('ไม่มีสิทธิ์',403);request_limit('edit',30,3600);$title=trim((string)($_POST['title']??''));$body=trim((string)($_POST['body']??''));
    if($title===''||mb_strlen($title)>160||$body===''||mb_strlen($body)>10000)fail('ข้อมูลไม่ถูกต้อง');
    $image=save_image('image',$row['image_name']);
    try{$q=db()->prepare("UPDATE $table SET title=?,body=?,image_name=? WHERE id=?");$q->execute([$title,$body,$image,$id]);}
    catch(Throwable $e){if($image!==$row['image_name'])remove_image($image);throw $e;}
    if($row['image_name']&&$image!==$row['image_name'])remove_image($row['image_name']);
    header('Location:/thread?id='.(int)($row['thread_id']??$id),true,303);exit;
   }
   page('แก้ไข','<form method="post" enctype="multipart/form-data">'.token().fields($row['title'],$row['body']).'<button>บันทึก</button></form>');break;

  case '/delete':
   login_required();post();$type=($_POST['type']??'')==='answer'?'answer':'thread';$table=$type==='answer'?'answers':'threads';$id=(int)($_POST['id']??0);
   if(!allowed($table,$id))fail('ไม่มีสิทธิ์หรือไม่พบรายการ',403);request_limit('delete',10,3600);
   $q=db()->prepare("SELECT image_name,thread_id FROM $table WHERE id=?");$q->execute([$id]);$row=$q->fetch();$images=[];
   if($row['image_name'])$images[]=$row['image_name'];
   if($type==='thread'){$q=db()->prepare('SELECT image_name FROM answers WHERE thread_id=? AND image_name IS NOT NULL');$q->execute([$id]);foreach($q->fetchAll() as $answer)$images[]=$answer['image_name'];}
   $q=db()->prepare("DELETE FROM $table WHERE id=?");$q->execute([$id]);foreach($images as $image)remove_image($image);
   header('Location:/thread?id='.(int)($row['thread_id']??$id),true,303);exit;

  case '/image':
   $name=(string)($_GET['name']??'');if(!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif)$/',$name))fail('ไม่พบไฟล์',404);
   $file=UPLOAD_DIR.'/'.$name;if(!is_file($file))fail('ไม่พบไฟล์',404);
   header('Content-Type:'.(['jpg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif'][pathinfo($name,PATHINFO_EXTENSION)]));header('Cache-Control: public, max-age=604800, immutable');readfile($file);exit;

  default:fail('ไม่พบหน้า',404);
 }
} catch(UserFacingException $e){fail($e->getMessage());}
catch(Throwable $e){error_log('request_failure request_id='.$requestId.' type='.get_class($e));fail('ระบบขัดข้อง กรุณาลองใหม่ รหัสอ้างอิง '.$requestId,500);}
function image_tag(?string $name):string{return $name?'<p><img class="photo" src="/image?name='.e($name).'" alt="รูปแนบ"></p>':'';}
