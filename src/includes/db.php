<?php
declare(strict_types=1);
function envv(string $k,string $d=''):string{$v=getenv($k);return $v===false?$d:$v;}
function db():PDO{static $p;if(!$p)$p=new PDO('mysql:host='.envv('DB_HOST','db').';dbname='.envv('DB_NAME','webboard').';charset=utf8mb4',envv('DB_USER'),envv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $p;}
