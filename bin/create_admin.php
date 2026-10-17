<?php
declare(strict_types=1);
require __DIR__.'/../includes/db.php';
if(PHP_SAPI!=='cli'||$argc!==3){fwrite(STDERR,"Usage: php bin/create_admin.php EMAIL FULL_NAME\n");exit(2);}
[, $email,$fullName]=$argv;
$email=strtolower(trim($email));$fullName=trim($fullName);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||$fullName===''||mb_strlen($fullName)>120){fwrite(STDERR,"Use a valid email and full name.\n");exit(2);}
if(!is_resource(STDIN)||!is_dir('/dev')){fwrite(STDERR,"Run this command in an interactive container terminal.\n");exit(2);}
$stty=trim((string)shell_exec('stty -g < /dev/tty 2>/dev/null'));
if($stty===''){fwrite(STDERR,"A TTY is required to read a hidden password.\n");exit(2);}
$changed=shell_exec('stty -echo < /dev/tty 2>/dev/null');
fwrite(STDERR,'New admin password (16+ characters): ');
$password=fgets(STDIN);
shell_exec('stty '.escapeshellarg($stty).' < /dev/tty 2>/dev/null');
fwrite(STDERR,"\n");
$password=rtrim((string)$password,"\r\n");
if(strlen($password)<16||strlen($password)>200){fwrite(STDERR,"Password must be 16–200 characters.\n");exit(2);}
$q=db()->prepare("INSERT INTO users(username,email,full_name,password_hash,role) VALUES(?,?,?,?,'admin')");
$q->execute([$email,$email,$fullName,password_hash($password,PASSWORD_ARGON2ID)]);
echo "Admin created.\n";
