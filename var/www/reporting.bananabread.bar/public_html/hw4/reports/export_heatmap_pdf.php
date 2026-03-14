<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();
require_once __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;

try {
    $pageStmt = $pdo->query("
      SELECT DISTINCT page
      FROM events
      WHERE event_type = 'click'
        AND page IS NOT NULL
        AND page <> ''
      ORDER BY page
    ");
    $pages = array_column($pageStmt->fetchAll(PDO::FETCH_ASSOC), "page");

    $selectedPage = $_GET["page"] ?? ($pages[0] ?? "");
    if ($selectedPage !== "" && !in_array($selectedPage, $pages, true)) {
        $selectedPage = $pages[0] ?? "";
    }

    $rows = [];
    if ($selectedPage !== "") {
        $stmt = $pdo->prepare("
          SELECT id, event_ts, data
          FROM events
          WHERE event_type = 'click'
            AND page = ?
          ORDER BY id DESC
          LIMIT 5000
        ");
        $stmt->execute([$selectedPage]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $rawPoints = [];
    $sourceWidth = 0;
    $sourceHeight = 0;

    foreach ($rows as $row) {
        $d = json_decode($row["data"] ?? "", true);
        if (!is_array($d)) {
            continue;
        }

        $x = $d["docX"] ?? $d["pageX"] ?? $d["x"] ?? $d["clientX"] ?? null;
        $y = $d["docY"] ?? $d["pageY"] ?? $d["y"] ?? $d["clientY"] ?? null;

        $w = $d["docWidth"] ?? $d["pageWidth"] ?? $d["documentWidth"] ?? $d["innerWidth"] ?? $d["viewportWidth"] ?? null;
        $h = $d["docHeight"] ?? $d["pageHeight"] ?? $d["documentHeight"] ?? $d["innerHeight"] ?? $d["viewportHeight"] ?? null;

        if (!isset($d["docX"]) && !isset($d["pageX"]) && isset($d["clientX"], $d["scrollX"])) {
            $x = $d["clientX"] + $d["scrollX"];
        }
        if (!isset($d["docY"]) && !isset($d["pageY"]) && isset($d["clientY"], $d["scrollY"])) {
            $y = $d["clientY"] + $d["scrollY"];
        }

        if (!is_numeric($x) || !is_numeric($y)) {
            continue;
        }

        $x = (float)$x;
        $y = (float)$y;

        if (is_numeric($w) && $w > $sourceWidth) {
            $sourceWidth = (float)$w;
        }
        if (is_numeric($h) && $h > $sourceHeight) {
            $sourceHeight = (float)$h;
        }

        $rawPoints[] = ["x" => $x, "y" => $y];
    }

    if ($sourceWidth <= 0) {
        foreach ($rawPoints as $p) {
            if ($p["x"] > $sourceWidth) {
                $sourceWidth = $p["x"];
            }
        }
    }

    if ($sourceHeight <= 0) {
        foreach ($rawPoints as $p) {
            if ($p["y"] > $sourceHeight) {
                $sourceHeight = $p["y"];
            }
        }
    }

    if ($sourceWidth <= 0) $sourceWidth = 1200;
    if ($sourceHeight <= 0) $sourceHeight = 800;

    $previewWidth = 760;
    $previewHeight = (int)round(($sourceHeight / max(1, $sourceWidth)) * $previewWidth);

    if ($previewHeight < 320) $previewHeight = 320;
    if ($previewHeight > 420) $previewHeight = 420;

    $binSize = 14;
    $bins = [];
    $totalClicks = 0;

    foreach ($rawPoints as $p) {
        $nx = (int)round(($p["x"] / $sourceWidth) * $previewWidth);
        $ny = (int)round(($p["y"] / $sourceHeight) * $previewHeight);

        $nx = max(0, min($previewWidth - 1, $nx));
        $ny = max(0, min($previewHeight - 1, $ny));

        $bx = (int)(floor($nx / $binSize) * $binSize);
        $by = (int)(floor($ny / $binSize) * $binSize);

        $key = $bx . ":" . $by;
        if (!isset($bins[$key])) {
            $bins[$key] = [
                "x" => $bx,
                "y" => $by,
                "value" => 0
            ];
        }

        $bins[$key]["value"]++;
        $totalClicks++;
    }

    $points = array_values($bins);
    $maxValue = 1;
    foreach ($points as $p) {
        if ($p["value"] > $maxValue) {
            $maxValue = $p["value"];
        }
    }

    $rankedPoints = $points;
    usort($rankedPoints, function ($a, $b) {
        return $b["value"] <=> $a["value"];
    });
    $topHotspots = array_slice($rankedPoints, 0, 8);

    // Match the same stylesheet used by heatmap.php
    $cssPath = __DIR__ . "/../assets/site.css";
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

        body {
          font-size: 12px;
          background: #fffaf0;
          color: #66585b;
        }

        .container {
          max-width: none;
          padding: 0;
        }

        .card {
          margin: 0 0 14px 0;
          padding: 14px;
          border: 1px solid #333;
          border-radius: 12px;
          background: #fffaf0;
          page-break-inside: avoid;
        }

        .stats {
          width: 100%;
          border-collapse: separate;
          border-spacing: 12px;
          margin-top: 0;
        }

        .stats-cell {
          width: 25%;
          background: #f1e4ba;
          border: 1px solid #333;
          border-radius: 10px;
          padding: 12px;
          vertical-align: top;
        }

        .muted {
          color: #66585b;
          font-size: 11px;
        }

        .url-box {
          word-break: break-word;
        }

        .explanation {
          margin-top: 12px;
          line-height: 1.5;
        }

        .heatmap-wrap {
          border: 1px solid #333;
          border-radius: 12px;
          background: #f1e4ba;
          padding: 12px;
          page-break-inside: avoid;
        }

        .heatmap-stage {
          position: relative;
          width: <?= (int)$previewWidth ?>px;
          height: <?= (int)$previewHeight ?>px;
          margin: 0 auto;
          background-color: #fafafa;
          background-image:
            linear-gradient(to right, rgba(0,0,0,0.06) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px);
          background-size: 40px 40px;
          border: 1px solid #ccc;
          border-radius: 10px;
          overflow: hidden;
        }

        .stage-label {
          position: absolute;
          z-index: 5;
          font-size: 10px;
          color: #66585b;
          background: rgba(255,255,255,0.85);
          padding: 3px 5px;
          border-radius: 6px;
        }

        .stage-label.tl { top: 8px; left: 8px; }
        .stage-label.br { right: 8px; bottom: 8px; }

        .heat-spot {
          position: absolute;
          border-radius: 999px;
          background: #ff4d00;
        }

        .small-note {
          margin-top: 10px;
          font-size: 10px;
          color: #66585b;
          opacity: 0.95;
        }

        .table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 12px;
        }

        .table th,
        .table td {
          font-size: 10px;
          padding: 8px 10px;
          border: 1px solid #333;
          text-align: left;
          color: #66585b;
        }

        .table th {
          background: #f1e4ba;
        }

        h1, h2 {
          margin-top: 0;
          color: #66585b;
        }

        .small {
          font-size: 11px;
          color: #66585b;
        }

        code {
          font-size: 10px;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="card">
          <h1>Click Heatmap</h1>
          <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
          <p class="small">
            This view plots click concentration for one page at a time.
          </p>
        </div>

        <div class="card">
          <table class="stats">
            <tr>
              <td class="stats-cell">
                <div class="muted">Selected Page</div>
                <div class="url-box"><?= $selectedPage !== "" ? htmlspecialchars($selectedPage) : "No click pages found" ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Raw Click Events</div>
                <div><?= (int)$totalClicks ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Heatmap Cells</div>
                <div><?= count($points) ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Normalized Preview</div>
                <div><?= (int)$previewWidth ?> × <?= (int)$previewHeight ?></div>
              </td>
            </tr>
          </table>

          <p class="explanation">
            This heatmap represents where clicks are most concentrated on the selected page. Brighter and stronger
            hotspots indicate areas where more clicks were grouped together, while lighter areas indicate fewer clicks.
            The numeric table below summarizes the strongest hotspot cells after the click positions were normalized and binned.
          </p>
        </div>

        <div class="card">
          <?php if ($selectedPage === ""): ?>
            <p>No pages with click events were found.</p>
          <?php elseif (count($points) === 0): ?>
            <p>
              Click events were found for this page, but no usable coordinates were extracted from the
              <code>data</code> JSON. Your click payload should include coordinates such as
              <code>docX/docY</code>, <code>pageX/pageY</code>, or at least <code>clientX/clientY</code>.
            </p>
          <?php else: ?>
            <div class="heatmap-wrap">
              <div class="heatmap-stage">
                <div class="stage-label tl">top-left</div>
                <div class="stage-label br">bottom-right</div>

                <?php foreach ($points as $p): ?>
                  <?php
                    $ratio = $maxValue > 0 ? ($p["value"] / $maxValue) : 0;
                    $size = 12 + (int)round($ratio * 24);
                    $opacity = 0.18 + ($ratio * 0.42);
                    $left = (int)$p["x"] - (int)floor($size / 2);
                    $top = (int)$p["y"] - (int)floor($size / 2);

                    if ($left < 0) $left = 0;
                    if ($top < 0) $top = 0;
                    if ($left > $previewWidth - $size) $left = $previewWidth - $size;
                    if ($top > $previewHeight - $size) $top = $previewHeight - $size;
                  ?>
                  <div
                    class="heat-spot"
                    style="left: <?= $left ?>px; top: <?= $top ?>px; width: <?= $size ?>px; height: <?= $size ?>px; opacity: <?= $opacity ?>;">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <p class="small-note">
              Best accuracy comes from storing full-page click coordinates and page dimensions in each click event.
              If only viewport-relative coordinates were stored, this heatmap represents the viewport rather than the full document.
            </p>
          <?php endif; ?>
        </div>

        <div class="card">
          <h2>Hotspot Data Table</h2>
          <?php if (count($topHotspots) === 0): ?>
            <p>No numeric hotspot data is available for the selected page.</p>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Normalized X</th>
                  <th>Normalized Y</th>
                  <th>Clicks in Cell</th>
                  <th>Relative Intensity</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topHotspots as $index => $p): ?>
                  <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= (int)$p["x"] ?></td>
                    <td><?= (int)$p["y"] ?></td>
                    <td><?= (int)$p["value"] ?></td>
                    <td><?= round(((int)$p["value"] / max(1, $maxValue)) * 100, 1) ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf([
        "isRemoteEnabled" => true
    ]);
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

    $filename = "heatmap-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save PDF to: " . $fullPath);
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    header("Location: heatmap.php?page=" . urlencode($selectedPage) . "&pdf=" . urlencode($publicUrl));
    exit;
} catch (Exception $e) {
    echo "Export failed: " . htmlspecialchars($e->getMessage());
    exit;
}
