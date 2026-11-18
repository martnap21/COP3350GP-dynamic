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

// The selected theme is stored on this device, not on the server.
function setTheme(theme, save = false) {
  const selected = theme === 'dark' ? 'dark' : 'light';
  document.documentElement.dataset.theme = selected;
  byId('theme-light').setAttribute('aria-pressed', String(selected === 'light'));
  byId('theme-dark').setAttribute('aria-pressed', String(selected === 'dark'));
  if (save) {
    try {
      localStorage.setItem('blackjack-theme', selected);
    } catch {
      // Restricted browser storage should not prevent the theme from changing.
    }
  }
}
setTheme(document.documentElement.dataset.theme);
byId('theme-light').addEventListener('click', () => setTheme('light', true));
byId('theme-dark').addEventListener('click', () => setTheme('dark', true));

function renderCard(card, hidden = false) {
  const element = document.createElement('span');
  element.className = 'card';

  if (hidden) {
    element.classList.add('card-back');
    element.textContent = '21';
    element.setAttribute('aria-label', 'Face-down card');
    return element;
  }

  const rank = document.createElement('span');
  rank.className = 'card-rank';
  rank.textContent = card.rank;

  const suit = document.createElement('span');
  suit.className = 'card-suit';
  suit.textContent = card.suit;

  element.append(rank, suit);
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
    empty.textContent = 'No cards dealt';
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
    : playing ? '—' : String(handValue(game.dealer).total);
  byId('player-total').textContent = !cardsDealt ? '—'
    : String(handValue(game.player).total);

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
  if (confirm('Reset your chips to 500?')) act(resetChips);
});

render();
