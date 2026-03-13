<?php
header("Content-Type: text/html; charset=utf-8");
date_default_timezone_set("America/Los_Angeles");
$action = $_GET["action"] ?? "view";

if ($action === "set" && isset($_POST["value"])) {
  setcookie("hw2_state_php", $_POST["value"], time() + 3600, "/");
  $msg = "Saved!";
} elseif ($action === "clear") {
  setcookie("hw2_state_php", "", time() - 3600, "/");
  $msg = "Cleared!";
} else {
  $msg = "";
}

$current = $_COOKIE["hw2_state_php"] ?? "(nothing saved)";
$uid = $_COOKIE['uid'] ?? '(none)';
?>
<!doctype html>
<html>
<head>
  <script src="https://cdn.logr-in.com/LogRocket.min.js" crossorigin="anonymous"></script>
  <script>window.LogRocket && window.LogRocket.init('c2uo5x/bananabreadbar');</script>
  <meta charset="utf-8"><title>State (PHP)</title>
</head>
<body>
  <h1>State Demo (PHP)</h1>
  <?php if ($msg) { ?><p><b><?php echo htmlspecialchars($msg); ?></b></p><?php } ?>
  <p>Current saved value: <b><?php echo htmlspecialchars($current); ?></b></p>

  <h2>Save</h2>
  <form method="POST" action="/cgi-bin/hw2/php/state-php.php?action=set">
    <input name="value" placeholder="type something">
    <button type="submit">Save</button>
  </form>

  <h2>Clear</h2>
  <form method="GET" action="/cgi-bin/hw2/php/state-php.php">
    <input type="hidden" name="action" value="clear">
    <button type="submit">Clear</button>
  </form>

  <p><a href="/cgi-bin/hw2/php/state-php.php">Refresh/View</a></p>

  <h2>Fingerprint</h2>
  <p>Fingerprint UID: <b><?php echo htmlspecialchars($uid); ?></b></p>
  <p><a href="/hw2/fingerprint.html">Run fingerprint reassociation</a></p>

</body>
</html>
