<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
$serverTime = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="The COP 3350 lab web server’s time in US Eastern Time.">
  <title>Server Clock | COP 3350</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main>
    <p class="course">COP 3350 · Systems Administration &amp; Programming</p>
    <h1>Server Clock</h1>
    <p class="intro">The lab server’s time in US Eastern Time.</p>

    <div class="clock">
      <time datetime="<?= $serverTime->format('c') ?>">
        <span class="digits"><?= $serverTime->format('g:i:s') ?></span>
        <span class="period"><?= $serverTime->format('A T') ?></span>
        <span class="date"><?= $serverTime->format('l, F j, Y') ?></span>
      </time>
      <form method="get">
        <button type="submit">Refresh time</button>
      </form>
      <p class="note">Select Refresh time to request a new reading from the server.</p>
    </div>
  </main>
</body>
</html>
