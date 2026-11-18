'use strict';

const $ = (id) => document.getElementById(id);
const token = document.querySelector('meta[name="csrf-token"]').content;
const activeStreets = ['preflop', 'flop', 'turn', 'river'];
let game = null;
let busy = false;
let refreshing = false;

function message(text, error = false) {
  $('notice').textContent = text;
  $('notice').classList.toggle('error', error);
}

async function api(action = null, payload = {}) {
  const options = { cache: 'no-store', credentials: 'same-origin' };
  if (action !== null) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': token };
    options.body = JSON.stringify({ action, ...payload });
  }
  const response = await fetch('api.php', options);
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Request failed.');
  return data;
}

function cardNode(code, compact = false) {
  const element = document.createElement('span');
  element.className = 'card';
  if (compact) element.classList.add('compact');
  if (!code || code === '??') {
    element.textContent = '★';
    element.classList.add('back');
    return element;
  }
  const rank = code[0] === 'T' ? '10' : code[0];
  const suit = { C: '♣', D: '♦', H: '♥', S: '♠' }[code[1]] || '?';
  if (code[1] === 'D' || code[1] === 'H') element.classList.add('red');
  element.textContent = rank + suit;
  return element;
}

function render() {
  if (!game) return;
  const me = game.players.find((p) => p.id === game.you) || null;
  const inLobby = game.phase === 'lobby';
  const betting = activeStreets.includes(game.phase);
  const myTurn = betting && game.actor === game.you && me && me.status === 'active';

  $('join').hidden = Boolean(me);
  $('game').hidden = false;
  $('join-form').hidden = !inLobby;
  $('join-hint').textContent = inLobby
    ? 'Enter a name to join the table and receive 500 play chips.'
    : 'A hand is underway. Join when the players return to the lobby.';

  const labels = {
    lobby: 'Lobby', preflop: 'Preflop', flop: 'Flop',
    turn: 'Turn', river: 'River', showdown: 'Showdown',
  };
  $('phase-title').textContent = labels[game.phase] || 'Game';
  $('round').textContent = game.handNumber
    ? 'Hand #' + game.handNumber : 'No hands played yet';
  $('pot-label').textContent = 'Pot: ' + game.pot + ' chips';
  $('bet-label').textContent = 'Current bet: ' + game.currentBet;

  const actor = game.players.find((p) => p.id === game.actor);
  const winners = game.players.filter((p) => game.winners.includes(p.id));
  let description = 'At least two players are needed to start a hand.';
  if (betting) {
    description = myTurn ? 'Your turn. Choose a betting action.'
      : actor ? 'Waiting for ' + actor.name + ' to act.' : 'Waiting for next round.';
  } else if (game.phase === 'showdown') {
    description = winners.length
      ? (winners.length === 1 ? 'Winner: ' : 'Tie: ') +
        winners.map((p) => p.name).join(', ') +
        (game.showdownByFold ? ' (all opponents folded).' : '.')
      : 'Hand complete.';
  }
  $('phase-description').textContent = description;

  const players = $('players');
  players.replaceChildren();
  for (const p of game.players) {
    const tile = document.createElement('article');
    tile.className = 'player';
    if (p.id === game.you) tile.classList.add('self');
    if (p.id === game.actor) tile.classList.add('acting');
    if (game.winners.includes(p.id)) tile.classList.add('winner');

    const heading = document.createElement('strong');
    const tags = [
      p.id === game.dealer ? 'Dealer' : '',
      p.id === game.smallBlind ? 'SB' : '',
      p.id === game.bigBlind ? 'BB' : '',
    ].filter(Boolean).join(', ');
    heading.textContent = p.name +
      (p.id === game.you ? ' (you)' : '') + (tags ? ' · ' + tags : '');
    tile.append(heading);

    const info = document.createElement('span');
    const parts = [p.stack + ' chips'];
    if (betting) parts.push('Bet: ' + p.bet);
    if (p.status === 'folded') parts.push('Folded');
    if (p.lastAction) parts.push(p.lastAction);
    if (p.handName) parts.push(p.handName);
    if (p.id === game.actor) parts.push('TURN');
    info.textContent = parts.join(' · ');
    tile.append(info);

    if (betting || game.phase === 'showdown') {
      const cards = document.createElement('div');
      cards.className = 'mini-cards';
      for (let i = 0; i < p.cardCount; i++) {
        cards.append(cardNode(p.hand[i] || '??', true));
      }
      tile.append(cards);
    }
    players.append(tile);
  }

  const board = $('board');
  board.replaceChildren();
  if (inLobby) {
    for (let i = 0; i < 5; i++) board.append(cardNode('??'));
  } else {
    game.board.forEach((code) => board.append(cardNode(code)));
    if (game.phase !== 'showdown') {
      for (let i = game.board.length; i < 5; i++) board.append(cardNode('??'));
    }
  }

  const hand = $('hand');
  hand.replaceChildren();
  if (me && me.hand.length) me.hand.forEach((code) => hand.append(cardNode(code)));
  else if (me && !inLobby) {
    for (let i = 0; i < 2; i++) hand.append(cardNode('??'));
  }
  $('hand-hint').textContent = !me
    ? 'Join the table during the lobby to get two private cards.'
    : inLobby ? 'Your private cards will appear when a hand begins.'
    : me.status === 'folded' ? 'You folded this hand.'
    : game.phase === 'showdown'
      ? (me.handName || (game.showdownByFold ? 'Hand ended by a fold.' : 'Round complete.'))
      : myTurn ? 'Your turn to act.' : 'Only you can see these two cards.';

  $('start-button').hidden = !me || !inLobby;
  $('start-button').disabled = game.players.length < 2;
  $('fold-button').hidden = !myTurn;
  $('check-button').hidden = !myTurn || game.toCall !== 0;
  $('call-button').hidden = !myTurn || game.toCall <= 0;
  $('call-button').textContent = 'Call ' + game.toCall;
  $('call-button').disabled = Boolean(me && me.stack < game.toCall);
  $('raise-button').hidden = !myTurn || game.raisesRemaining === 0;
  $('raise-button').textContent = (game.currentBet ? 'Raise' : 'Bet') + ' +10';
  $('raise-button').disabled = Boolean(me && me.stack < game.toCall + 10);
  $('refill-button').hidden = !me || !inLobby || me.stack >= 500;
  $('lobby-button').hidden = !me || inLobby;
  $('leave-button').hidden = !me || !inLobby;
}

async function refresh() {
  if (busy || refreshing) return;
  refreshing = true;
  try {
    game = await api();
    message('Connected · Table updates automatically.');
    render();
  } catch (error) {
    message(error.message + ' Refresh and try again.', true);
  } finally {
    refreshing = false;
  }
}

async function act(action, payload = {}) {
  if (busy) return;
  busy = true;
  try {
    game = await api(action, payload);
    message('Action completed.');
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
for (const action of ['fold', 'check', 'call', 'raise', 'refill', 'leave']) {
  $(action + '-button').addEventListener('click', () => act(action));
}
$('lobby-button').addEventListener('click', () => {
  if (game && activeStreets.includes(game.phase) &&
      !confirm('End this unfinished hand for everyone and return to the lobby?')) return;
  act('lobby');
});
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) refresh();
});
refresh();
setInterval(refresh, 1800);
