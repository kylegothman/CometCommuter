<?php
require_once 'db.php';
$db=getDB();
$r=$db->query("SELECT l.line_id,l.line_name,GROUP_CONCAT(s.station_name ORDER BY s.station_name SEPARATOR '||') AS stations FROM `Lines` l JOIN `Line_Stations` ls ON l.line_id=ls.line_id JOIN `Stations` s ON ls.station_id=s.station_id GROUP BY l.line_id ORDER BY l.line_name");
$lines=[];while($row=$r->fetch_assoc())$lines[]=$row;
$db->close();
$dots=['Red Line'=>'#E53935','Blue Line'=>'#1E88E5','Green Line'=>'#43A047','Orange Line'=>'#FB8C00','Silver Line BRT'=>'#757575'];
$utd=['UT Dallas / Synergy Park','CityLine/Bush'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Comet Commuter — Lines</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
  <a href="index.php" class="brand"><strong>Comet</strong>Commuter</a>
  <ul>
    <li><a href="index.php">Dashboard</a></li>
    <li><a href="lines.php" class="active">Lines</a></li>
    <li><a href="stations.php">Stations</a></li>
    <li><a href="admin_login.php">Admin</a></li>
  </ul>
</nav>
<div class="page-hdr">
  <h1>DART Transit Lines</h1>
  <p><?=count($lines)?> active lines serving the UT Dallas corridor. Click a line to go to the dashboard and start tracking.</p>
</div>
<div class="container">
  <div class="lines-grid">
    <?php foreach($lines as $line):
      $dot=$dots[$line['line_name']]??'#9E9E9E';
      $stations=$line['stations']?explode('||',$line['stations']):[];
    ?>
    <div class="line-card">
      <div class="line-card-top" style="border-top:3px solid <?=$dot?>;">
        <div class="line-dot" style="background:<?=$dot?>;"></div>
        <div>
          <strong><?=htmlspecialchars($line['line_name'])?></strong>
          <small><?=count($stations)?> stations</small>
        </div>
      </div>
      <ul class="stop-list">
        <?php foreach($stations as $sn): $hi=in_array(trim($sn),$utd); ?>
        <li class="<?=$hi?'highlight':''?>"><?=htmlspecialchars(trim($sn))?><?=$hi?' — UT Dallas':''?></li>
        <?php endforeach; ?>
      </ul>
      <!-- Links to dashboard with line_id pre-selected via JS -->
      <a href="index.php?track_line=<?=$line['line_id']?>" class="btn-track-line">
        Track a stop on <?=htmlspecialchars($line['line_name'])?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<footer>Comet Commuter &mdash; UT Dallas DART Alert System &nbsp;&middot;&nbsp; Data Dogs &nbsp;&middot;&nbsp; SE 4347.005</footer>
</body>
</html>
