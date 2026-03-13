<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_section_access('behavior');
require_login();



$stmt = $pdo->query("SELECT id, session_id, event_type, page, event_ts, data FROM events ORDER BY id DESC LIMIT 100");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdfUrl = $_GET["pdf"] ?? "";
$pdfError = $_GET["error"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>HW4 Data Table</title>
  <link rel="icon" href="/assets/bananabread.ico">
  <link rel="stylesheet" href="/hw4/assets/site.css" />
</head>
<body>
  <?php include __DIR__ . "/../includes/header.php"; ?>

  <div class="container">
    <div class="card">
      <h1>Data Table</h1>
      <p class="small">Latest 100 rows from <code>collector_db.events</code>.</p>

      <p>
        <a class="btn" href="export_table_pdf.php">Export PDF</a>
      </p>

      <?php if ($pdfUrl !== ""): ?>
        <p class="small">
          PDF created:
          <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener">Open report</a>
        </p>
      <?php endif; ?>

      <?php if ($pdfError !== ""): ?>
        <p class="small">Export failed: <?= htmlspecialchars($pdfError) ?></p>
      <?php endif; ?>

      <table class="table">
        <thead>
          <tr>
            <th>id</th>
            <th>session_id</th>
            <th>event_type</th>
            <th>page</th>
            <th>event_ts</th>
            <th>data</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r["id"]) ?></td>
              <td><?= htmlspecialchars($r["session_id"]) ?></td>
              <td><?= htmlspecialchars($r["event_type"]) ?></td>
              <td style="max-width:260px; word-break:break-word;"><?= htmlspecialchars($r["page"]) ?></td>
              <td><?= htmlspecialchars($r["event_ts"]) ?></td>
              <td style="max-width:340px; word-break:break-word;"><?= htmlspecialchars($r["data"]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
