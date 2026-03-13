<?php require_once __DIR__ . "/auth.php"; ?>
<link rel="icon" href="/assets/bananabread.ico" />

<header class="top">
  <div class="top-inner">

    <!-- LEFT SIDE -->
    <div class="brand-wrap">
      <img src="/assets/bananabread.png" alt="Bananabread Logo" class="brand-logo">

      <div class="brand-text">
        <h1>Bananabread Analytics</h1>
        <p>Reporting Dashboard</p>
      </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="nav-area">

      <div class="nav-links">
        <?php if (!is_logged_in()): ?>
          <a href="/hw4/login.php">Login</a>
        <?php else: ?>
          <?php $role = $_SESSION["user"]["role"] ?? ""; ?>

          <?php if ($role === "super_admin" || $role === "analyst"): ?>
            <a href="/hw4/index.php">Home</a>

            <?php if ($role === "super_admin" || user_can_access_section('behavior')): ?>
              <a href="/hw4/reports/table.php">Data Table</a>
            <?php endif; ?>

            <?php if ($role === "super_admin" || user_can_access_section('performance')): ?>
              <a href="/hw4/reports/charts.php">Charts</a>
            <?php endif; ?>

	<!-- NEW HEATMAP REPORT -->
        <a href="/hw4/reports/heatmap.php">Heatmap</a> 

          <?php endif; ?>

          <?php if (
            $role === "super_admin" ||
            $role === "analyst" ||
            $role === "viewer"
          ): ?>
            <a href="/hw4/reports/view-performance.php">Performance</a>
            <a href="/hw4/reports/view-behavior.php">Behavior</a>
          <?php endif; ?>

          <a href="/hw4/logout.php">Logout</a>
        <?php endif; ?>
      </div>

      <?php if (is_logged_in()): ?>
        <p class="small">
          Logged in as
          <?= htmlspecialchars($_SESSION["user"]["username"] ?? "") ?>
          (<?= htmlspecialchars($role) ?>)
        </p>
      <?php endif; ?>

    </div>

  </div>
</header>
