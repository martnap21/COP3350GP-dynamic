import test from 'node:test';
import assert from 'node:assert/strict';
import {
  STARTING_CHIPS, MIN_BET, shuffleDeck, handValue, isBlackjack,
  newGame, deal, hit, stand, resetChips,
} from '../blackjack.js';

const c = (rank, suit = '♠') => ({ rank, suit });
// The engine pops cards from the end of a shuffled deck.
const arranged = (...drawOrder) => [...drawOrder].reverse();

test('standard 52-card deck contains no duplicates', () => {
  const deck = shuffleDeck();
  assert.equal(deck.length, 52);
  assert.equal(new Set(deck.map(({rank, suit}) => rank + suit)).size, 52);
});

test('scores soft and hard aces correctly', () => {
  assert.deepEqual(handValue([c('A'), c('6')]), { total: 17, soft: true });
  assert.deepEqual(handValue([c('A'), c('A'), c('9')]), { total: 21, soft: true });
  assert.deepEqual(handValue([c('A'), c('A'), c('9'), c('8')]), { total: 19, soft: false });
  assert.equal(isBlackjack([c('A'), c('K')]), true);
  assert.equal(isBlackjack([c('A'), c('5'), c('5')]), false);
});

test('only valid, affordable play-chip bets are accepted', () => {
  const g = newGame();
  assert.equal(g.bankroll, STARTING_CHIPS);
  assert.equal(MIN_BET, 10);
  for (const amount of [-10, 0, 5, 15, 501, 510, 10.5, NaN]) {
    assert.throws(() => deal(g, amount));
  }
  assert.equal(g.bankroll, STARTING_CHIPS);
});

test('player natural blackjack pays 3:2', () => {
  const g = deal(newGame(), 20, arranged(c('A'), c('9'), c('K'), c('7')));
  assert.equal(g.outcome, 'blackjack');
  assert.equal(g.bankroll, 530);
  assert.equal(g.phase, 'finished');
});

test('two naturals push and dealer natural wins otherwise', () => {
  const both = deal(newGame(), 20, arranged(c('A'), c('A', '♥'), c('K'), c('Q', '♦')));
  assert.equal(both.outcome, 'push');
  assert.equal(both.bankroll, 500);
  const dealer = deal(newGame(), 20, arranged(c('10'), c('A'), c('8'), c('K')));
  assert.equal(dealer.outcome, 'lose');
  assert.equal(dealer.bankroll, 480);
});

test('player bust loses wager', () => {
  const g = deal(newGame(), 20, arranged(c('10'), c('9'), c('8'), c('7'), c('6')));
  const end = hit(g);
  assert.equal(end.outcome, 'lose');
  assert.equal(end.bankroll, 480);
  assert.equal(handValue(end.player).total, 24);
});

test('dealer bust awards regular 1:1 win', () => {
  const g = deal(newGame(), 20, arranged(c('10'), c('9'), c('8'), c('7'), c('10')));
  const end = stand(g);
  assert.equal(end.outcome, 'win');
  assert.equal(end.bankroll, 520);
  assert.equal(handValue(end.dealer).total, 26);
  assert.equal(g.phase, 'playing', 'original state stays unchanged');
});

test('regular tie returns wager', () => {
  const g = deal(newGame(), 50, arranged(c('10'), c('10', '♥'), c('Q'), c('J', '♦')));
  const end = stand(g);
  assert.equal(end.outcome, 'push');
  assert.equal(end.bankroll, 500);
});

test('dealer stands on soft 17', () => {
  const g = deal(newGame(), 10, arranged(c('10'), c('A'), c('8'), c('6')));
  const end = stand(g);
  assert.equal(end.dealer.length, 2);
  assert.equal(end.bankroll, 510);
});

test('hit to 21 automatically resolves dealer turn', () => {
  const g = deal(newGame(), 10, arranged(c('8'), c('10'), c('6'), c('7'), c('7')));
  const end = hit(g);
  assert.equal(handValue(end.player).total, 21);
  assert.equal(end.phase, 'finished');
  assert.equal(end.outcome, 'win');
});

test('second round and reset chips work without persistence', () => {
  const first = deal(newGame(), 20, arranged(c('10'), c('9'), c('8'), c('7'), c('6')));
  const lost = hit(first);
  assert.equal(lost.bankroll, 480);
  const second = deal(lost, 20, arranged(c('A'), c('9'), c('K'), c('7')));
  assert.equal(second.bankroll, 510);
  assert.throws(() => resetChips(first));
  assert.deepEqual(resetChips(second), newGame());
});
