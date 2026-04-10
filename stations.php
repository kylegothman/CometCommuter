<?php
require_once 'db.php';
$db=getDB();
$lines_r=$db->query("SELECT line_id,line_name FROM `Lines` ORDER BY line_name");
$lines=[];while($r=$lines_r->fetch_assoc())$lines[]=$r;
$fl=isset($_GET['line_id'])?intval($_GET['line_id']):0;
$fq=isset($_GET['q'])?trim($_GET['q']):'';
$sql="SELECT s.station_id,s.station_name,s.latitude,s.longitude,GROUP_CONCAT(l.line_name ORDER BY l.line_name SEPARATOR '||') AS served, MIN(ls.line_id) AS first_line_id FROM `Stations` s LEFT JOIN `Line_Stations` ls ON s.station_id=ls.station_id LEFT JOIN `Lines` l ON ls.line_id=l.line_id WHERE 1=1";
$params=[];$types='';
if($fl>0){$sql.=" AND ls.line_id=?";$params[]=$fl;$types.='i';}
if($fq!==''){$sql.=" AND s.station_name LIKE ?";$like="%$fq%";$params[]=$like;$types.='s';}
$sql.=" GROUP BY s.station_id ORDER BY s.station_name";
$stmt=$db->prepare($sql);if($params)$stmt->bind_param($types,...$params);
$stmt->execute();$res=$stmt->get_result();$stations=[];while($r=$res->fetch_assoc())$stations[]=$r;
$stmt->close();$db->close();
$lcls=['Red Line'=>'tag-red','Blue Line'=>'tag-blue','Green Line'=>'tag-green','Orange Line'=>'tag-orange','Silver Line BRT'=>'tag-silver'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Comet Commuter — Stations</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
  <a href="index.php" class="brand"><strong>Comet</strong>Commuter</a>
  <ul>
    <li><a href="index.php">Dashboard</a></li>
    <li><a href="lines.php">Lines</a></li>
    <li><a href="stations.php" class="active">Stations</a></li>
    <li><a href="admin_login.php">Admin</a></li>
  </ul>
</nav>
<div class="page-hdr">
  <h1>DART Stations</h1>
  <p>All stations in the network. Click "Set Alert" to go to the dashboard with that station pre-selected.</p>
</div>
<div class="container">
  <div class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <form method="GET" action="stations.php">
        <div class="form-filt">
          <div class="field">
            <label>Filter by Line</label>
            <select name="line_id">
              <option value="">All Lines</option>
              <?php foreach($lines as $l): ?>
                <option value="<?=$l['line_id']?>" <?=$fl==$l['line_id']?'selected':''?>><?=htmlspecialchars($l['line_name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Search Name</label>
            <input type="text" name="q" value="<?=htmlspecialchars($fq)?>" placeholder="e.g. UT Dallas, Plano...">
          </div>
          <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" class="btn-primary">Search</button>
            <a href="stations.php" class="btn-secondary">Clear</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div style="font-size:12px;color:var(--text-faint);font-weight:500;margin-bottom:12px;">
    <?=count($stations)?> station<?=count($stations)!=1?'s':''?> found
  </div>

  <div class="card" style="padding:0;">
    <table>
      <thead>
        <tr><th>ID</th><th>Station Name</th><th>Lines Served</th><th>Latitude</th><th>Longitude</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if(empty($stations)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-faint);padding:24px;">No stations found. Try clearing the filters.</td></tr>
        <?php else: foreach($stations as $s): ?>
        <tr>
          <td style="color:var(--text-faint);font-size:11px;"><?=$s['station_id']?></td>
          <td style="font-weight:600;"><?=htmlspecialchars($s['station_name'])?></td>
          <td>
            <?php if($s['served']): foreach(explode('||',$s['served']) as $ln): $cls=$lcls[trim($ln)]??'tag-silver'; ?>
              <span class="line-tag <?=$cls?>"><?=htmlspecialchars(trim($ln))?></span>
            <?php endforeach; else: echo '<span style="color:var(--text-faint)">—</span>'; endif; ?>
          </td>
          <td class="td-mono"><?=$s['latitude']?></td>
          <td class="td-mono"><?=$s['longitude']?></td>
          <td>
            <!--
              Passes both the station_id AND its first line_id to the dashboard
              so the line dropdown can be pre-selected and stations filtered automatically
            -->
            <a href="index.php?preset_station=<?=$s['station_id']?>&track_line=<?=$s['first_line_id']?>"
               class="btn-sm" style="text-decoration:none;">Set Alert</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<footer>Comet Commuter &mdash; UT Dallas DART Alert System &nbsp;&middot;&nbsp; Data Dogs &nbsp;&middot;&nbsp; SE 4347.005</footer>
</body>
</html>
