<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_section_access('performance');
require_login();

$stmt = $pdo->query("
  SELECT event_type, COUNT(*) AS c
  FROM events
  GROUP BY event_type
  ORDER BY c DESC
  LIMIT 20
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$values = [];
foreach ($rows as $r) {
  $labels[] = $r["event_type"] ?? "(null)";
  $values[] = (int)$r["c"];
}

$pdfUrl = $_GET["pdf"] ?? "";
$pdfError = $_GET["error"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Charts</title>
  <link rel="icon" href="/assets/bananabread.ico">
  <link rel="stylesheet" href="/hw4/assets/site.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include __DIR__ . "/../includes/header.php"; ?>

  <div class="container">
    <div class="card">
      <h1>Chart</h1>
      <p class="small">Top 20 event types from <code>events</code>.</p>

      <p>
        <a class="btn" href="export_charts_pdf.php">Export PDF</a>
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

      <canvas id="eventTypeChart" width="900" height="380"></canvas>
    </div>
  </div>

  <script>
    const labels = <?= json_encode($labels) ?>;
    const values = <?= json_encode($values) ?>;

    const ctx = document.getElementById('eventTypeChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Count',
          data: values
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  </script>

  <?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
