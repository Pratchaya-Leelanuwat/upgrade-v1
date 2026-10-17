<?php
declare(strict_types=1);

function client_ip():string{
 $remote=(string)($_SERVER['REMOTE_ADDR']??'');
 $cf=(string)($_SERVER['HTTP_CF_CONNECTING_IP']??'');
 if(envv('APP_TRUST_CF_HEADERS')==='true'&&filter_var($cf,FILTER_VALIDATE_IP))return $cf;
 return filter_var($remote,FILTER_VALIDATE_IP)?$remote:'unknown';
}
function security_event(string $event):void{
 error_log(json_encode(['event'=>$event,'request_id'=>$_SERVER['REQUEST_ID']??'unknown','time'=>gmdate(DATE_ATOM)],JSON_UNESCAPED_SLASHES));
}
function request_limit(string $scope,int $limit,int $windowSeconds):void{
 if(!preg_match('/^[a-z][a-z0-9_-]{0,31}$/',$scope)||$limit<1||$windowSeconds<1)throw new InvalidArgumentException('Invalid rate limit');
 $secret=envv('APP_SECRET');if(strlen($secret)<32)fail('ตั้งค่า APP_SECRET ก่อนใช้งาน',500);
 $key=hash_hmac('sha256',$scope.'|'.client_ip(),$secret);$db=db();
 if(random_int(1,100)===1)$db->exec('DELETE FROM request_limits WHERE occurred_at < NOW() - INTERVAL 1 DAY');
 $q=$db->prepare("SELECT COUNT(*) FROM request_limits WHERE scope=? AND key_hash=? AND occurred_at > NOW() - INTERVAL {$windowSeconds} SECOND");$q->execute([$scope,$key]);
 if((int)$q->fetchColumn()>=$limit){security_event('rate_limited_'.$scope);header('Retry-After: '.$windowSeconds);fail('ส่งคำขอบ่อยเกินไป กรุณารอสักครู่',429);}
 $q=$db->prepare('INSERT INTO request_limits(scope,key_hash) VALUES(?,?)');$q->execute([$scope,$key]);
}
