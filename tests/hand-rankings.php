<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAILED: ' . $label);
}

$hands = [
    'straight flush' => ['9H', 'TH', 'JH', 'QH', 'KH'],
    'four of a kind' => ['9C', '9D', '9H', '9S', '2C'],
    'full house' => ['KC', 'KH', 'KS', '2D', '2S'],
    'flush' => ['2H', '4H', '8H', 'JH', 'KH'],
    'straight' => ['5C', '6S', '7H', '8D', '9C'],
    'three of a kind' => ['6C', '6D', '6H', '9S', '2C'],
    'two pair' => ['QC', 'QD', '2C', '2D', '9H'],
    'one pair' => ['AC', 'AD', '5H', '8C', '2D'],
    'high card' => ['AC', 'KD', '9S', '7H', '2C'],
];
$last = null;
foreach ($hands as $label => $cards) {
    $score = poker_evaluate($cards);
    check(strtolower(poker_hand_name($score)) === $label, 'category ' . $label);
    if ($last !== null) check(poker_compare($last, $score) > 0, 'rank order ' . $label);
    $last = $score;
}
check(poker_compare(poker_evaluate(['AC','2D','3H','4S','5C']),
    poker_evaluate(['2C','3D','4H','5S','6C'])) < 0, 'wheel straight is five high');
check(poker_compare(poker_evaluate(['AH','AD','KH','9H','7H']),
    poker_evaluate(['AS','AC','QH','9S','7C'])) > 0, 'kickers decide equal pairs');
check(poker_compare(poker_evaluate(['2C','2D','5H','5S','AH']),
    poker_evaluate(['2H','2S','5D','5C','AD'])) === 0, 'tied hands');
check(poker_best_of_seven(['AC','KC','QC','JC','TC','2D','3H']) === [8,14], 'royal flush from seven');
check(poker_hand_name(poker_best_of_seven(['AH','AD','AC','AS','2D','3H','4S'])) === 'Four of a kind',
    'quads from seven');
check(poker_best_of_seven(['AC','2D','3H','4S','5C','9D','TH'])[0] === 4, 'wheel from seven');
check(poker_best_of_seven(['2C','3D','4H','5S','6C','7D','8H'])[1] === 8,
    'best straight from seven');
$deck = poker_deck();
check(count($deck) === 52 && count(array_unique($deck)) === 52, '52 unique cards');

$state = poker_new_state();
$state['players'] = [
    'a' => ['name'=>'Alice','stack'=>500,'hand'=>[],'status'=>'lobby',
      'wins'=>0,'bet'=>0,'acted'=>false,'lastAction'=>'','handName'=>null],
    'b' => ['name'=>'Bob','stack'=>500,'hand'=>[],'status'=>'lobby',
      'wins'=>0,'bet'=>0,'acted'=>false,'lastAction'=>'','handName'=>null],
];
poker_start_hand($state);
check($state['phase'] === 'preflop' && count($state['players']['a']['hand']) === 2, 'deal hole cards');
check($state['smallBlind'] === 'a' && $state['bigBlind'] === 'b' && $state['actor'] === 'a',
    'heads-up dealer acts preflop');
check($state['pot'] === 15 && $state['currentBet'] === 10, 'blinds posted');
$view = poker_public_state($state,'a');
check(count($view['players'][0]['hand']) === 2 && count($view['players'][1]['hand']) === 0,
    'opponents hidden');
check(!array_key_exists('deck',$view), 'secret deck is not in JSON');
poker_action($state,'a','call');
poker_action($state,'b','check');
check($state['phase'] === 'flop' && count($state['board']) === 3 && $state['actor'] === 'b',
    'flop dealt, nondealer acts first');
foreach (['flop','turn','river'] as $street) {
    check($state['phase'] === $street, 'playing street ' . $street);
    poker_action($state,'b','check');
    poker_action($state,'a','check');
}
check($state['phase'] === 'showdown' && count($state['board']) === 5, 'river showdown');
check(count($state['winners']) >= 1, 'winner selected');
check($state['players']['a']['stack'] + $state['players']['b']['stack'] === 1000, 'chips conserved');
$view = poker_public_state($state,'a');
check(count($view['players'][1]['hand']) === 2, 'cards shown at showdown');

poker_to_lobby($state);
poker_start_hand($state);
check($state['dealer'] === 'b' && $state['actor'] === 'b', 'dealer button rotates');
poker_action($state,'b','fold');
check($state['phase'] === 'showdown' && $state['showdownByFold'], 'fold wins pot');
$view = poker_public_state($state,'a');
check(count($view['players'][1]['hand']) === 0, 'folded hand stays secret to opponent');
check($state['players']['a']['stack'] + $state['players']['b']['stack'] === 1000, 'fold pot conserved');


$three = poker_new_state();
foreach (['a' => 'Alice', 'b' => 'Bob', 'c' => 'Charlie'] as $id => $name) {
    $three['players'][$id] = [
        'name'=>$name, 'stack'=>500, 'hand'=>[], 'status'=>'lobby',
        'wins'=>0, 'bet'=>0, 'acted'=>false, 'lastAction'=>'', 'handName'=>null,
    ];
}
poker_start_hand($three);
check($three['dealer'] === 'a' && $three['smallBlind'] === 'b' &&
    $three['bigBlind'] === 'c' && $three['actor'] === 'a',
    'three-player blinds and preflop action');
poker_action($three,'a','raise');
check($three['currentBet'] === 20 && $three['actor'] === 'b', 'raise reopens betting');
poker_action($three,'b','call');
poker_action($three,'c','call');
check($three['phase'] === 'flop' && $three['actor'] === 'b' && $three['pot'] === 60,
    'three-player flop after raised preflop');
for ($street = 0; $street < 3; ++$street) {
    foreach (['b','c','a'] as $id) poker_action($three,$id,'check');
}
check($three['phase'] === 'showdown' && count($three['board']) === 5,
    'three-player showdown');
check(array_sum(array_column($three['players'], 'stack')) === 1500,
    'three-player chips conserved');

echo "All Texas Hold'em engine tests passed.\n";
