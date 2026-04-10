<?php
header('Content-Type: application/json');
require_once 'db.php';
$id=isset($_GET['line_id'])?intval($_GET['line_id']):0;
if(!$id){echo json_encode([]);exit;}
$db=getDB();
$s=$db->prepare("SELECT s.station_id,s.station_name FROM `Stations` s JOIN `Line_Stations` ls ON s.station_id=ls.station_id WHERE ls.line_id=? ORDER BY s.station_name");
$s->bind_param('i',$id);$s->execute();$r=$s->get_result();
$out=[];while($row=$r->fetch_assoc())$out[]=$row;
$s->close();$db->close();
echo json_encode($out);
?>
