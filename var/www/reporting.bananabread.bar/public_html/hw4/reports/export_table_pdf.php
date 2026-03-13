<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;

try {
    $stmt = $pdo->query("SELECT id, session_id, event_type, page, event_ts, data FROM events ORDER BY id DESC LIMIT 100");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cssPath = __DIR__ . "/../assets/style.css";
    $css = file_exists($cssPath) ? file_get_contents($cssPath) : "";

    $generatedAt = date("Y-m-d H:i:s");

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

        .table {
          table-layout: fixed;
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
          <h1>Data Table Export</h1>
          <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
          <p class="small">Latest 100 rows from <code>collector_db.events</code>.</p>

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
                  <td><?= htmlspecialchars((string)$r["id"]) ?></td>
                  <td><?= htmlspecialchars((string)$r["session_id"]) ?></td>
                  <td><?= htmlspecialchars((string)$r["event_type"]) ?></td>
                  <td><?= htmlspecialchars((string)$r["page"]) ?></td>
                  <td><?= htmlspecialchars((string)$r["event_ts"]) ?></td>
                  <td><?= htmlspecialchars((string)$r["data"]) ?></td>
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

    $filename = "events-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save the PDF.");
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    header("Location: table.php?pdf=" . urlencode($publicUrl));
    exit;
} catch (Exception $e) {
    header("Location: table.php?error=" . urlencode($e->getMessage()));
    exit;
}
