<?php
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_login();
require_role(["super_admin", "analyst"]);

// Page filter
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

// Bucket filter
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

// Range filter
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

// Aggregated counts by time bucket + event_type
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

// Build label list
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

// Initialize datasets with zeros for every label
$datasetsAssoc = [];
foreach ($eventTypes as $etype) {
  $datasetsAssoc[$etype] = [
    "label" => $etype,
    "data" => array_fill(0, count($labels), 0),
  ];
}

// Fill counts
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

$datasets = array_values($datasetsAssoc);

// Build table rows
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

// Totals per event type
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

$pdfUrl = $_GET["pdf"] ?? "";
$pdfError = $_GET["error"] ?? "";
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Event Trends</title>
  <link rel="icon" href="/assets/bananabread.ico"/>
  <link rel="stylesheet" href="/hw4/assets/site.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .controls {
      display: flex;
      gap: 12px;
      align-items: end;
      flex-wrap: wrap;
    }

    .controls .field {
      min-width: 220px;
    }

    select {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid #444;
      background: #f1e4ba;
      color: #66585b;
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }

    .stat {
      background: #f1e4ba;
      border: 1px solid #333;
      border-radius: 10px;
      padding: 12px;
    }

    .muted {
      color: #66585b;
      font-size: 0.95rem;
    }

    .chart-card {
      overflow: hidden;
    }

    .chart-wrap {
      position: relative;
      min-height: 540px;
      border: 1px solid #333;
      border-radius: 12px;
      background: #f1e4ba;
      padding: 16px;
    }

    .chart-inner {
      position: relative;
      min-height: 500px;
      background: #fafafa;
      border: 1px solid #ccc;
      border-radius: 10px;
      padding: 12px;
    }

    .table-wrap {
      overflow: auto;
      border: 1px solid #333;
      border-radius: 10px;
      background: #f1e4ba;
    }

    .explanation p {
      margin: 0 0 12px;
      line-height: 1.5;
    }

    .explanation p:last-child {
      margin-bottom: 0;
    }

    .success-box {
      border: 1px solid #2e7d32;
      background: rgba(46, 125, 50, 0.12);
      border-radius: 10px;
      padding: 12px;
    }

    .success-box p {
      margin: 0 0 8px;
    }

    .success-box p:last-child {
      margin-bottom: 0;
    }

    .error-box {
      border: 1px solid #b71c1c;
      background: rgba(183, 28, 28, 0.10);
      border-radius: 10px;
      padding: 12px;
    }

    .error-box p {
      margin: 0 0 8px;
    }

    .error-box p:last-child {
      margin-bottom: 0;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/../includes/header.php"; ?>

  <div class="container">

    <?php if ($pdfUrl !== ""): ?>
      <div class="card success-box">
        <p><strong>PDF exported successfully.</strong></p>
        <p>
          <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener">Open exported PDF</a>
        </p>
      </div>
    <?php endif; ?>

    <?php if ($pdfError !== ""): ?>
      <div class="card error-box">
        <p><strong>Export failed.</strong></p>
        <p><?= htmlspecialchars($pdfError) ?></p>
      </div>
    <?php endif; ?>

    <div class="card">
      <h1>Event Trends Over Time</h1>
      <p class="small">
        Each line represents one event type. The line rises or falls based on how many times that event happened in each time bucket.
      </p>

      <form method="GET" action="/hw4/reports/event-scatter.php" class="controls">
        <div class="field">
          <label for="page">Page</label>
          <select id="page" name="page" onchange="this.form.submit()">
            <option value="" <?= $selectedPage === "" ? "selected" : "" ?>>All Pages</option>
            <?php foreach ($pages as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>" <?= $selectedPage === $p ? "selected" : "" ?>>
                <?= htmlspecialchars($p) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="bucket">Granularity</label>
          <select id="bucket" name="bucket" onchange="this.form.submit()">
            <option value="minute" <?= $bucket === "minute" ? "selected" : "" ?>>Minute</option>
            <option value="hour" <?= $bucket === "hour" ? "selected" : "" ?>>Hour</option>
            <option value="day" <?= $bucket === "day" ? "selected" : "" ?>>Day</option>
          </select>
        </div>

        <div class="field">
          <label for="range">Range</label>
          <select id="range" name="range" onchange="this.form.submit()">
            <option value="1d" <?= $range === "1d" ? "selected" : "" ?>>Last 1 Day</option>
            <option value="7d" <?= $range === "7d" ? "selected" : "" ?>>Last 7 Days</option>
            <option value="30d" <?= $range === "30d" ? "selected" : "" ?>>Last 30 Days</option>
          </select>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Load Trends</button>
          <button
            class="btn"
            type="submit"
            formaction="/hw4/reports/export_event_scatter_pdf.php">
            Export PDF
          </button>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="stats">
        <div class="stat">
          <div class="muted">Scope</div>
          <div><?= $selectedPage === "" ? "All Pages" : htmlspecialchars($selectedPage) ?></div>
        </div>

        <div class="stat">
          <div class="muted">Granularity</div>
          <div><?= htmlspecialchars(ucfirst($bucket)) ?></div>
        </div>

        <div class="stat">
          <div class="muted">Time Buckets</div>
          <div><?= count($labels) ?></div>
        </div>

        <div class="stat">
          <div class="muted">Distinct Event Types</div>
          <div><?= count($datasets) ?></div>
        </div>

        <div class="stat">
          <div class="muted">Aggregated Event Count</div>
          <div><?= (int)$totalCount ?></div>
        </div>
      </div>
    </div>

    <div class="card chart-card">
      <?php if (count($labels) === 0 || count($datasets) === 0): ?>
        <p>No event data was available for this selection.</p>
      <?php else: ?>
        <div class="chart-wrap">
          <div class="chart-inner">
            <canvas id="eventTrendsChart"></canvas>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Trend Data Table</h2>
      <p class="small">
        This table shows the exact event counts that were used to draw the chart above.
      </p>

      <?php if (count($tableRows) === 0): ?>
        <p>No aggregated data is available for this selection.</p>
      <?php else: ?>
        <div class="table-wrap">
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
        </div>
      <?php endif; ?>
    </div>

    <div class="card explanation">
      <h2>What This Means</h2>

      <?php if (count($tableRows) === 0): ?>
        <p>
          There is not enough data in the current selection to interpret a trend yet. Try widening the time range or choosing a page with more recorded events.
        </p>
      <?php else: ?>
        <p>
          This visualization tracks how frequently each event type occurred across the selected time period. A line rises when that event type becomes more common in a given bucket, and falls when that activity becomes less common. In other words, the chart is showing the progression of behavior over time rather than isolated single events.
        </p>

        <p>
          In this selection, the most common event type was
          <strong><?= htmlspecialchars($topEventType ?? "N/A") ?></strong>
          with <strong><?= (int)max(0, $topEventTypeTotal) ?></strong> total events.
          The busiest bucket was
          <strong><?= htmlspecialchars($peakBucketLabel ?? "N/A") ?></strong>
          with <strong><?= (int)max(0, $peakBucketTotal) ?></strong> total logged events.
        </p>

        <p>
          The table underneath the chart contains the exact counts used to draw each line. This makes it easier to verify the visual pattern numerically and helps explain whether a spike reflects a short burst of interaction, a sustained trend, or a possible anomaly in user behavior.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (count($labels) > 0 && count($datasets) > 0): ?>
  <script>
    const labels = <?= json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const datasets = <?= json_encode($datasets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const styledDatasets = datasets.map((ds, i) => {
      const color = `hsl(${(i * 57) % 360} 70% 60%)`;
      return {
        label: ds.label,
        data: ds.data,
        borderColor: color,
        backgroundColor: color,
        fill: false,
        tension: 0.2,
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 4
      };
    });

    const canvas = document.getElementById("eventTrendsChart");
    if (canvas) {
      const ctx = canvas.getContext("2d");

      new Chart(ctx, {
        type: "line",
        data: {
          labels,
          datasets: styledDatasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: "index",
            intersect: false
          },
          plugins: {
            tooltip: {
              mode: "index",
              intersect: false
            },
            legend: {
              labels: {
                color: "#66585b",
                boxWidth: 28,
                boxHeight: 12,
                padding: 12
              }
            }
          },
          scales: {
            x: {
              title: {
                display: true,
                text: "Time",
                color: "#66585b"
              },
              ticks: {
                autoSkip: true,
                maxTicksLimit: 12,
                color: "#66585b"
              },
              grid: {
                color: "rgba(0,0,0,0.08)"
              }
            },
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: "Event Count",
                color: "#66585b"
              },
              ticks: {
                color: "#66585b"
              },
              grid: {
                color: "rgba(0,0,0,0.08)"
              }
            }
          }
        }
      });
    }
  </script>
  <?php endif; ?>

  <?php include __DIR__ . "/../includes/footer.php"; ?>
</body>
</html>
