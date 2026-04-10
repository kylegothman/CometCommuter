<?php
require_once 'db.php';
$db = getDB();
$lines_r = $db->query("SELECT line_id, line_name FROM `Lines` ORDER BY line_name");
$lines = []; while($r=$lines_r->fetch_assoc()) $lines[]=$r;
$alerts_r = $db->query("SELECT a.alert_id, s.station_name, s.latitude, s.longitude, a.radius, a.status, a.timestamp_created FROM `Alerts` a JOIN `Stations` s ON a.station_id=s.station_id ORDER BY a.timestamp_created DESC LIMIT 5");
$recent = []; while($r=$alerts_r->fetch_assoc()) $recent[]=$r;
$success_id = isset($_GET['alert_id']) ? intval($_GET['alert_id']) : 0;
$preset_sid = isset($_GET['preset_station']) ? intval($_GET['preset_station']) : 0;
$track_line = isset($_GET['track_line']) ? intval($_GET['track_line']) : 0;

// If alert just created, load its station data for auto-tracking
$auto_station = null;
if ($success_id) {
    $as = $db->prepare("SELECT s.station_id, s.station_name, s.latitude, s.longitude, a.radius FROM `Alerts` a JOIN `Stations` s ON a.station_id=s.station_id WHERE a.alert_id=?");
    $as->bind_param('i', $success_id);
    $as->execute();
    $auto_station = $as->get_result()->fetch_assoc();
    $as->close();
}
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Comet Commuter — Dashboard</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="style.css">
<style>
  .tracking-panel { display:none; background:#fff; border:1px solid var(--border); border-radius:var(--r); padding:20px 24px; margin-bottom:22px; border-left:4px solid var(--orange); box-shadow:var(--shadow-sm); }
  .tracking-panel.visible { display:block; }
  .tracking-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
  .tracking-title { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:800; color:var(--text); }
  .tracking-station { font-size:13px; color:var(--text-muted); margin-top:2px; }
  .dist-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
  .dist-box { background:#F5F3EF; border:1px solid var(--border); border-radius:var(--r-sm); padding:16px; text-align:center; }
  .dist-val { font-family:'Playfair Display',serif; font-size:2rem; font-weight:800; color:var(--orange); letter-spacing:-0.04em; line-height:1; }
  .dist-val.in-range { color:#2E7D32; }
  .dist-lbl { font-size:9px; font-weight:700; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.09em; margin-top:5px; }
  .dist-sub { font-size:10px; color:var(--text-faint); margin-top:3px; }
  .alert-banner { background:#E6F4EA; border:1.5px solid #A5D6A7; border-radius:var(--r-sm); padding:14px 20px; margin-bottom:20px; display:none; text-align:center; font-weight:600; color:#2E7D32; font-size:14px; }
  .gps-status { font-size:11px; color:var(--text-faint); display:flex; align-items:center; gap:6px; }
  .gps-dot { width:7px; height:7px; border-radius:50%; background:var(--text-faint); flex-shrink:0; }
  .gps-dot.active { background:#43A047; animation:blink 1.5s infinite; }
  @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }
  #map { width:100%; height:320px; }
  .btn-stop { background:transparent; color:#C62828; border:1.5px solid #FFCDD2; border-radius:var(--r-sm); padding:8px 18px; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; }
  .btn-stop:hover { background:#FFEBEE; }
  /* Hero redesign */
  .hero { display:grid; grid-template-columns:1fr 1fr; min-height:440px; }
  .hero-left { padding:56px 48px 48px; display:flex; flex-direction:column; justify-content:center; overflow:hidden; }
  .hero-eyebrow { display:inline-flex; align-items:center; gap:8px; margin-bottom:24px; width:fit-content; white-space:nowrap; }
  .ey-dot { width:6px; height:6px; background:#E8701A; border-radius:50%; flex-shrink:0; animation:pulse-ey 2s ease-in-out infinite; }
  @keyframes pulse-ey { 0%,100%{opacity:1;transform:scale(1);}50%{opacity:.5;transform:scale(1.4);} }
  .hero-eyebrow span:last-child { font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,0.5); white-space:nowrap; }
  .hero h1 { font-family:'Playfair Display',serif; font-size:clamp(2.4rem,4vw,3.4rem); font-weight:900; color:#fff; line-height:1.05; letter-spacing:-.03em; margin-bottom:18px; }
  .hero h1 em { color:#E8701A; font-style:italic; }
  .hero-sub { color:rgba(255,255,255,0.5); font-size:14px; line-height:1.7; max-width:380px; margin-bottom:32px; }
  .btn-ghost { background:transparent; color:rgba(255,255,255,0.75); border:1.5px solid rgba(255,255,255,0.25); padding:13px 22px; border-radius:8px; font-size:14px; font-weight:500; font-family:inherit; cursor:pointer; transition:all .15s; width:fit-content; display:inline-block; }
  .btn-ghost:hover { color:#fff; border-color:rgba(255,255,255,0.5); background:rgba(255,255,255,0.05); }
  .hero-right { position:relative; overflow:hidden; }
  .hero-map { width:100%; height:100%; min-height:440px; background:#1a2e1b; position:relative; }
  .hero-map svg { position:absolute; inset:0; width:100%; height:100%; }
  .map-legend { position:absolute; top:20px; left:20px; background:rgba(255,255,255,0.97); border-radius:10px; padding:14px 16px; min-width:160px; }
  .map-legend strong { font-size:11px; font-weight:700; color:#1a1a1a; display:block; margin-bottom:8px; }
  .legend-row { display:flex; align-items:center; gap:7px; color:#5a5a5a; font-size:10px; margin-bottom:4px; }
  .legend-row:last-child { margin-bottom:0; }
  .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
  .hero-stats { position:absolute; bottom:20px; left:20px; right:20px; display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .hero-stat { background:rgba(21,71,52,0.94); border:1px solid rgba(255,255,255,0.12); border-radius:10px; padding:16px 12px; text-align:center; transition:background .2s; }
  .hero-stat:hover { background:rgba(21,71,52,1); }
  .hs-num { font-family:'Playfair Display',serif; font-size:1.9rem; font-weight:900; color:#fff; letter-spacing:-.04em; line-height:1; }
  .hs-lbl { font-size:9px; font-weight:600; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:.09em; margin-top:5px; }
</style>
</head>
<body>

<nav>
  <a href="index.php" class="brand"><strong>Comet</strong>Commuter</a>
  <ul>
    <li><a href="index.php" class="active">Dashboard</a></li>
    <li><a href="lines.php">Lines</a></li>
    <li><a href="stations.php">Stations</a></li>
    <li><a href="admin_login.php">Admin</a></li>
  </ul>
</nav>

<!-- HERO -->
<div style="background:#154734;overflow:hidden;">
  <div class="hero">
    <div class="hero-left">
      <div class="hero-eyebrow">
        <span class="ey-dot"></span>
        <span style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,0.5);white-space:nowrap;">UT Dallas · DART Network</span>
      </div>
      <h1>Never miss<br>your <em>stop</em><br>again.</h1>
      <p class="hero-sub">Real-time proximity alerts for UT Dallas students and faculty using the DART transit system.</p>
      <a href="stations.php" class="btn-ghost" style="text-decoration:none;display:inline-block;">Browse Stations</a>
    </div>
    <div class="hero-right">
      <div class="hero-map">
        <svg width="100%" height="100%" viewBox="0 0 520 440" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
          <rect fill="#1a2e1b" width="520" height="440"/>
          <line x1="0" y1="220" x2="520" y2="220" stroke="#243825" stroke-width="16"/>
          <line x1="260" y1="0" x2="260" y2="440" stroke="#243825" stroke-width="12"/>
          <line x1="0" y1="110" x2="520" y2="110" stroke="#1e3020" stroke-width="5"/>
          <line x1="0" y1="330" x2="520" y2="330" stroke="#1e3020" stroke-width="5"/>
          <line x1="130" y1="0" x2="130" y2="440" stroke="#1e3020" stroke-width="4"/>
          <line x1="390" y1="0" x2="390" y2="440" stroke="#1e3020" stroke-width="4"/>
          <line x1="0" y1="80" x2="520" y2="360" stroke="#1e3020" stroke-width="3"/>
          <circle cx="290" cy="185" r="68" fill="rgba(199,91,18,0.07)" stroke="#C75B12" stroke-width="1.5" stroke-dasharray="5,4"/>
          <circle cx="290" cy="185" r="34" fill="rgba(199,91,18,0.05)" stroke="#C75B12" stroke-width="1" stroke-dasharray="3,3" opacity=".6"/>
          <circle cx="290" cy="185" r="8" fill="#C75B12"/>
          <circle cx="290" cy="185" r="5" fill="#fff"/>
          <circle cx="195" cy="210" r="6" fill="#5a9ee8"/>
          <circle cx="195" cy="210" r="4" fill="#fff"/>
          <circle cx="130" cy="110" r="5" fill="#3a5a3a"/>
          <circle cx="390" cy="110" r="5" fill="#3a5a3a"/>
          <circle cx="130" cy="330" r="5" fill="#3a5a3a"/>
          <circle cx="390" cy="330" r="5" fill="#3a5a3a"/>
          <circle cx="260" cy="110" r="5" fill="#3a5a3a"/>
          <circle cx="260" cy="330" r="5" fill="#3a5a3a"/>
        </svg>
        <!-- Legend card -->
        <div class="map-legend">
          <strong>Live Tracking</strong>
          <div class="legend-row"><div class="legend-dot" style="background:#C75B12;"></div>Destination station</div>
          <div class="legend-row"><div class="legend-dot" style="background:#5a9ee8;"></div>Your location</div>
          <div class="legend-row"><div class="legend-dot" style="background:rgba(199,91,18,0.25);border:1px dashed #C75B12;"></div>Alert radius</div>
        </div>
        <!-- Stats pills -->
        <div class="hero-stats">
          <div class="hero-stat">
            <div class="hs-num" id="hs1">0</div>
            <div class="hs-lbl">DART Stations</div>
          </div>
          <div class="hero-stat">
            <div class="hs-num" id="hs2">0</div>
            <div class="hs-lbl">Transit Lines</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- STATS BAR removed — stats now live on the hero map -->

<div class="container">

  <!-- ALERT NOTIFICATION BANNER -->
  <div class="alert-banner" id="alertBanner">
    You are within range of your destination — prepare to get off!
  </div>

  <!-- LIVE TRACKING PANEL (shown after alert created or station selected) -->
  <div class="tracking-panel <?= $auto_station ? 'visible' : '' ?>" id="trackingPanel">
    <div class="tracking-top">
      <div>
        <div class="tracking-title">Live Tracking</div>
        <div class="tracking-station" id="trackingStation"><?= $auto_station ? htmlspecialchars($auto_station['station_name']) : '' ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:14px;">
        <div class="gps-status"><div class="gps-dot active" id="gpsDot"></div><span id="gpsLabel">Acquiring GPS...</span></div>
        <button class="btn-stop" onclick="stopTracking()">Stop Tracking</button>
      </div>
    </div>
    <div class="dist-grid">
      <div class="dist-box"><div class="dist-val" id="distVal">—</div><div class="dist-lbl">Distance (miles)</div><div class="dist-sub" id="distSub"></div></div>
      <div class="dist-box"><div class="dist-val" id="etaVal">—</div><div class="dist-lbl">ETA (min)</div><div class="dist-sub" id="etaSub"></div></div>
      <div class="dist-box"><div class="dist-val" id="threshVal"><?= $auto_station ? number_format($auto_station['radius'] / 1609.34, 2) : '—' ?></div><div class="dist-lbl">Alert Radius (miles)</div></div>
    </div>
  </div>

  <!-- ALERT SETUP FORM -->
  <div class="setup-card" id="setup">
    <div class="setup-top">
      <h3>Set Up Proximity Alert</h3>
      <span class="badge b-idle" id="trackingBadge">No active alert</span>
    </div>
    <div class="setup-body">
      <form action="process_alert.php" method="POST" id="alertForm">
        <div class="form-3">
          <div class="field">
            <label for="line_id">Transit Line</label>
            <select id="line_id" name="line_id" required>
              <option value="">Select a line</option>
              <?php foreach($lines as $l): ?>
                <option value="<?=$l['line_id']?>"><?=htmlspecialchars($l['line_name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="station_id">Destination Station</label>
            <select id="station_id" name="station_id" required>
              <option value="">Choose line first</option>
            </select>
          </div>
          <div class="field">
            <label for="radius">Alert Radius</label>
            <select id="radius" name="radius">
              <option value="1609">1 mile</option>
              <option value="8047" selected>5 miles (default)</option>
              <option value="16093">10 miles</option>
              <option value="40234">25 miles</option>
              <option value="80467">50 miles</option>
              <option value="160934">100 miles</option>
            </select>
          </div>
        </div>
        <div class="btn-row">
          <button type="submit" class="btn-primary" id="submitBtn">Save Alert &amp; Start Tracking</button>
          <span style="font-size:12px;color:var(--text-faint);">Saves to database and begins GPS tracking</span>
        </div>
      </form>
    </div>
  </div>

  <!-- MAP + ALERTS TABLE -->
  <div class="two-col">
    <div class="card">
      <div class="card-hdr">
        <h4>Live Map</h4>
        <span style="font-size:11px;color:var(--orange);font-weight:600;" id="mapStatus">Select a station to begin</span>
      </div>
      <div id="map"></div>
    </div>
    <div class="card" style="padding:0;">
      <div class="card-hdr" style="padding:14px 16px;"><h4>Recent Alerts</h4><a href="index.php" style="font-size:11px;color:var(--orange);font-weight:500;text-decoration:none;">Refresh</a></div>
      <table>
        <thead><tr><th>Station</th><th>Radius</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
          <?php if(empty($recent)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-faint);padding:20px;">No alerts yet. Set one up above.</td></tr>
          <?php else: foreach($recent as $a): ?>
          <tr>
            <td style="font-weight:500;"><?=htmlspecialchars($a['station_name'])?></td>
            <td><?=number_format($a['radius']/1609.34, 2)?> mi</td>
            <td><span class="badge b-<?=$a['status']?>"><?=$a['status']?></span></td>
            <td style="color:var(--text-faint);font-size:11px;"><?=date('M j, H:i',strtotime($a['timestamp_created']))?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<footer>Comet Commuter &mdash; UT Dallas DART Alert System &nbsp;&middot;&nbsp; Data Dogs &nbsp;&middot;&nbsp; SE 4347.005</footer>

<script>
// ── MAP SETUP ────────────────────────────────────────────────
const map = L.map('map').setView([32.9886,-96.7518],12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors'}).addTo(map);

let userMarker=null, stationMarker=null, radiusCircle=null;
let watchId=null, currentStation=null, alertFired=false;

// ── AUTO-START if coming back from successful alert ──────────
<?php if($auto_station): ?>
currentStation = {
  lat: <?= floatval($auto_station['latitude']) ?>,
  lng: <?= floatval($auto_station['longitude']) ?>,
  name: <?= json_encode($auto_station['station_name']) ?>,
  radius: <?= intval($auto_station['radius']) ?>
};
placeStationMarker();
startGPS();
document.getElementById('trackingBadge').textContent = 'Tracking active';
document.getElementById('trackingBadge').className = 'badge b-active';
document.getElementById('mapStatus').textContent = 'Tracking active';
<?php endif; ?>

// ── HAVERSINE ────────────────────────────────────────────────
function haversine(a,b,c,d){
  const R=6371000,dL=(c-a)*Math.PI/180,dN=(d-b)*Math.PI/180;
  const e=Math.sin(dL/2)**2+Math.cos(a*Math.PI/180)*Math.cos(c*Math.PI/180)*Math.sin(dN/2)**2;
  return R*2*Math.atan2(Math.sqrt(e),Math.sqrt(1-e));
}

// ── LINE DROPDOWN ────────────────────────────────────────────
document.getElementById('line_id').addEventListener('change',function(){
  const id=this.value, sel=document.getElementById('station_id');
  if(!id){sel.innerHTML='<option value="">Choose line first</option>';return;}
  sel.innerHTML='<option value="">Loading stations...</option>';
  fetch('get_stations.php?line_id='+id)
    .then(r=>r.json())
    .then(stations=>{
      sel.innerHTML='<option value="">Select a station</option>';
      stations.forEach(s=>{
        const o=document.createElement('option');
        o.value=s.station_id; o.textContent=s.station_name;
        sel.appendChild(o);
      });
      <?php if($preset_sid): ?>sel.value='<?=$preset_sid?>';sel.dispatchEvent(new Event('change'));<?php endif; ?>
    })
    .catch(()=>{ sel.innerHTML='<option value="">Error loading stations</option>'; });
});

// ── STATION DROPDOWN — preview on map without saving ─────────
document.getElementById('station_id').addEventListener('change',function(){
  const sid=this.value, radius=parseInt(document.getElementById('radius').value);
  if(!sid) return;
  fetch('get_station_coords.php?station_id='+sid)
    .then(r=>r.json())
    .then(d=>{
      if(d.error) return;
      currentStation={lat:parseFloat(d.latitude),lng:parseFloat(d.longitude),name:d.station_name,radius};
      placeStationMarker();
      const miles=(radius/1609.34).toFixed(2);
      document.getElementById('threshVal').textContent=miles;
      document.getElementById('trackingBadge').textContent='Station selected — click Save Alert to begin';
      document.getElementById('mapStatus').textContent='Preview — not yet tracking';
    });
});

// ── RADIUS CHANGE ─────────────────────────────────────────────
document.getElementById('radius').addEventListener('change',function(){
  const r=parseInt(this.value);
  if(currentStation) currentStation.radius=r;
  if(radiusCircle) radiusCircle.setRadius(r);
  document.getElementById('threshVal').textContent=(r/1609.34).toFixed(2);
  alertFired=false;
});

// ── PLACE STATION MARKER ─────────────────────────────────────
function placeStationMarker(){
  if(!currentStation) return;
  if(stationMarker) map.removeLayer(stationMarker);
  if(radiusCircle)  map.removeLayer(radiusCircle);
  stationMarker = L.marker([currentStation.lat,currentStation.lng],{
    icon:L.divIcon({className:'',
      html:'<div style="background:#C75B12;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3)"></div>',
      iconSize:[16,16]})
  }).addTo(map)
    .bindPopup('<b style="font-family:serif;font-size:13px;">'+currentStation.name+'</b><br><span style="font-size:11px;color:#888;">Destination · '+(currentStation.radius/1609.34).toFixed(2)+' mi radius</span>')
    .openPopup();
  radiusCircle = L.circle([currentStation.lat,currentStation.lng],{
    radius:currentStation.radius,
    color:'#C75B12',fillColor:'#C75B12',fillOpacity:0.08,weight:2,dashArray:'5,4'
  }).addTo(map);
  map.setView([currentStation.lat,currentStation.lng],15);
}

// ── START GPS ────────────────────────────────────────────────
function startGPS(){
  if(!navigator.geolocation){
    document.getElementById('gpsLabel').textContent='GPS not supported';
    return;
  }
  if(watchId) navigator.geolocation.clearWatch(watchId);
  alertFired=false;
  document.getElementById('trackingPanel').classList.add('visible');
  watchId = navigator.geolocation.watchPosition(
    pos=>updatePosition(pos.coords.latitude,pos.coords.longitude),
    err=>{
      document.getElementById('gpsLabel').textContent='GPS unavailable: '+err.message;
      document.getElementById('gpsDot').classList.remove('active');
    },
    {enableHighAccuracy:true,maximumAge:3000,timeout:10000}
  );
}

function stopTracking(){
  if(watchId){ navigator.geolocation.clearWatch(watchId); watchId=null; }
  document.getElementById('trackingPanel').classList.remove('visible');
  document.getElementById('distVal').textContent='—';
  document.getElementById('etaVal').textContent='—';
  document.getElementById('alertBanner').style.display='none';
  document.getElementById('trackingBadge').textContent='No active alert';
  document.getElementById('trackingBadge').className='badge b-idle';
  document.getElementById('mapStatus').textContent='Select a station to begin';
  alertFired=false;
}

// ── UPDATE POSITION ───────────────────────────────────────────
function updatePosition(lat,lng){
  document.getElementById('gpsLabel').textContent='GPS active';

  if(!userMarker){
    userMarker=L.marker([lat,lng],{
      icon:L.divIcon({className:'',
        html:'<div style="background:#154734;width:14px;height:14px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 12px rgba(21,71,52,0.6)"></div>',
        iconSize:[14,14]})
    }).addTo(map).bindPopup('Your current location');
  } else {
    userMarker.setLatLng([lat,lng]);
  }

  if(!currentStation) return;

  const distMeters = haversine(lat,lng,currentStation.lat,currentStation.lng);
  const distMiles  = distMeters / 1609.34;

  // Display distance in miles
  let distDisplay, distSub;
  if(distMiles < 0.1){
    // Under 0.1 miles — show feet instead
    const feet = Math.round(distMeters * 3.28084);
    distDisplay = feet.toLocaleString();
    distSub     = 'feet';
    document.getElementById('distLbl') && (document.getElementById('distLbl').textContent='Distance (ft)');
  } else {
    distDisplay = distMiles.toFixed(2);
    distSub     = distMiles === 1 ? '1 mile' : distMiles.toFixed(2)+' miles';
  }

  // ETA — DART train averages ~30 mph = 48 km/h between stops
  // Walking to platform ~3 mph = 4.8 km/h if very close
  const speedMph = distMiles < 0.25 ? 3 : 30; // walking vs train speed
  const etaMins  = distMiles > 0 ? Math.round((distMiles / speedMph) * 60) : 0;
  const etaSub   = etaMins <= 1 ? 'arriving soon' : 'at ~'+speedMph+' mph';

  document.getElementById('distVal').textContent = distMiles < 0.1
    ? Math.round(distMeters * 3.28084).toLocaleString()
    : distMiles.toFixed(2);
  document.getElementById('distSub').textContent = distMiles < 0.1 ? 'feet away' : 'miles away';
  document.getElementById('etaVal').textContent  = etaMins;
  document.getElementById('etaSub').textContent  = etaSub;

  // Threshold display in miles
  const threshMiles = (currentStation.radius / 1609.34).toFixed(2);
  document.getElementById('threshVal').textContent = threshMiles;

  const inRange = distMeters <= currentStation.radius;
  document.getElementById('distVal').className = inRange ? 'dist-val in-range' : 'dist-val';

  if(inRange && !alertFired){
    alertFired=true;
    document.getElementById('alertBanner').style.display='block';
    document.getElementById('trackingBadge').textContent='In range!';
    document.getElementById('trackingBadge').className='badge b-active';
    document.getElementById('mapStatus').textContent='Within range — get ready!';
    if(Notification.permission==='granted'){
      new Notification('Comet Commuter',{body:'Approaching '+currentStation.name+' — prepare to get off.'});
    } else if(Notification.permission!=='denied'){
      Notification.requestPermission().then(p=>{
        if(p==='granted') new Notification('Comet Commuter',{body:'Approaching '+currentStation.name+'.'});
      });
    }
  }
}

// ── AUTO-TRIGGER line + station from URL params ───────────────
<?php if($track_line && !$auto_station): ?>
window.addEventListener('load', function(){
  const lineSel = document.getElementById('line_id');
  lineSel.value = '<?=$track_line?>';
  lineSel.dispatchEvent(new Event('change'));
});
<?php endif; ?>

// ── HERO STAT COUNTERS ───────────────────────────────────────
function animCount(el,target,duration){
  if(!el) return;
  const start=performance.now();
  function step(now){
    const p=Math.min((now-start)/duration,1);
    const ease=1-Math.pow(1-p,3);
    el.textContent=Math.round(ease*target);
    if(p<1)requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
setTimeout(()=>{
  setTimeout(()=>animCount(document.getElementById('hs1'),28,900),200);
  setTimeout(()=>animCount(document.getElementById('hs2'),5,900),400);
},300);

// ── NOTIFICATION PERMISSION ───────────────────────────────────
if(Notification && Notification.permission==='default') Notification.requestPermission();
</script>
</body>
</html>
