<?php
http_response_code(403);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>403 - Forbidden</title>
    <link rel="stylesheet" href="/hw4/assets/site.css" />
    <link rel="icon" href="/assets/bananabread.ico" />
  </head>
  <body>
    <main class="error-wrap">
      <section class="error-card">
        <h1>403</h1>
        <p>Sorry — you do not have permission to view this page.</p>
        <p>Your account can only access the pages allowed for its role.</p>
        <p><a class="error-link" href="/hw4/login.php">Go to login</a> or <a class="error-link" href="/hw4/reports/saved.php">open saved reports</a>.</p>
      </section>
    </main>
  </body>
</html>
