<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;

try {
    $stmt = $pdo->query("
      SELECT event_type, COUNT(*) AS c
      FROM events
      GROUP BY event_type
      ORDER BY c DESC
      LIMIT 20
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cssPath = __DIR__ . "/../assets/style.css";
    $css = file_exists($cssPath) ? file_get_contents($cssPath) : "";

    $generatedAt = date("Y-m-d H:i:s");

    $maxValue = 0;
    foreach ($rows as $r) {
        $count = (int)$r["c"];
        if ($count > $maxValue) {
            $maxValue = $count;
        }
    }

    ob_start();
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8">
      <style>
        <?= $css ?>

        @page {
          size: A4 landscape;
          margin: 18px;
        }

        .container {
          max-width: none;
          padding: 0;
        }

        .card {
          margin: 0;
        }

        .pdf-chart {
          margin-top: 20px;
        }

        .bar-row {
          margin-bottom: 14px;
        }

        .bar-label {
          font-size: 12px;
          margin-bottom: 4px;
          word-break: break-word;
        }

        .bar-track {
          width: 100%;
          height: 22px;
          background: #0f0f0f;
          border: 1px solid #333;
          border-radius: 6px;
        }

        .bar-fill {
          height: 22px;
          background: #8ab4f8;
          border-radius: 6px;
        }

        .bar-value {
          font-size: 11px;
          margin-top: 3px;
          opacity: 0.9;
        }

        .table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 24px;
        }

        .table th,
        .table td {
          font-size: 11px;
          word-break: break-word;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="card">
          <h1>Chart Export</h1>
          <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
          <p class="small">Top 20 event types from <code>events</code>.</p>

          <div class="pdf-chart">
            <?php foreach ($rows as $r): ?>
              <?php
                $label = $r["event_type"] ?? "(null)";
                $count = (int)$r["c"];
                $percent = $maxValue > 0 ? ($count / $maxValue) * 100 : 0;
              ?>
              <div class="bar-row">
                <div class="bar-label"><?= htmlspecialchars($label) ?></div>
                <div class="bar-track">
                  <div class="bar-fill" style="width: <?= $percent ?>%;"></div>
                </div>
                <div class="bar-value"><?= htmlspecialchars((string)$count) ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <table class="table">
            <thead>
              <tr>
                <th>Event Type</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r["event_type"] ?? "(null)") ?></td>
                  <td><?= htmlspecialchars((string)$r["c"]) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "landscape");
    $dompdf->render();

    $exportsDir = __DIR__ . "/../exports";
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0755, true);
    }

    if (!is_dir($exportsDir)) {
        throw new Exception("Exports directory does not exist: " . $exportsDir);
    }

    if (!is_writable($exportsDir)) {
        throw new Exception("Exports directory is not writable: " . $exportsDir);
    }

    $filename = "chart-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save PDF to: " . $fullPath);
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    header("Location: charts.php?pdf=" . urlencode($publicUrl));
    exit;
} catch (Exception $e) {
    header("Location: charts.php?error=" . urlencode($e->getMessage()));
    exit;
}
