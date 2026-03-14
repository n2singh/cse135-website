<?php
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();
require_role(["super_admin", "analyst"]);
require_once __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $pageStmt = $pdo->query("
      SELECT DISTINCT page
      FROM events
      WHERE page IS NOT NULL
        AND page <> ''
      ORDER BY page
    ");
    $pages = array_column($pageStmt->fetchAll(PDO::FETCH_ASSOC), "page");

    $selectedPage = $_GET["page"] ?? "";
    if ($selectedPage !== "" && !in_array($selectedPage, $pages, true)) {
        $selectedPage = "";
    }

    $bucket = $_GET["bucket"] ?? "hour";
    $bucketMap = [
        "minute" => "%Y-%m-%d %H:%i",
        "hour"   => "%Y-%m-%d %H:00",
        "day"    => "%Y-%m-%d",
    ];
    if (!isset($bucketMap[$bucket])) {
        $bucket = "hour";
    }
    $bucketFormat = $bucketMap[$bucket];

    $range = $_GET["range"] ?? "";
    $rangeMap = [
        "1d"  => "1 DAY",
        "7d"  => "7 DAY",
        "30d" => "30 DAY",
    ];

    if ($range === "") {
        if ($bucket === "minute") {
            $range = "1d";
        } elseif ($bucket === "hour") {
            $range = "7d";
        } else {
            $range = "30d";
        }
    }
    if (!isset($rangeMap[$range])) {
        $range = "7d";
    }
    $rangeSql = $rangeMap[$range];

    $sql = "
      SELECT
        DATE_FORMAT(event_ts, '{$bucketFormat}') AS bucket_label,
        event_type,
        COUNT(*) AS c
      FROM events
      WHERE event_type IS NOT NULL
        AND event_type <> ''
        AND event_ts IS NOT NULL
        AND event_ts >= DATE_SUB(NOW(), INTERVAL {$rangeSql})
    ";

    $params = [];
    if ($selectedPage !== "") {
        $sql .= " AND page = ? ";
        $params[] = $selectedPage;
    }

    $sql .= "
      GROUP BY bucket_label, event_type
      ORDER BY bucket_label ASC, event_type ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $labelIndex = [];
    $eventTypes = [];

    foreach ($rows as $r) {
        $label = $r["bucket_label"];
        $etype = $r["event_type"];

        if (!array_key_exists($label, $labelIndex)) {
            $labelIndex[$label] = count($labels);
            $labels[] = $label;
        }

        if (!in_array($etype, $eventTypes, true)) {
            $eventTypes[] = $etype;
        }
    }

    sort($eventTypes);

    $datasetsAssoc = [];
    foreach ($eventTypes as $etype) {
        $datasetsAssoc[$etype] = [
            "label" => $etype,
            "data" => array_fill(0, count($labels), 0),
        ];
    }

    $totalCount = 0;
    foreach ($rows as $r) {
        $label = $r["bucket_label"];
        $etype = $r["event_type"];
        $count = (int)$r["c"];

        if (isset($datasetsAssoc[$etype], $labelIndex[$label])) {
            $datasetsAssoc[$etype]["data"][$labelIndex[$label]] = $count;
            $totalCount += $count;
        }
    }

    $tableRows = [];
    $peakBucketLabel = null;
    $peakBucketTotal = -1;

    for ($i = 0; $i < count($labels); $i++) {
        $row = [
            "bucket_label" => $labels[$i],
            "counts" => [],
            "total" => 0,
        ];

        foreach ($eventTypes as $etype) {
            $value = $datasetsAssoc[$etype]["data"][$i] ?? 0;
            $row["counts"][$etype] = $value;
            $row["total"] += $value;
        }

        if ($row["total"] > $peakBucketTotal) {
            $peakBucketTotal = $row["total"];
            $peakBucketLabel = $row["bucket_label"];
        }

        $tableRows[] = $row;
    }

    $eventTypeTotals = [];
    $topEventType = null;
    $topEventTypeTotal = -1;

    foreach ($eventTypes as $etype) {
        $sum = array_sum($datasetsAssoc[$etype]["data"]);
        $eventTypeTotals[$etype] = $sum;

        if ($sum > $topEventTypeTotal) {
            $topEventTypeTotal = $sum;
            $topEventType = $etype;
        }
    }

    $maxY = 1;
    foreach ($eventTypes as $etype) {
        foreach ($datasetsAssoc[$etype]["data"] as $value) {
            if ($value > $maxY) {
                $maxY = $value;
            }
        }
    }

    $svgWidth = 1000;
    $svgHeight = 360;
    $padLeft = 60;
    $padRight = 20;
    $padTop = 20;
    $padBottom = 50;
    $innerWidth = $svgWidth - $padLeft - $padRight;
    $innerHeight = $svgHeight - $padTop - $padBottom;

    $palette = [
        "#e85d5d", "#d1c93a", "#67d84c", "#58d0c3", "#5469d4",
        "#b050d0", "#d95b87", "#d8a340", "#8ad14b", "#53c98b",
        "#5a95d8", "#f59e0b", "#7c83fd", "#14b8a6", "#f97316"
    ];

    $colorMap = [];
    foreach ($eventTypes as $i => $etype) {
        $colorMap[$etype] = $palette[$i % count($palette)];
    }

    $svg = "";
    if (count($labels) > 0 && count($eventTypes) > 0) {
        $bg = "#fafafa";
        $grid = "#ddd4b4";
        $axis = "#b8ab84";
        $text = "#66585b";

        $svg .= '<svg width="' . $svgWidth . '" height="' . $svgHeight . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $svgWidth . ' ' . $svgHeight . '">';
        $svg .= '<rect x="0" y="0" width="' . $svgWidth . '" height="' . $svgHeight . '" fill="' . $bg . '" />';

        for ($g = 0; $g <= 4; $g++) {
            $y = $padTop + ($innerHeight * $g / 4);
            $value = (int)round($maxY - (($maxY * $g) / 4));
            $svg .= '<line x1="' . $padLeft . '" y1="' . $y . '" x2="' . ($padLeft + $innerWidth) . '" y2="' . $y . '" stroke="' . $grid . '" stroke-width="1" />';
            $svg .= '<text x="' . ($padLeft - 10) . '" y="' . ($y + 4) . '" font-size="11" font-family="Arial, sans-serif" fill="' . $text . '" text-anchor="end">' . $value . '</text>';
        }

        $svg .= '<line x1="' . $padLeft . '" y1="' . $padTop . '" x2="' . $padLeft . '" y2="' . ($padTop + $innerHeight) . '" stroke="' . $axis . '" stroke-width="1" />';
        $svg .= '<line x1="' . $padLeft . '" y1="' . ($padTop + $innerHeight) . '" x2="' . ($padLeft + $innerWidth) . '" y2="' . ($padTop + $innerHeight) . '" stroke="' . $axis . '" stroke-width="1" />';

        $labelCount = count($labels);
        $step = max(1, (int)ceil($labelCount / 8));

        foreach ($labels as $i => $label) {
            if ($i % $step !== 0 && $i !== $labelCount - 1) {
                continue;
            }

            $x = $labelCount > 1
                ? $padLeft + ($i / ($labelCount - 1)) * $innerWidth
                : $padLeft + ($innerWidth / 2);

            $displayLabel = $label;
            $ts = strtotime($label);
            if ($ts !== false) {
                if ($bucket === "hour" || $bucket === "minute") {
                    $displayLabel = date("H:i", $ts);
                } elseif ($bucket === "day") {
                    $displayLabel = date("m-d", $ts);
                }
            }

            $safeLabel = htmlspecialchars($displayLabel, ENT_QUOTES, "UTF-8");
            $svg .= '<text x="' . $x . '" y="' . ($padTop + $innerHeight + 18) . '" font-size="10" font-family="Arial, sans-serif" fill="' . $text . '" text-anchor="middle">' . $safeLabel . '</text>';
        }

        foreach ($eventTypes as $etype) {
            $points = [];

            foreach ($datasetsAssoc[$etype]["data"] as $i => $value) {
                $x = count($labels) > 1
                    ? $padLeft + ($i / (count($labels) - 1)) * $innerWidth
                    : $padLeft + ($innerWidth / 2);

                $y = $padTop + $innerHeight - (($value / max(1, $maxY)) * $innerHeight);
                $points[] = round($x, 2) . "," . round($y, 2);
            }

            $svg .= '<polyline fill="none" stroke="' . $colorMap[$etype] . '" stroke-width="2" points="' . implode(" ", $points) . '" />';

            foreach ($datasetsAssoc[$etype]["data"] as $i => $value) {
                $x = count($labels) > 1
                    ? $padLeft + ($i / (count($labels) - 1)) * $innerWidth
                    : $padLeft + ($innerWidth / 2);

                $y = $padTop + $innerHeight - (($value / max(1, $maxY)) * $innerHeight);
                $svg .= '<circle cx="' . round($x, 2) . '" cy="' . round($y, 2) . '" r="3" fill="' . $colorMap[$etype] . '" />';
            }
        }

        $svg .= '</svg>';
    }

    $svgDataUri = "";
    if ($svg !== "") {
        $svgDataUri = "data:image/svg+xml;base64," . base64_encode($svg);
    }

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

        h1, h2 {
          margin-top: 0;
          color: #66585b;
        }

        .small {
          font-size: 11px;
          color: #66585b;
        }

        .stats {
          width: 100%;
          border-collapse: separate;
          border-spacing: 12px;
          margin-top: 0;
        }

        .stats-cell {
          width: 20%;
          background: #f1e4ba;
          border: 1px solid #333;
          border-radius: 10px;
          padding: 12px;
          vertical-align: top;
        }

        .muted {
          color: #66585b;
          font-size: 11px;
          margin-bottom: 4px;
        }

        .chart-wrap {
          border: 1px solid #333;
          border-radius: 12px;
          background: #f1e4ba;
          padding: 12px;
          page-break-inside: avoid;
        }

        .chart-inner {
          background: #fafafa;
          border: 1px solid #ccc;
          border-radius: 10px;
          padding: 12px;
        }

        .chart-box {
          text-align: center;
        }

        .chart-box img {
          width: 100%;
          max-width: 1000px;
          height: auto;
          display: block;
          margin: 0 auto;
        }

        .legend {
          margin-top: 12px;
          font-size: 11px;
          color: #66585b;
          line-height: 1.7;
        }

        .legend-item {
          display: inline-block;
          margin-right: 12px;
          margin-bottom: 6px;
        }

        .legend-swatch {
          display: inline-block;
          width: 10px;
          height: 10px;
          margin-right: 5px;
          vertical-align: middle;
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
          word-break: break-word;
        }

        .table th {
          background: #f1e4ba;
        }

        .note {
          font-size: 11px;
          line-height: 1.5;
          color: #66585b;
        }

        .url-box {
          word-break: break-word;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="card">
          <h1>Event Trends Over Time</h1>
          <p class="small">Generated at <?= htmlspecialchars($generatedAt) ?></p>
          <p class="small">
            Each line represents one event type. The line rises or falls based on how many times that event happened in each time bucket.
          </p>
        </div>

        <div class="card">
          <table class="stats">
            <tr>
              <td class="stats-cell">
                <div class="muted">Scope</div>
                <div class="url-box"><?= $selectedPage === "" ? "All Pages" : htmlspecialchars($selectedPage) ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Granularity</div>
                <div><?= htmlspecialchars(ucfirst($bucket)) ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Time Buckets</div>
                <div><?= count($labels) ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Distinct Event Types</div>
                <div><?= count($eventTypes) ?></div>
              </td>
              <td class="stats-cell">
                <div class="muted">Aggregated Event Count</div>
                <div><?= (int)$totalCount ?></div>
              </td>
            </tr>
          </table>
        </div>

        <div class="card">
          <h2>Trend Chart</h2>

          <?php if (count($labels) === 0 || count($eventTypes) === 0): ?>
            <p>No event data was available for this selection.</p>
          <?php else: ?>
            <div class="chart-wrap">
              <div class="chart-inner">
                <div class="chart-box">
                  <img src="<?= $svgDataUri ?>" alt="Trend Chart">
                </div>

                <div class="legend">
                  <?php foreach ($eventTypes as $etype): ?>
                    <span class="legend-item">
                      <span class="legend-swatch" style="background: <?= htmlspecialchars($colorMap[$etype]) ?>;"></span>
                      <?= htmlspecialchars($etype) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="card">
          <h2>Trend Data Table</h2>

          <?php if (count($tableRows) === 0): ?>
            <p>No aggregated data is available for this selection.</p>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Time Bucket</th>
                  <?php foreach ($eventTypes as $etype): ?>
                    <th><?= htmlspecialchars($etype) ?></th>
                  <?php endforeach; ?>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tableRows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["bucket_label"]) ?></td>
                    <?php foreach ($eventTypes as $etype): ?>
                      <td><?= (int)$row["counts"][$etype] ?></td>
                    <?php endforeach; ?>
                    <td><strong><?= (int)$row["total"] ?></strong></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th>Total</th>
                  <?php foreach ($eventTypes as $etype): ?>
                    <th><?= (int)$eventTypeTotals[$etype] ?></th>
                  <?php endforeach; ?>
                  <th><?= (int)$totalCount ?></th>
                </tr>
              </tfoot>
            </table>
          <?php endif; ?>
        </div>

        <div class="card">
          <h2>What This Means</h2>

          <?php if (count($tableRows) === 0): ?>
            <p class="note">
              There is not enough data in the current selection to interpret a trend yet.
            </p>
          <?php else: ?>
            <p class="note">
              This visualization tracks how frequently each event type occurred across the selected time period. A line rises when that event type becomes more common in a given bucket, and falls when that activity becomes less common. In other words, the chart is showing the progression of behavior over time rather than isolated single events.
            </p>

            <p class="note">
              In this selection, the most common event type was
              <strong><?= htmlspecialchars($topEventType ?? "N/A") ?></strong>
              with <strong><?= (int)max(0, $topEventTypeTotal) ?></strong> total events.
              The busiest bucket was
              <strong><?= htmlspecialchars($peakBucketLabel ?? "N/A") ?></strong>
              with <strong><?= (int)max(0, $peakBucketTotal) ?></strong> total logged events.
            </p>

            <p class="note">
              The table contains the exact values used in the chart, making it easier to verify whether a visible spike
              represents a short burst, a repeated pattern, or an unusual anomaly in activity.
            </p>
          <?php endif; ?>
        </div>
      </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set("isHtml5ParserEnabled", true);
    $options->set("isPhpEnabled", true);
    $options->set("isRemoteEnabled", true);

    $dompdf = new Dompdf($options);
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

    $filename = "event-scatter-report-" . date("Ymd-His") . ".pdf";
    $fullPath = $exportsDir . "/" . $filename;

    if (file_put_contents($fullPath, $dompdf->output()) === false) {
        throw new Exception("Could not save PDF to: " . $fullPath);
    }

    $publicUrl = "/hw4/exports/" . rawurlencode($filename);

    $redirect = "/hw4/reports/event-scatter.php?bucket=" . urlencode($bucket) . "&range=" . urlencode($range);
    if ($selectedPage !== "") {
        $redirect .= "&page=" . urlencode($selectedPage);
    }
    $redirect .= "&pdf=" . urlencode($publicUrl);

    header("Location: " . $redirect);
    exit;
} catch (Throwable $e) {
    $redirect = "/hw4/reports/event-scatter.php?bucket=" . urlencode($_GET["bucket"] ?? "hour") . "&range=" . urlencode($_GET["range"] ?? "7d");
    if (!empty($_GET["page"])) {
        $redirect .= "&page=" . urlencode($_GET["page"]);
    }
    $redirect .= "&error=" . urlencode($e->getMessage());

    header("Location: " . $redirect);
    exit;
}
