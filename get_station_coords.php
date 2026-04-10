<?php
header('Content-Type: application/json');
require_once 'db.php';
$id=isset($_GET['station_id'])?intval($_GET['station_id']):0;
if(!$id){echo json_encode(['error'=>'Invalid']);exit;}
$db=getDB();
$s=$db->prepare("SELECT station_id,station_name,latitude,longitude FROM `Stations` WHERE station_id=?");
$s->bind_param('i',$id);$s->execute();$row=$s->get_result()->fetch_assoc();
$s->close();$db->close();
echo $row?json_encode($row):json_encode(['error'=>'Not found']);
?>
