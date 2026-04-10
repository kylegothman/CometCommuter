<?php
require_once 'db.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: index.php');exit;}
$sid=isset($_POST['station_id'])?intval($_POST['station_id']):0;
$radius=isset($_POST['radius'])?floatval($_POST['radius']):200.00;
if(!$sid){header('Location: index.php?error=invalid_station');exit;}
$db=getDB();
$s=$db->prepare("INSERT INTO `Alerts`(station_id,radius,status,timestamp_created)VALUES(?,?,'active',NOW())");
$s->bind_param('id',$sid,$radius);
if($s->execute()){$id=$db->insert_id;$s->close();$db->close();header("Location: index.php?alert_id=$id&success=1");}
else{$s->close();$db->close();header('Location: index.php?error=insert_failed');}
exit;
?>
