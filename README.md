# Blackjack 21 — private group lab

A very small **single-player blackjack game against a computer dealer**, replacing the earlier poker experiment.

## Play
- Begin with **500 play chips**, choose a wager (minimum 10, in increments of 10), and press **Deal**.
- Use **Hit** to draw another card or **Stand** to let the dealer play.
- Aces count as 1 or 11, whichever is best without busting.
- Dealer draws below 17 and **stands on all 17s**, including soft 17.
- A regular win pays **1:1**, a two-card natural blackjack pays **3:2**, and ties return the wager. Losses forfeit the wager.
- **Reset play chips** restores your balance to 500 for another practice session.
- This demo has **no actual money, cashouts, payments, user accounts, split, double down, or insurance**.

## Run locally on your Mac
No database, PHP, Tailscale, GitHub deployment, or server-side game files are needed. Use any simple local web server to serve the repository (module imports require HTTP):

```sh
cd ~/Developer/poker-test
git pull origin main
php -S 127.0.0.1:8000
```

Open **http://127.0.0.1:8000**. If port 8000 is already in use, stop the previous PHP test server with Ctrl+C first.

**Important:** No `POKER_STATE_FILE` variable is used for blackjack. The game runs in the browser and refresh starts a new 500-chip session. Old poker state files can be left untouched.

## Files
- `index.html`: game page
- `style.css`: responsive styling
- `blackjack.js`: deck, blackjack rules and payouts (ES module)
- `app.js`: UI, betting inputs, hit and stand buttons
- `tests/blackjack.test.js`: no-dependency rules and payout checks

## Automated tests
Requires Node.js 22+ for the built-in test runner and browser-compatible `crypto`:

```sh
node --test
```

GitHub Actions runs the same tests and JavaScript syntax checks.

## Deploy to the lab site
The group's existing Apache vhost for `poker.lab.test` can serve this game as **static files**. Copy only `index.html`, `style.css`, `blackjack.js` and `app.js` to the vhost document root after confirming the exact path. Remove or stop routing to any old `index.php` entry point: leaving `index.php` alongside `index.html` may cause Apache to serve the old poker game instead, depending on `DirectoryIndex`.

Keep TLS, certificates, existing `/admin/` protection, and the informational site configuration intact. Do not delete server infrastructure or private CA data.

**This code is client-side**: players can inspect or change their chip balance using browser developer tools. That is acceptable for a local classroom toy, but not for real-money or competitive gambling.
