<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Missing username or password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, allowed_sections FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $u = $stmt->fetch();

        if ($u && password_verify($password, $u["password_hash"])) {
            session_regenerate_id(true);

            $_SESSION["user"] = [
                "id" => $u["id"],
                "username" => $u["username"],
                "role" => $u["role"],
                "allowed_sections" => $u["allowed_sections"]
                    ? json_decode($u["allowed_sections"], true)
                    : []
            ];

            header("Location: /hw4/index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>HW4 Login</title>

<!-- Shared styling for HW4/HW5 -->
<link rel="icon" href="/assets/bananabread.ico">
<link rel="stylesheet" href="/hw4/assets/site.css" />

</head>
<body>

<?php include __DIR__ . "/includes/header.php"; ?>

<main class="main">
  <section class="section">
    <h2>Login</h2>

    <div class="form-box">
      <p class="small">Login required. Forceful browsing is blocked.</p>

      <?php if ($error !== ""): ?>
        <p style="color:#ff5a73;"><strong><?= htmlspecialchars($error) ?></strong></p>
      <?php endif; ?>

      <form method="POST" action="/hw4/login.php">
        <label>Username</label>
        <input name="username" autocomplete="username" />

        <label>Password</label>
        <input name="password" type="password" autocomplete="current-password" />

        <div style="margin-top:12px;">
          <button class="btn" type="submit">Login</button>
        </div>
      </form>
    </div>

  </section>
</main>

<?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>
