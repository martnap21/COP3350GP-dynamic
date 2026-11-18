import {
  MIN_BET, deal, handValue, hit, newGame, resetChips, stand,
} from './blackjack.js';

const byId = (id) => document.getElementById(id);
const gameForm = byId('deal-form');
const betInput = byId('bet-amount');
const dealButton = byId('deal-button');
const resetButton = byId('reset-button');
const hitButton = byId('hit-button');
const standButton = byId('stand-button');
let game = newGame();

function renderCard(card, hidden = false) {
  const element = document.createElement('span');
  element.className = 'card';
  if (hidden) {
    element.classList.add('card-back');
    element.textContent = '★';
    element.setAttribute('aria-label', 'Face down card');
    return element;
  }
  element.textContent = card.rank + card.suit;
  element.setAttribute('aria-label', card.rank + ' of ' + ({
    '♣': 'clubs', '♦': 'diamonds', '♥': 'hearts', '♠': 'spades',
  }[card.suit] || 'unknown'));
  if (card.suit === '♥' || card.suit === '♦') element.classList.add('red');
  return element;
}

function renderCards(target, cards, hideSecond = false) {
  target.replaceChildren();
  for (let i = 0; i < cards.length; i++) {
    target.append(renderCard(cards[i], hideSecond && i === 1));
  }
  if (!cards.length) {
    const empty = document.createElement('span');
    empty.className = 'placeholder';
    empty.textContent = 'Cards appear here';
    target.append(empty);
  }
}

function render() {
  const playing = game.phase === 'playing';
  const cardsDealt = game.player.length > 0;
  byId('balance').textContent = String(game.bankroll);
  byId('wager').textContent = String(game.wager);
  byId('result').textContent = game.message;
  byId('result').dataset.outcome = game.outcome || '';
  renderCards(byId('dealer-cards'), game.dealer, playing);
  renderCards(byId('player-cards'), game.player);

  byId('dealer-total').textContent = !cardsDealt ? '—'
    : playing ? '?'
      : String(handValue(game.dealer).total);
  byId('player-total').textContent = !cardsDealt
    ? '—' : String(handValue(game.player).total);

  gameForm.hidden = playing;
  byId('play-actions').hidden = !playing;
  resetButton.hidden = playing;
  dealButton.disabled = game.bankroll < MIN_BET;
  betInput.disabled = game.bankroll < MIN_BET;
  betInput.max = String(Math.floor(game.bankroll / 10) * 10);
  const requestedBet = Number(betInput.value);
  if (!Number.isFinite(requestedBet) || requestedBet > game.bankroll) {
    betInput.value = String(Math.max(MIN_BET, Math.floor(game.bankroll / 10) * 10));
  }
  byId('no-chips').hidden = game.bankroll >= MIN_BET || playing;
}

function act(operation) {
  try {
    game = operation(game);
    render();
  } catch (error) {
    byId('result').textContent = error.message;
    byId('result').dataset.outcome = 'error';
  }
}

gameForm.addEventListener('submit', (event) => {
  event.preventDefault();
  act((state) => deal(state, Number(betInput.value)));
});
hitButton.addEventListener('click', () => act(hit));
standButton.addEventListener('click', () => act(stand));
resetButton.addEventListener('click', () => {
  if (confirm('Reset your play chips to 500 and clear this round?')) act(resetChips);
});

render();
