<?php
session_start();
if(empty($_SESSION['admin_logged_in'])){header('Location: admin_login.php');exit;}
require_once 'db.php';
$db=getDB();
$msg=''; $msg_type='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??'';
  if($a==='insert_station'){
    $n=trim($_POST['station_name']??'');$lat=floatval($_POST['latitude']??0);$lng=floatval($_POST['longitude']??0);
    if($n&&$lat&&$lng){
      $s=$db->prepare("INSERT INTO `Stations`(station_name,latitude,longitude)VALUES(?,?,?)");
      $s->bind_param('sdd',$n,$lat,$lng);
      $s->execute()?$msg="Station '$n' added successfully.":($msg='Error: '.$s->error and $msg_type='error');
      $s->close();
    } else {$msg='All three fields are required.';$msg_type='error';}
  }
  elseif($a==='delete_station'){
    $id=intval($_POST['station_id']??0);
    if($id){$s=$db->prepare("DELETE FROM `Stations` WHERE station_id=?");$s->bind_param('i',$id);$s->execute()?$msg='Station deleted (and related alerts/links removed via CASCADE).':($msg='Error: '.$s->error and $msg_type='error');$s->close();}
  }
  elseif($a==='insert_line'){
    $n=trim($_POST['line_name']??'');
    if($n){$s=$db->prepare("INSERT INTO `Lines`(line_name)VALUES(?)");$s->bind_param('s',$n);$s->execute()?$msg="Line '$n' added successfully.":($msg='Error: '.$s->error and $msg_type='error');$s->close();}
    else{$msg='Line name is required.';$msg_type='error';}
  }
  elseif($a==='delete_line'){
    $id=intval($_POST['line_id']??0);
    if($id){$s=$db->prepare("DELETE FROM `Lines` WHERE line_id=?");$s->bind_param('i',$id);$s->execute()?$msg='Line deleted (all station links removed via CASCADE).':($msg='Error: '.$s->error and $msg_type='error');$s->close();}
  }
  elseif($a==='insert_line_station'){
    $lid=intval($_POST['line_id']??0);$sid=intval($_POST['station_id']??0);
    if($lid&&$sid){$s=$db->prepare("INSERT IGNORE INTO `Line_Stations`(line_id,station_id)VALUES(?,?)");$s->bind_param('ii',$lid,$sid);$s->execute()?$msg='Station linked to line successfully.':($msg='Error: '.$s->error and $msg_type='error');$s->close();}
    else{$msg='Select both a line and a station.';$msg_type='error';}
  }
  elseif($a==='delete_line_station'){
    $lid=intval($_POST['line_id']??0);$sid=intval($_POST['station_id']??0);
    if($lid&&$sid){$s=$db->prepare("DELETE FROM `Line_Stations` WHERE line_id=? AND station_id=?");$s->bind_param('ii',$lid,$sid);$s->execute()?$msg='Link removed successfully.':($msg='Error: '.$s->error and $msg_type='error');$s->close();}
  }
  elseif($a==='delete_alert'){
    $id=intval($_POST['alert_id']??0);
    if($id){$s=$db->prepare("DELETE FROM `Alerts` WHERE alert_id=?");$s->bind_param('i',$id);$s->execute()?$msg='Alert deleted.':($msg='Error: '.$s->error and $msg_type='error');$s->close();}
  }
}

// Fetch fresh data after any POST
$sr=$db->query("SELECT * FROM `Stations` ORDER BY station_name");
$stations=[];while($r=$sr->fetch_assoc())$stations[]=$r;

$lr=$db->query("SELECT l.*,COUNT(ls.station_id) AS cnt FROM `Lines` l LEFT JOIN `Line_Stations` ls ON l.line_id=ls.line_id GROUP BY l.line_id ORDER BY l.line_name");
$lines=[];while($r=$lr->fetch_assoc())$lines[]=$r;

$lsr=$db->query("SELECT ls.*,l.line_name,s.station_name FROM `Line_Stations` ls JOIN `Lines` l ON ls.line_id=l.line_id JOIN `Stations` s ON ls.station_id=s.station_id ORDER BY l.line_name,s.station_name");
$ls=[];while($r=$lsr->fetch_assoc())$ls[]=$r;

$ar=$db->query("SELECT a.*,s.station_name FROM `Alerts` a JOIN `Stations` s ON a.station_id=s.station_id ORDER BY a.timestamp_created DESC");
$alerts=[];while($r=$ar->fetch_assoc())$alerts[]=$r;
$db->close();

// Which section to jump to after action
$jump = $_POST['section'] ?? 'stations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Comet Commuter — Admin</title>
<link rel="stylesheet" href="style.css">
<style>
  .admin-layout { display:flex; min-height:calc(100vh - 60px); }
  .sidebar { width:220px; background:var(--white); border-right:1px solid var(--border); padding:22px 0; flex-shrink:0; position:sticky; top:60px; height:calc(100vh - 60px); overflow-y:auto; }
  .sb-section { padding:14px 20px 5px; font-size:9px; font-weight:700; color:#C0B8AD; text-transform:uppercase; letter-spacing:0.12em; }
  .sb-link { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; color:var(--text-muted); text-decoration:none; font-size:13px; font-weight:500; border-left:2px solid transparent; transition:all 0.12s; cursor:pointer; }
  .sb-link:hover { background:var(--cream); color:var(--text); border-left-color:var(--border); }
  .sb-link.active { color:var(--orange); border-left-color:var(--orange); background:rgba(199,91,18,0.04); font-weight:600; }
  .sb-count { background:var(--cream-dark); color:var(--text-muted); font-size:10px; padding:2px 8px; border-radius:100px; font-weight:600; min-width:24px; text-align:center; }
  .admin-main { flex:1; padding:32px 40px; background:var(--cream); overflow-x:auto; }
  .section { scroll-margin-top:80px; }
  .section-hdr { font-family:'Playfair Display',serif; font-size:1.3rem; font-weight:800; color:var(--text); letter-spacing:-0.02em; margin-bottom:18px; padding-bottom:14px; border-bottom:2px solid var(--border); display:flex; align-items:center; gap:12px; }
  .section-hdr .count-pill { font-family:'Inter',sans-serif; font-size:12px; font-weight:600; background:var(--cream-dark); color:var(--text-muted); padding:3px 10px; border-radius:100px; }
  .form-section { background:var(--white); border:1px solid var(--border); border-radius:var(--r); padding:24px; margin-bottom:16px; box-shadow:var(--shadow-sm); }
  .form-section-label { font-size:10px; font-weight:700; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.09em; margin-bottom:16px; }
  .tbl-section { background:var(--white); border:1px solid var(--border); border-radius:var(--r); overflow:hidden; margin-bottom:40px; box-shadow:var(--shadow-sm); }
  .tbl-hdr { padding:13px 18px; border-bottom:1px solid var(--border-soft); display:flex; justify-content:space-between; align-items:center; font-size:10px; font-weight:700; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.09em; }
  .empty-row td { text-align:center; color:var(--text-faint); padding:28px; font-size:13px; }
</style>
</head>
<body>
<nav>
  <a href="index.php" class="brand"><strong>Comet</strong>Commuter</a>
  <div style="display:flex;align-items:center;gap:14px;">
    <span class="admin-nav-tag">Admin Mode</span>
    <a href="admin_logout.php" style="color:rgba(255,255,255,0.55);text-decoration:none;font-size:13px;font-weight:500;">Sign Out</a>
  </div>
</nav>

<div class="admin-layout">
  <!-- SIDEBAR — anchor links scroll to sections -->
  <aside class="sidebar">
    <div class="sb-section">Database</div>
    <a href="#stations" class="sb-link active" onclick="setActive(this)">Stations <span class="sb-count"><?=count($stations)?></span></a>
    <a href="#lines"    class="sb-link"        onclick="setActive(this)">Lines <span class="sb-count"><?=count($lines)?></span></a>
    <a href="#ls"       class="sb-link"        onclick="setActive(this)">Line Stations <span class="sb-count"><?=count($ls)?></span></a>
    <div class="sb-section">Sessions</div>
    <a href="#alerts"   class="sb-link"        onclick="setActive(this)">Alerts <span class="sb-count"><?=count($alerts)?></span></a>
  </aside>

  <div class="admin-main">
    <?php if($msg): ?>
      <div class="msg-<?=$msg_type?>" style="margin-bottom:24px;"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <!-- ══ STATIONS ══ -->
    <div class="section" id="stations">
      <div class="section-hdr">Stations <span class="count-pill"><?=count($stations)?> records</span></div>

      <div class="form-section">
        <div class="form-section-label">Add New Station</div>
        <form method="POST" action="admin_panel.php#stations">
          <input type="hidden" name="action" value="insert_station">
          <input type="hidden" name="section" value="stations">
          <div class="form-3">
            <div class="field">
              <label>station_name</label>
              <input type="text" name="station_name" placeholder="e.g. UT Dallas / Synergy Park" required>
              <small>VARCHAR(100) UNIQUE NOT NULL</small>
            </div>
            <div class="field">
              <label>latitude</label>
              <input type="number" name="latitude" step="0.000001" placeholder="32.988600" required>
              <small>DECIMAL(9,6) NOT NULL</small>
            </div>
            <div class="field">
              <label>longitude</label>
              <input type="number" name="longitude" step="0.000001" placeholder="-96.751800" required>
              <small>DECIMAL(9,6) NOT NULL</small>
            </div>
          </div>
          <div class="btn-row"><button type="submit" class="btn-primary">Add Station</button></div>
        </form>
      </div>

      <div class="tbl-section">
        <div class="tbl-hdr"><span>All Stations</span><span><?=count($stations)?> records</span></div>
        <table>
          <thead><tr><th>ID</th><th>Station Name</th><th>Latitude</th><th>Longitude</th><th>Delete</th></tr></thead>
          <tbody>
            <?php if(empty($stations)): ?>
              <tr class="empty-row"><td colspan="5">No stations found.</td></tr>
            <?php else: foreach($stations as $s): ?>
            <tr>
              <td style="color:var(--text-faint);font-size:11px;"><?=$s['station_id']?></td>
              <td style="font-weight:600;"><?=htmlspecialchars($s['station_name'])?></td>
              <td class="td-mono"><?=$s['latitude']?></td>
              <td class="td-mono"><?=$s['longitude']?></td>
              <td>
                <form method="POST" action="admin_panel.php#stations" onsubmit="return confirm('Delete \'<?=addslashes($s['station_name'])?>\' and all its alerts and line links?')">
                  <input type="hidden" name="action" value="delete_station">
                  <input type="hidden" name="station_id" value="<?=$s['station_id']?>">
                  <input type="hidden" name="section" value="stations">
                  <button type="submit" class="btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ LINES ══ -->
    <div class="section" id="lines">
      <div class="section-hdr">Lines <span class="count-pill"><?=count($lines)?> records</span></div>

      <div class="form-section">
        <div class="form-section-label">Add New Line</div>
        <form method="POST" action="admin_panel.php#lines">
          <input type="hidden" name="action" value="insert_line">
          <input type="hidden" name="section" value="lines">
          <div style="max-width:420px;">
            <div class="field">
              <label>line_name</label>
              <input type="text" name="line_name" placeholder="e.g. Purple Line" required>
              <small>VARCHAR(100) UNIQUE NOT NULL</small>
            </div>
          </div>
          <div class="btn-row"><button type="submit" class="btn-primary">Add Line</button></div>
        </form>
      </div>

      <div class="tbl-section">
        <div class="tbl-hdr"><span>All Lines</span><span><?=count($lines)?> records</span></div>
        <table>
          <thead><tr><th>ID</th><th>Line Name</th><th>Linked Stations</th><th>Delete</th></tr></thead>
          <tbody>
            <?php if(empty($lines)): ?>
              <tr class="empty-row"><td colspan="4">No lines found.</td></tr>
            <?php else: foreach($lines as $l): ?>
            <tr>
              <td style="color:var(--text-faint);font-size:11px;"><?=$l['line_id']?></td>
              <td style="font-weight:600;"><?=htmlspecialchars($l['line_name'])?></td>
              <td><?=$l['cnt']?> station<?=$l['cnt']!=1?'s':''?></td>
              <td>
                <form method="POST" action="admin_panel.php#lines" onsubmit="return confirm('Delete \'<?=addslashes($l['line_name'])?>\' and all its station links?')">
                  <input type="hidden" name="action" value="delete_line">
                  <input type="hidden" name="line_id" value="<?=$l['line_id']?>">
                  <input type="hidden" name="section" value="lines">
                  <button type="submit" class="btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ LINE_STATIONS ══ -->
    <div class="section" id="ls">
      <div class="section-hdr">Line Stations <span class="count-pill"><?=count($ls)?> records</span></div>

      <div class="form-section">
        <div class="form-section-label">Link a Station to a Line</div>
        <form method="POST" action="admin_panel.php#ls">
          <input type="hidden" name="action" value="insert_line_station">
          <input type="hidden" name="section" value="ls">
          <div class="form-2" style="max-width:620px;">
            <div class="field">
              <label>Line</label>
              <select name="line_id" required>
                <option value="">Select line</option>
                <?php foreach($lines as $l): ?>
                  <option value="<?=$l['line_id']?>"><?=$l['line_id']?> — <?=htmlspecialchars($l['line_name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Station</label>
              <select name="station_id" required>
                <option value="">Select station</option>
                <?php foreach($stations as $s): ?>
                  <option value="<?=$s['station_id']?>"><?=$s['station_id']?> — <?=htmlspecialchars($s['station_name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="btn-row"><button type="submit" class="btn-primary">Add Link</button></div>
        </form>
      </div>

      <div class="tbl-section">
        <div class="tbl-hdr"><span>All Line–Station Links</span><span><?=count($ls)?> records</span></div>
        <table>
          <thead><tr><th>Line</th><th>Station</th><th>Remove</th></tr></thead>
          <tbody>
            <?php if(empty($ls)): ?>
              <tr class="empty-row"><td colspan="3">No links found.</td></tr>
            <?php else: foreach($ls as $row): ?>
            <tr>
              <td style="font-weight:500;"><?=htmlspecialchars($row['line_name'])?></td>
              <td><?=htmlspecialchars($row['station_name'])?></td>
              <td>
                <form method="POST" action="admin_panel.php#ls" onsubmit="return confirm('Remove this link?')">
                  <input type="hidden" name="action" value="delete_line_station">
                  <input type="hidden" name="line_id" value="<?=$row['line_id']?>">
                  <input type="hidden" name="station_id" value="<?=$row['station_id']?>">
                  <input type="hidden" name="section" value="ls">
                  <button type="submit" class="btn-danger">Remove</button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ ALERTS ══ -->
    <div class="section" id="alerts">
      <div class="section-hdr">Alerts <span class="count-pill"><?=count($alerts)?> records</span></div>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">Alerts are created by users on the dashboard. Admins can delete stale or cancelled sessions here.</p>

      <div class="tbl-section">
        <div class="tbl-hdr"><span>All Alert Sessions</span><span><?=count($alerts)?> records</span></div>
        <table>
          <thead><tr><th>ID</th><th>Station</th><th>Radius</th><th>Status</th><th>Created</th><th>Delete</th></tr></thead>
          <tbody>
            <?php if(empty($alerts)): ?>
              <tr class="empty-row"><td colspan="6">No alerts found.</td></tr>
            <?php else: foreach($alerts as $a): ?>
            <tr>
              <td style="color:var(--text-faint);font-size:11px;"><?=$a['alert_id']?></td>
              <td style="font-weight:600;"><?=htmlspecialchars($a['station_name'])?></td>
              <td><?=$a['radius']?> m</td>
              <td><span class="badge b-<?=$a['status']?>"><?=$a['status']?></span></td>
              <td style="color:var(--text-faint);font-size:11px;"><?=date('M j, Y H:i',strtotime($a['timestamp_created']))?></td>
              <td>
                <form method="POST" action="admin_panel.php#alerts" onsubmit="return confirm('Delete alert #<?=$a['alert_id']?>?')">
                  <input type="hidden" name="action" value="delete_alert">
                  <input type="hidden" name="alert_id" value="<?=$a['alert_id']?>">
                  <input type="hidden" name="section" value="alerts">
                  <button type="submit" class="btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /admin-main -->
</div><!-- /admin-layout -->

<footer>Comet Commuter Admin &mdash; Data Dogs &mdash; SE 4347.005</footer>

<script>
// Sidebar active state
function setActive(el){
  document.querySelectorAll('.sb-link').forEach(l=>l.classList.remove('active'));
  el.classList.add('active');
}

// Highlight active section on scroll
const sections = ['stations','lines','ls','alerts'];
window.addEventListener('scroll',function(){
  let current='stations';
  sections.forEach(id=>{
    const el=document.getElementById(id);
    if(el && el.getBoundingClientRect().top <= 100) current=id;
  });
  document.querySelectorAll('.sb-link').forEach(l=>{
    l.classList.toggle('active', l.getAttribute('href')==='#'+current);
  });
});

// Jump to correct section after form submit
<?php if($msg): ?>
window.addEventListener('load',function(){
  const section = '<?=htmlspecialchars($jump)?>';
  const el = document.getElementById(section);
  if(el) el.scrollIntoView({behavior:'smooth',block:'start'});
});
<?php endif; ?>
</script>
</body>
</html>
