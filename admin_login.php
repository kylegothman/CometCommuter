<?php
session_start();
define('ADMIN_PASSWORD','cometadmin2026');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['password']??'')===ADMIN_PASSWORD){$_SESSION['admin_logged_in']=true;header('Location: admin_panel.php');exit;}
  else $error='Incorrect password. Please try again.';
}
if(!empty($_SESSION['admin_logged_in'])){header('Location: admin_panel.php');exit;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Comet Commuter — Admin Login</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
  <a href="index.php" class="brand"><strong>Comet</strong>Commuter</a>
  <ul><li><a href="index.php">Dashboard</a></li></ul>
</nav>
<div class="login-page">
  <div class="login-card">
    <div class="login-brand"><strong>Comet</strong>Commuter</div>
    <div class="login-sub">Administrator Access &nbsp;&middot;&nbsp; UT Dallas</div>
    <div class="restrict-tag">Restricted Area</div>
    <?php if($error): ?><div class="msg-error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="POST" action="admin_login.php">
      <div class="field" style="margin-bottom:14px;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter admin password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary" style="width:100%;padding:12px;font-size:14px;">Sign In</button>
    </form>
    <div class="note-box">Credentials validated server-side. Not stored in the database.</div>
    <div class="back-link"><a href="index.php">Back to Dashboard</a></div>
  </div>
</div>
</body>
</html>
