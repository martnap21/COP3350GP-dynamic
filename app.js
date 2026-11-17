'use strict';

const $ = (id) => document.getElementById(id);
const token = document.querySelector('meta[name="csrf-token"]').content;
let game = null;
let selected = new Set();
let previousHand = null;
let busy = false;
let refreshing = false;

function message(text, isError = false) {
  $('notice').textContent = text;
  $('notice').classList.toggle('error', isError);
}

async function api(action = null, payload = {}) {
  const options = { credentials: 'same-origin', cache: 'no-store' };
  if (action !== null) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': token };
    options.body = JSON.stringify({ action, ...payload });
  }
  const response = await fetch('api.php', options);
  const body = await response.json();
  if (!response.ok) throw new Error(body.error || 'Request failed.');
  return body;
}

function cardNode(code, selectable = false, index = -1) {
  const card = document.createElement(selectable ? 'button' : 'span');
  card.className = 'card';
  if (selectable) {
    card.type = 'button';
    card.setAttribute('aria-pressed', String(selected.has(index)));
    card.classList.toggle('selected', selected.has(index));
    card.title = 'Click to select or deselect a card';
    card.addEventListener('click', () => {
      if (selected.has(index)) selected.delete(index);
      else if (selected.size < 3) selected.add(index);
      else return message('You can replace at most three cards.', true);
      render();
    });
  }
  if (code === '??') {
    card.classList.add('back');
    card.textContent = '★';
  } else {
    const rank = code[0] === 'T' ? '10' : code[0];
    const suit = { C: '♣', D: '♦', H: '♥', S: '♠' }[code[1]] || '?';
    card.classList.toggle('red', code[1] === 'D' || code[1] === 'H');
    card.textContent = rank + suit;
  }
  return card;
}

function render() {
  if (!game) return;
  const me = game.players.find((p) => p.id === game.you) || null;
  const inLobby = game.phase === 'lobby';
  const key = game.handNumber + ':' + game.phase;
  if (key !== previousHand) {
    selected.clear();
    previousHand = key;
  }

  $('join').hidden = Boolean(me);
  $('game').hidden = false;
  $('join-form').hidden = !inLobby;
  $('join-hint').textContent = inLobby
    ? 'Choose a name (up to 24 characters) to sit at the shared table.'
    : 'A hand is in progress. You can join when the table returns to the lobby.';

  $('round').textContent = game.handNumber ? 'Hand #' + game.handNumber : 'No hands played yet';
  const titles = { lobby: 'Waiting for players', drawing: 'Draw round', showdown: 'Showdown' };
  $('phase-title').textContent = titles[game.phase] || 'Game';
  let description = '';
  if (inLobby) description = 'At least two players are needed. Anyone seated can deal.';
  if (game.phase === 'drawing') description = 'Choose up to three cards to exchange, then confirm your draw. Everyone draws once.';
  if (game.phase === 'showdown') {
    const winners = game.players.filter((p) => game.winners.includes(p.id)).map((p) => p.name);
    description = winners.length ? ('Winner' + (winners.length > 1 ? 's' : '') + ': ' + winners.join(', ') + '.') : 'Round complete.';
  }
  $('phase-description').textContent = description;

  const players = $('players');
  players.replaceChildren();
  game.players.forEach((p) => {
    const tile = document.createElement('article');
    tile.className = 'player';
    if (p.id === game.you) tile.classList.add('self');
    if (game.winners.includes(p.id)) tile.classList.add('winner');

    const name = document.createElement('strong');
    name.textContent = p.name + (p.id === game.you ? ' (you)' : '');
    tile.append(name);
    const details = document.createElement('span');
    details.textContent = p.wins + ' point' + (p.wins === 1 ? '' : 's') +
      (game.phase === 'drawing' ? (p.drawn ? ' · Done drawing' : ' · Choosing cards') : '') +
      (p.handName ? ' · ' + p.handName : '');
    tile.append(details);

    if (game.phase === 'showdown') {
      const cards = document.createElement('div');
      cards.className = 'mini-cards';
      p.hand.forEach((code) => cards.append(cardNode(code)));
      tile.append(cards);
    }
    players.append(tile);
  });

  const hand = $('hand');
  hand.replaceChildren();
  $('hand-heading').textContent = me ? 'Your cards' : 'Spectating';
  if (me && me.hand.length) {
    const selectable = game.phase === 'drawing' && !me.drawn;
    me.hand.forEach((code, i) => hand.append(cardNode(code, selectable, i)));
    $('hand-hint').textContent = selectable
      ? 'Select 0–3 cards to replace, then click Draw selected cards.'
      : (me.drawn && game.phase === 'drawing'
        ? 'Your draw is complete. Waiting for others.'
        : (game.phase === 'showdown' ? (me.handName || 'Showdown complete.') : ''));
  } else {
    $('hand-hint').textContent = me ? 'Waiting for cards to be dealt.' : 'Join during the lobby to receive your own hand.';
  }

  $('start-button').hidden = !me || !inLobby;
  $('start-button').disabled = game.players.length < 2;
  $('draw-button').hidden = !me || game.phase !== 'drawing' || me.drawn;
  $('draw-button').textContent = selected.size ? 'Replace ' + selected.size + ' and finish draw' : 'Keep all five cards';
  $('lobby-button').hidden = !me || inLobby;
  $('leave-button').hidden = !me || !inLobby;
}

async function refresh() {
  if (busy || refreshing) return;
  refreshing = true;
  try {
    game = await api();
    message('Connected · Players update automatically.');
    render();
  } catch (error) {
    message(error.message + ' Try refreshing the page.', true);
  } finally {
    refreshing = false;
  }
}

async function act(action, payload = {}) {
  if (busy) return;
  busy = true;
  try {
    game = await api(action, payload);
    selected.clear();
    message('Updated.');
    render();
  } catch (error) {
    message(error.message, true);
  } finally {
    busy = false;
  }
}

$('join-form').addEventListener('submit', (event) => {
  event.preventDefault();
  act('join', { name: $('player-name').value.trim() });
});
$('start-button').addEventListener('click', () => act('start'));
$('draw-button').addEventListener('click', () => act('draw', { positions: [...selected].sort() }));
$('lobby-button').addEventListener('click', () => {
  if (confirm('Return everyone to the lobby? This ends any unfinished hand.')) act('lobby');
});
$('leave-button').addEventListener('click', () => act('leave'));
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) refresh();
});
refresh();
setInterval(refresh, 1800);
