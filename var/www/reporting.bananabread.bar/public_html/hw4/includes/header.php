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

          <!-- VIEWER NAVIGATION -->
          <?php if ($role === "viewer"): ?>

            <a href="/hw4/index.php">Home</a>
            <a href="/hw4/reports/saved.php">Saved Reports</a>
            <a href="/hw4/logout.php">Logout</a>

          <!-- ANALYST / ADMIN NAVIGATION -->
          <?php else: ?>

            <a href="/hw4/index.php">Home</a>

            <?php if ($role === "super_admin" || user_can_access_section('behavior')): ?>
              <a href="/hw4/reports/table.php">Data Table (HW4)</a>
            <?php endif; ?>

            <?php if ($role === "super_admin" || user_can_access_section('performance')): ?>
              <a href="/hw4/reports/charts.php">Charts (HW4)</a>
            <?php endif; ?>
	    <a href="/hw4/reports/view-performance.php">Performance</a>
            <a href="/hw4/reports/heatmap.php">Behavior</a>
            <a href="/hw4/reports/event-scatter.php">Engagement</a>
	    <a href="/hw4/reports/saved.php">Saved Reports</a>
            <a href="/hw4/logout.php">Logout</a>

          <?php endif; ?>

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
