/**
 * Small, browser-only Blackjack (21) engine.
 * Play chips only. Dealer stands on soft 17.
 * No splitting, doubling, insurance, or cash transactions.
 */

export const STARTING_CHIPS = 500;
export const MIN_BET = 10;

const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
const SUITS = ['♣', '♦', '♥', '♠'];

function randomBelow(max) {
  const range = 0x100000000;
  const limit = range - (range % max);
  const values = new Uint32Array(1);
  let value;
  do {
    crypto.getRandomValues(values);
    value = values[0];
  } while (value >= limit);
  return value % max;
}

export function shuffleDeck(randomIndex = randomBelow) {
  const deck = SUITS.flatMap((suit) => RANKS.map((rank) => ({ rank, suit })));
  for (let i = deck.length - 1; i > 0; i--) {
    const j = randomIndex(i + 1);
    if (!Number.isInteger(j) || j < 0 || j > i) {
      throw new Error('Invalid shuffle index.');
    }
    [deck[i], deck[j]] = [deck[j], deck[i]];
  }
  return deck;
}

export function handValue(cards) {
  let total = 0;
  let highAces = 0;
  for (const card of cards) {
    if (!RANKS.includes(card.rank)) throw new Error('Invalid card rank.');
    if (card.rank === 'A') {
      total += 11;
      highAces++;
    } else if (['J', 'Q', 'K'].includes(card.rank)) {
      total += 10;
    } else {
      total += Number(card.rank);
    }
  }
  while (total > 21 && highAces > 0) {
    total -= 10;
    highAces--;
  }
  return { total, soft: highAces > 0 };
}

export function isBlackjack(cards) {
  return cards.length === 2 && handValue(cards).total === 21;
}

export function newGame() {
  return {
    phase: 'ready',
    bankroll: STARTING_CHIPS,
    wager: 0,
    player: [],
    dealer: [],
    deck: [],
    outcome: null,
    message: 'Choose your bet and deal.',
  };
}

function draw(state, who) {
  if (!state.deck.length) throw new Error('The deck is empty.');
  state[who].push(state.deck.pop());
}

function settle(state, outcome, message) {
  const returned = outcome === 'blackjack' ? state.wager * 2.5
    : outcome === 'win' ? state.wager * 2
    : outcome === 'push' ? state.wager : 0;
  return {
    ...state,
    bankroll: state.bankroll + returned,
    phase: 'finished',
    outcome,
    message,
  };
}

export function deal(game, wager, shuffled = shuffleDeck()) {
  if (game.phase === 'playing') throw new Error('Finish this hand first.');
  if (!Number.isInteger(wager) || wager < MIN_BET || wager % 10 !== 0) {
    throw new Error('Bet at least 10 chips, in multiples of 10.');
  }
  if (wager > game.bankroll) throw new Error('Not enough chips for that bet.');
  if (!Array.isArray(shuffled) || shuffled.length < 4) throw new Error('Not enough cards.');

  const state = {
    ...game,
    bankroll: game.bankroll - wager,
    wager,
    player: [],
    dealer: [],
    deck: [...shuffled],
    phase: 'playing',
    outcome: null,
    message: 'Hit for another card or stand to finish.',
  };
  draw(state, 'player');
  draw(state, 'dealer');
  draw(state, 'player');
  draw(state, 'dealer');

  const playerNatural = isBlackjack(state.player);
  const dealerNatural = isBlackjack(state.dealer);
  if (playerNatural && dealerNatural) {
    return settle(state, 'push', 'Both have blackjack. Push — bet returned.');
  }
  if (playerNatural) {
    return settle(state, 'blackjack', 'Blackjack! You win 3:2.');
  }
  if (dealerNatural) {
    return settle(state, 'lose', 'Dealer has blackjack. You lose.');
  }
  return state;
}

export function hit(game) {
  if (game.phase !== 'playing') throw new Error('Deal a hand first.');
  const state = {
    ...game,
    deck: [...game.deck],
    player: [...game.player],
    dealer: [...game.dealer],
  };
  draw(state, 'player');
  const total = handValue(state.player).total;
  if (total > 21) return settle(state, 'lose', 'Bust! You went over 21.');
  if (total === 21) return stand(state);
  return state;
}

export function stand(game) {
  if (game.phase !== 'playing') throw new Error('Deal a hand first.');
  const state = {
    ...game,
    deck: [...game.deck],
    player: [...game.player],
    dealer: [...game.dealer],
  };
  // Dealer must hit below 17, and stands on all 17s (including soft 17).
  while (handValue(state.dealer).total < 17) {
    draw(state, 'dealer');
  }
  const dealerTotal = handValue(state.dealer).total;
  const playerTotal = handValue(state.player).total;
  if (dealerTotal > 21) return settle(state, 'win', 'Dealer busts! You win.');
  if (playerTotal > dealerTotal) return settle(state, 'win', 'You win!');
  if (playerTotal < dealerTotal) return settle(state, 'lose', 'Dealer wins this hand.');
  return settle(state, 'push', 'Push! It is a tie — bet returned.');
}

export function resetChips(game) {
  if (game.phase === 'playing') throw new Error('Finish this hand first.');
  return newGame();
}
