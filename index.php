<?php
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
session_name('POKERLABSESSID');
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();
if (!isset($_SESSION['poker_csrf'])) {
    $_SESSION['poker_csrf'] = bin2hex(random_bytes(24));
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['poker_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="Play a simple live five-card draw poker game with classmates.">
  <title>Poker Table | Group Lab</title>
  <link rel="stylesheet" href="style.css">
  <script src="app.js" defer></script>
</head>
<body>
  <main>
    <header class="top">
      <div>
        <p class="eyebrow">COP 3350 · Private group lab</p>
        <h1>Poker Table</h1>
        <p class="subtitle">Five-card draw with your classmates. No real money, no downloads, one shared table.</p>
      </div>
      <a class="back" href="https://info.lab.test">Lab info</a>
    </header>

    <p id="notice" class="notice" role="status" aria-live="polite">Connecting to the game server...</p>
    <section id="join" class="panel" hidden>
      <h2>Join the table</h2>
      <p id="join-hint">Enter a name to join the next hand.</p>
      <form id="join-form">
        <label for="player-name">Display name</label>
        <div class="join-controls">
          <input id="player-name" name="name" maxlength="24" autocomplete="nickname" required placeholder="Your name">
          <button type="submit" id="join-button">Join table</button>
        </div>
      </form>
    </section>

    <section id="game" hidden>
      <div class="game-head">
        <div>
          <p class="eyebrow" id="round">Waiting for a hand</p>
          <h2 id="phase-title">Lobby</h2>
          <p id="phase-description">Waiting for players.</p>
        </div>
        <button id="lobby-button" class="secondary" hidden>Return to lobby</button>
      </div>

      <div class="felt">
        <h3>Players</h3>
        <div id="players" class="players"></div>
        <div class="hand-area">
          <h3 id="hand-heading">Your cards</h3>
          <p id="hand-hint" class="hint">Join a table to play.</p>
          <div id="hand" class="cards" aria-label="Your hand"></div>
        </div>
      </div>

      <div class="controls">
        <button id="start-button" hidden>Deal cards</button>
        <button id="draw-button" hidden>Draw selected cards</button>
        <button id="leave-button" class="secondary" hidden>Leave table</button>
      </div>
      <p class="hint">Rules: 2–6 players. Each player gets five cards and may replace up to three once. When everyone finishes, the highest poker hand wins one point. Ties award each winner a point. Click cards to select replacements.</p>
    </section>
    <footer>Private classroom demo · Refreshing keeps your seat in this browser · No real-money betting</footer>
  </main>
</body>
</html>
