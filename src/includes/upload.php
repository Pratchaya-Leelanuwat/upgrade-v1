<?php
declare(strict_types=1);

const UPLOAD_DIR='/var/www/private-uploads';

final class UserFacingException extends RuntimeException {}

function save_image(string $field='image',?string $old=null):?string{
 if(!preg_match('/^[a-z][a-z0-9_]*$/',$field))throw new UserFacingException('ช่องอัปโหลดไม่ถูกต้อง');
 if(($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return $old;
 $f=$_FILES[$field];
 if($f['error']!==UPLOAD_ERR_OK||$f['size']>3*1024*1024||!is_uploaded_file($f['tmp_name']))throw new UserFacingException('ไฟล์อัปโหลดไม่ถูกต้องหรือใหญ่เกิน 3MB');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
 $types=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
 $info=@getimagesize($f['tmp_name']);
 if(!$info||$info['mime']!==$mime||!isset($types[$mime])||$info[0]>3000||$info[1]>3000)throw new UserFacingException('รูปภาพไม่รองรับ');
 $im=@imagecreatefromstring(file_get_contents($f['tmp_name']));
 if(!$im)throw new UserFacingException('รูปภาพอ่านไม่ได้');
 $name=bin2hex(random_bytes(16)).'.'.$types[$mime];$dest=UPLOAD_DIR.'/'.$name;
 $ok=match($mime){'image/jpeg'=>imagejpeg($im,$dest,88),'image/png'=>imagepng($im,$dest,6),'image/gif'=>imagegif($im,$dest)};
 imagedestroy($im);
 if(!$ok)throw new UserFacingException('บันทึกรูปไม่สำเร็จ');
 return $name;
}

function remove_image(?string $n):void{
 if($n&&preg_match('/^[a-f0-9]{32}\.(jpg|png|gif)$/',$n))@unlink(UPLOAD_DIR.'/'.$n);
}
