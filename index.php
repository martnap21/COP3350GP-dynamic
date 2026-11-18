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
if (!isset($_SESSION['poker_csrf'])) $_SESSION['poker_csrf'] = bin2hex(random_bytes(24));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['poker_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="Play live Texas Hold'em with classmates on our private lab network.">
  <title>Texas Hold'em | Group Lab</title>
  <link rel="stylesheet" href="style.css">
  <script src="app.js" defer></script>
</head>
<body>
  <main>
    <header class="top">
      <div>
        <p class="eyebrow">COP 3350 · Private group lab</p>
        <h1>Texas Hold'em</h1>
        <p class="subtitle">One shared table, 2–6 classmates, play chips only. Blinds 5/10, fixed 10-chip raises.</p>
      </div>
      <a class="back" href="https://info.lab.test">Lab info</a>
    </header>

    <p id="notice" class="notice" role="status" aria-live="polite">Connecting to the poker server...</p>
    <section id="join" class="panel" hidden>
      <h2>Join the table</h2>
      <p id="join-hint">Choose a name to join the next hand.</p>
      <form id="join-form">
        <label for="player-name">Display name</label>
        <div class="join-controls">
          <input id="player-name" name="name" maxlength="24" autocomplete="nickname" required placeholder="Your name">
          <button type="submit">Join table</button>
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
        <div class="table-stats">
          <span id="pot-label">Pot: 0 chips</span>
          <span id="bet-label">Current bet: 0</span>
        </div>
        <h3>Players</h3>
        <div id="players" class="players"></div>
        <div class="board-area">
          <h3>Community cards</h3>
          <div id="board" class="cards" aria-label="Community cards"></div>
        </div>
        <div class="hand-area">
          <h3 id="hand-heading">Your hole cards</h3>
          <p id="hand-hint" class="hint">Join the table to play.</p>
          <div id="hand" class="cards" aria-label="Your hole cards"></div>
        </div>
      </div>

      <div class="controls" aria-label="Poker actions">
        <button id="start-button" hidden>Deal new hand</button>
        <button id="fold-button" class="secondary" hidden>Fold</button>
        <button id="check-button" hidden>Check</button>
        <button id="call-button" hidden>Call</button>
        <button id="raise-button" hidden>Raise +10</button>
        <button id="refill-button" class="secondary" hidden>Refill play chips</button>
        <button id="leave-button" class="secondary" hidden>Leave table</button>
      </div>
      <p class="hint">Play the preflop, flop, turn, and river betting rounds. The best five-card hand from your two hole cards and five community cards wins. This simplified lab version uses a maximum of three fixed-size raises per round and does not support all-ins or side pots.</p>
    </section>
    <footer>Private classroom demo · No real money · Each browser is a separate seat</footer>
  </main>
</body>
</html>
