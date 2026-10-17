<?php
declare(strict_types=1);
require __DIR__.'/../includes/db.php';
if(PHP_SAPI!=='cli'||$argc!==3){fwrite(STDERR,"Usage: php bin/create_admin.php USERNAME PASSWORD\n");exit(2);}[$script,$name,$pw]=$argv;if(!preg_match('/^[A-Za-z0-9_]{3,32}$/',$name)||strlen($pw)<16){fwrite(STDERR,"Use a valid username and password of 16+ characters.\n");exit(2);}$s=db()->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,'admin')");$s->execute([$name,password_hash($pw,PASSWORD_ARGON2ID)]);echo "Admin created.\n";
