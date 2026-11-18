import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const html = readFileSync(new URL('../index.html', import.meta.url), 'utf8');
const css = readFileSync(new URL('../style.css', import.meta.url), 'utf8');
const app = readFileSync(new URL('../app.js', import.meta.url), 'utf8');
const initialThemeScript = html.match(/<script>([\s\S]*?)<\/script>/)?.[1];

function initialTheme({ saved = null, systemDark = false, storageThrows = false } = {}) {
  const root = { dataset: {} };
  const context = {
    document: { documentElement: root },
    window: { matchMedia: () => ({ matches: systemDark }) },
    localStorage: {
      getItem: () => {
        if (storageThrows) throw new Error('Browser storage blocked');
        return saved;
      },
    },
  };
  vm.runInNewContext(initialThemeScript, context);
  return root.dataset.theme;
}

test('theme initializes from explicit preference, then OS preference', () => {
  assert.equal(initialTheme({ saved: 'dark' }), 'dark');
  assert.equal(initialTheme({ saved: 'light', systemDark: true }), 'light');
  assert.equal(initialTheme({ systemDark: true }), 'dark');
  assert.equal(initialTheme({ systemDark: false }), 'light');
  assert.equal(initialTheme({ saved: 'invalid', systemDark: true }), 'dark');
});

test('theme initialization tolerates unavailable browser storage', () => {
  assert.equal(initialTheme({ storageThrows: true, systemDark: true }), 'dark');
});

test('light/dark controls and gameplay elements remain wired', () => {
  for (const id of [
    'theme-light', 'theme-dark', 'balance', 'wager', 'result',
    'dealer-total', 'player-total', 'dealer-cards', 'player-cards',
    'deal-form', 'bet-amount', 'deal-button', 'hit-button',
    'stand-button', 'play-actions', 'reset-button', 'no-chips',
  ]) {
    assert.match(html, new RegExp('id="' + id + '"'), 'Missing element: ' + id);
  }
  assert.match(app, /localStorage\.setItem\('blackjack-theme'/);
  assert.match(app, /setAttribute\('aria-pressed'/);
  assert.match(css, /:root\[data-theme="dark"\]/);
  assert.match(css, /:focus-visible/);
  assert.match(html, /aria-live="polite"/);
});
