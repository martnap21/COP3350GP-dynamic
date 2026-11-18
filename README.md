# Texas Hold'em Group Lab

A small, **multiplayer Texas Hold'em** application for the private site `https://poker.lab.test`.

## Features and rules
- 2–6 people at **one live shared table** (1.8-second updates, no WebSockets).
- Each player gets **two secret hole cards**; the board reveals a **flop (3), turn (1), and river (1)**.
- Preflop, flop, turn, and river betting rounds with **Fold, Check, Call, and Raise/Bet**.
- **Dealer rotates** each new hand, including proper heads-up blind and action order.
- **5-chip small blind, 10-chip big blind**; initial stack **500 play chips** per player.
- **Simplified fixed-size raises of 10 chips**, with at most three raises per betting round.
- At showdown, compare the **best five-card hand from seven available cards**. Ties split the pot with odd chips awarded clockwise after the dealer.
- Player sessions are separate; opponents' hole cards and the deck remain secret until a normal showdown. Folded cards are not shown.
- Players can refill their **play chips in the lobby**; no real money, purchases, cashouts, or payments.

**Scope limitation:** This is a classroom Hold'em MVP, **not standard no-limit tournament poker**. All-ins and side pots are not implemented; if you cannot afford a call, you must fold. It has one shared table, no authentication, turn timer, or moderation. Any seated player can reset an abandoned hand by returning to the lobby. It is intended for a trusted lab Tailscale network only.

## Technology
Apache + PHP 8.0+, vanilla JavaScript and CSS. No SQL database, Composer packages, or Node server. Sessions and CSRF tokens protect game actions. Game state is saved in a file with a process-level lock on the server; only the current actor can make a betting move.

## Local test on macOS
Install PHP 8+ (e.g. `brew install php`), then:

```sh
git clone https://github.com/martnap21/COP3350GP-dynamic.git poker-test
cd poker-test
mkdir -p "$HOME/poker-test-data"
POKER_STATE_FILE="$HOME/poker-test-data/state.json" php -S 127.0.0.1:8000
```

Open `http://127.0.0.1:8000` in a normal and private browser window. Join as different players, deal, and act in turn.

To test automatically in a second terminal:

```sh
php tests/hand-rankings.php
bash tests/multiplayer-smoke.sh
```

## Deployment (coordinate with the server administrator)
1. Verify the Apache HTTPS vhost that serves `poker.lab.test` (e.g. `sudo apache2ctl -S` on Ubuntu or `sudo httpd -S` on RHEL), PHP availability (`php -v`), and its `DocumentRoot`. Keep existing DNS, CA, TLS, reverse proxy, and any protected `/admin/` route unchanged.
2. Copy **`index.php`, `api.php`, `lib.php`, `app.js`, `style.css`** into the configured poker site document root. Do not publish `tests/`, `.github/`, credentials, state files, or server configuration.
3. Create a **private writable state directory outside the web root**. On Ubuntu/Debian (usually `www-data`):
   ```sh
   sudo install -d -o www-data -g www-data -m 0700 /var/lib/poker-lab
   ```
   On RHEL (usually `apache`) with SELinux:
   ```sh
   sudo install -d -o apache -g apache -m 0700 /var/lib/poker-lab
   sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/lib/poker-lab(/.*)?'
   sudo restorecon -Rv /var/lib/poker-lab
   ```
   Check the actual PHP worker user before proceeding, and investigate permission/SELinux denials rather than disabling protections.
4. **State format changed from five-card draw.** If the earlier game has been deployed, back up its state and lock file, then remove/rename the old state file *before* switching the server to Hold'em. Example, once the site is stopped:
   ```sh
   sudo mv /var/lib/poker-lab/state.json /var/lib/poker-lab/state-five-card-backup.json
   ```
   Only do this if the original file exists, during coordinated maintenance. Do **not** delete state files blindly.
5. Existing HTTPS does not automatically update from GitHub. Deploy a reviewed revision via your group's agreed workflow. Confirm `php -l` on changed PHP files, then perform two-browser Tailscale testing after deployment.

## Repository layout
- `lib.php`: deck, ranking of five/seven-card hands, betting rules, chips, turn order and file-locked state
- `api.php`: session-bound actions, server-side validation, private/public information
- `index.php`: the page and per-session CSRF token
- `app.js`: live display and betting buttons
- `style.css`: responsive game table
- `tests/hand-rankings.php`: ranking and engine assertions
- `tests/multiplayer-smoke.sh` + `tests/holdem-http-smoke.py`: independent-session HTTP game test

## Known limitations and planned improvements
- No all-in, side pots, variable-size raises, or complex betting reopen rules. These require an additional rules engine iteration.
- No player accounts/host permissions, reconnect identity other than browser cookies, table/lobby codes, or automatic idle-player recovery.
- One state file is safe for one Apache server/filesystem, **not horizontally scaled multiple servers**.
- No integrated computer opponents. The smoke test acts as two dummy browser clients.
- Refill play chips in lobby to continue when stacks are low. An incomplete hand can be cancelled by any seated player.
- Do not expose this classroom prototype to the public internet.
