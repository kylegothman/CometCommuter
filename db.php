<?php
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','comet_commuter');
function getDB(){
    $c=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
    if($c->connect_error){http_response_code(500);die(json_encode(['error'=>'DB failed: '.$c->connect_error]));}
    $c->set_charset('utf8mb4');
    return $c;
}
?>
