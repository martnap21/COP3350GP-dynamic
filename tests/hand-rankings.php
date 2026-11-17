<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

function verify(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
}

$hands = [
    'straight flush' => ['9H','TH','JH','QH','KH'],
    'four of a kind' => ['9C','9D','9H','9S','2C'],
    'full house' => ['KC','KH','KS','2D','2S'],
    'flush' => ['2H','4H','8H','JH','KH'],
    'straight' => ['5C','6S','7H','8D','9C'],
    'three of a kind' => ['6C','6D','6H','9S','2C'],
    'two pair' => ['QC','QD','2C','2D','9H'],
    'one pair' => ['AC','AD','5H','8C','2D'],
    'high card' => ['AC','KD','9S','7H','2C'],
];
$previous = null;
foreach ($hands as $label => $cards) {
    $score = poker_evaluate($cards);
    verify(poker_hand_name($score) === ucfirst($label), 'Correct category: ' . $label);
    if ($previous !== null) verify(poker_compare($previous, $score) > 0, 'Ranking order: ' . $label);
    $previous = $score;
}

verify(poker_compare(poker_evaluate(['AC','2D','3H','4S','5C']), poker_evaluate(['2C','3D','4H','5S','6C'])) < 0,
    'Wheel straight ranks below six-high straight.');
verify(poker_compare(poker_evaluate(['AH','AD','KH','9H','7H']), poker_evaluate(['AS','AC','QH','9S','7C'])) > 0,
    'Pair kicker comparison works.');
verify(poker_compare(poker_evaluate(['2C','2D','5H','5S','AH']), poker_evaluate(['2H','2S','5D','5C','AD'])) === 0,
    'Equal hands tie.');
$deck = poker_deck();
verify(count($deck) === 52 && count(array_unique($deck)) === 52, 'Deck has 52 unique cards.');
$state = poker_new_state();
$state['deck'] = $deck;
$cards = [];
for ($i = 0; $i < 52; $i++) $cards[] = poker_draw_card($state);
verify(count(array_unique($cards)) === 52 && count($state['deck']) === 0, 'No duplicate deals.');

$state = poker_new_state();
$state['phase'] = 'drawing';
$state['players']['alice'] = ['name' => 'Alice','wins' => 0,'drawn' => false,
    'hand' => ['AH','KH','QH','JH','TH']];
$state['players']['bob'] = ['name' => 'Bob','wins' => 0,'drawn' => false,
    'hand' => ['9C','9D','9H','9S','2H']];
$view = poker_public_state($state, 'alice');
verify(count($view['players'][0]['hand']) === 5 && count($view['players'][1]['hand']) === 0,
    'Opponent cards hidden until showdown.');
verify(!array_key_exists('deck', $view), 'Deck never returned by API.');
$state['phase'] = 'showdown';
$view = poker_public_state($state, 'alice');
verify(count($view['players'][1]['hand']) === 5, 'All cards visible at showdown.');

echo "All game engine tests passed.\n";
