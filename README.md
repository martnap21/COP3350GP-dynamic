# Group Poker Lab

A minimal **multiplayer five-card draw** application for the private site `https://poker.lab.test`.

## Features
- One shared table for **2–6 players** using separate browsers/devices over Tailscale.
- Join with a display name; no accounts and **no real-money gambling**.
- Server deals five cards to every player; each player can replace **zero to three** cards once.
- Once everyone has drawn, hands are revealed, evaluated, and a winner is announced. Ties each receive a point.
- Everyone sees the same players and round status via **1.8-second polling**. No WebSocket infrastructure needed.
- Hidden hands and the remaining deck stay on the server until showdown.
- Anyone at the table can deal, reset an unfinished hand, or return to the lobby.

This is intentionally **not full Texas Hold'em**: there is no betting, money, blinds, or side pots. It is a shared five-card draw classroom MVP.

## Stack
Apache with PHP 8.0+, vanilla JavaScript and CSS. No Composer packages, JavaScript build step, SQL server, or third-party services. State is stored in a locked JSON file **outside the Apache document root**. Player identity is a PHP session cookie and POST requests use a CSRF token.

## Server deployment (coordinate with the server administrator)
1. Confirm which existing Apache HTTPS vhost serves `poker.lab.test` and record its `DocumentRoot`. Keep the current DNS, Tailscale, Apache virtual-host, and TLS/CA configuration intact.
2. Verify that the PHP handler and session storage work: `php -v` and `sudo apachectl -t` (or `sudo httpd -t` on RHEL).
3. Copy **`index.php`, `api.php`, `lib.php`, `app.js`, and `style.css`** from the reviewed repository revision into that vhost's document root. Do not copy `tests/` or `.github/` into the public web directory.
4. Create the separate game-state directory for the PHP worker, typically `apache` on RHEL:
   ```sh
   sudo install -d -o apache -g apache -m 0700 /var/lib/poker-lab
   sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/lib/poker-lab(/.*)?'
   sudo restorecon -Rv /var/lib/poker-lab
   ```
   If `semanage` is unavailable, coordinate installing `policycoreutils-python-utils`. The administrator must verify which user PHP actually runs as, and set owner/group accordingly. Check SELinux denials rather than disabling SELinux.
5. Keep `/var/lib/poker-lab` **outside the website document root**. `POKER_STATE_FILE` can override the default path `/var/lib/poker-lab/state.json` if provided through the PHP service environment.
6. Reload Apache/PHP-FPM only if necessary after checking the current configuration. GitHub pushes are **not** automatic deployments unless the team has explicitly configured one.

## Test before release
```sh
php -l lib.php
php -l api.php
php -l index.php
php tests/hand-rankings.php
curl -I https://poker.lab.test
curl https://poker.lab.test
```
For the actual multiplayer test, use two different browsers (or a private window) **on separate Tailscale-connected devices**. Join with two names, deal, select up to three cards, finish both draws, verify the showdown and winners, and return to the lobby. Use browser developer tools to verify `api.php` polls succeed.

## Operational limitations
- **One table per deployment**; no authentication or moderation. Appropriate for a small, trusted classroom tailnet, not a public internet service.
- Players are tied to a browser session, so losing cookies may strand a player. A seated player can reset the game to the lobby; restarting PHP or the VM does not itself erase the game-state file.
- File-backed locking is intended for a **single server filesystem**. Don't run multiple PHP servers with separate state directories or deploy the state file into Git.
- Any member can reset an active hand to recover from an abandoned session. There are no host-only controls.
- Do not expose the administrative directory, system logs, password files, or CA private keys through either web root. Do not store passwords in the repositories.
- The server's existing protected `/admin/` path is managed separately. This application does not replace it.

## Repository layout
- `index.php`: game page and CSRF token
- `api.php`: validated game actions, session handling, private/public state
- `lib.php`: deck, hand evaluation, locked persistence
- `app.js`: multiplayer polling and interface
- `style.css`: responsive table design
- `tests/hand-rankings.php`: no-dependency game-engine tests
