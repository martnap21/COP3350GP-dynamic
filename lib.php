<?php
declare(strict_types=1);

/**
 * Server-authoritative classroom Texas Hold'em (fixed 10-chip raises).
 * One table, 2–6 players, 500 initial play chips, 5/10 blinds.
 * Simplification: no all-ins or side pots. Players unable to call must fold.
 * State stays in a locked file outside the public web root.
 */

function poker_new_state(): array {
    return [
        'version' => 2, 'phase' => 'lobby', 'handNumber' => 0,
        'players' => [], 'dealer' => null, 'actor' => null,
        'smallBlind' => null, 'bigBlind' => null, 'deck' => [], 'board' => [],
        'pot' => 0, 'currentBet' => 0, 'raises' => 0,
        'winners' => [], 'showdownByFold' => false,
    ];
}

function poker_deck(): array {
    $cards = [];
    foreach (str_split('CDHS') as $suit) {
        foreach (str_split('23456789TJQKA') as $rank) {
            $cards[] = $rank . $suit;
        }
    }
    for ($i = count($cards) - 1; $i > 0; --$i) {
        $j = random_int(0, $i);
        [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
    }
    return $cards;
}

function poker_draw_card(array &$state): string {
    if (!$state['deck']) throw new RuntimeException('Deck exhausted.');
    return array_pop($state['deck']);
}

/** A five-card score [category, tiebreakers...], ordered from low to high. */
function poker_evaluate(array $cards): array {
    if (count($cards) !== 5) throw new InvalidArgumentException('Five cards required.');
    $values = [];
    $suits = [];
    foreach ($cards as $card) {
        if (!is_string($card) || strlen($card) !== 2) {
            throw new InvalidArgumentException('Invalid card.');
        }
        $rank = strpos('23456789TJQKA', $card[0]);
        if ($rank === false || !str_contains('CDHS', $card[1])) {
            throw new InvalidArgumentException('Invalid card.');
        }
        $values[] = $rank + 2;
        $suits[] = $card[1];
    }
    rsort($values, SORT_NUMERIC);
    $counts = array_count_values($values);
    $unique = array_keys($counts);
    rsort($unique, SORT_NUMERIC);
    $flush = count(array_unique($suits)) === 1;
    $straight = 0;
    if (count($unique) === 5) {
        if ($unique[0] - $unique[4] === 4) $straight = (int)$unique[0];
        elseif ($unique === [14, 5, 4, 3, 2]) $straight = 5;
    }
    $groups = [];
    foreach ($counts as $value => $count) $groups[] = [(int)$count, (int)$value];
    usort($groups, static fn($a, $b) => ($b[0] <=> $a[0]) ?: ($b[1] <=> $a[1]));

    if ($straight && $flush) return [8, $straight];
    if ($groups[0][0] === 4) return [7, $groups[0][1], $groups[1][1]];
    if ($groups[0][0] === 3 && $groups[1][0] === 2) return [6, $groups[0][1], $groups[1][1]];
    if ($flush) return array_merge([5], $values);
    if ($straight) return [4, $straight];
    if ($groups[0][0] === 3) return [3, $groups[0][1], $groups[1][1], $groups[2][1]];
    if ($groups[0][0] === 2 && $groups[1][0] === 2)
        return [2, max($groups[0][1], $groups[1][1]), min($groups[0][1], $groups[1][1]), $groups[2][1]];
    if ($groups[0][0] === 2) return [1, $groups[0][1], $groups[1][1], $groups[2][1], $groups[3][1]];
    return array_merge([0], $values);
}

/** Compare five-card scores lexicographically. */
function poker_compare(array $left, array $right): int {
    for ($i = 0; $i < max(count($left), count($right)); ++$i) {
        $comparison = ($left[$i] ?? 0) <=> ($right[$i] ?? 0);
        if ($comparison !== 0) return $comparison;
    }
    return 0;
}

function poker_best_of_seven(array $cards): array {
    if (count($cards) !== 7 || count(array_unique($cards)) !== 7) {
        throw new InvalidArgumentException('Seven distinct cards required.');
    }
    $best = null;
    for ($a = 0; $a < 3; ++$a) {
        for ($b = $a + 1; $b < 4; ++$b) {
            for ($c = $b + 1; $c < 5; ++$c) {
                for ($d = $c + 1; $d < 6; ++$d) {
                    for ($e = $d + 1; $e < 7; ++$e) {
                        $score = poker_evaluate([$cards[$a], $cards[$b], $cards[$c], $cards[$d], $cards[$e]]);
                        if ($best === null || poker_compare($score, $best) > 0) $best = $score;
                    }
                }
            }
        }
    }
    return $best;
}

function poker_hand_name(array $score): string {
    return ['High card', 'One pair', 'Two pair', 'Three of a kind', 'Straight',
        'Flush', 'Full house', 'Four of a kind', 'Straight flush'][$score[0]] ?? 'Unknown';
}

function poker_ids(array $state): array {
    return array_keys($state['players']);
}

/** Advance circularly through table seats, respecting the allowed IDs. */
function poker_next_id(array $state, ?string $from, array $allowed): ?string {
    $seats = poker_ids($state);
    if (!$seats || !$allowed) return null;
    $offset = $from === null ? -1 : array_search($from, $seats, true);
    if ($offset === false) $offset = -1;
    for ($n = 1; $n <= count($seats); ++$n) {
        $id = $seats[($offset + $n) % count($seats)];
        if (in_array($id, $allowed, true)) return $id;
    }
    return null;
}

function poker_live_ids(array $state): array {
    $ids = [];
    foreach ($state['players'] as $id => $player) {
        if ($player['status'] === 'active') $ids[] = $id;
    }
    return $ids;
}

function poker_post_chips(array &$state, string $id, int $amount): void {
    if ($amount < 0 || $state['players'][$id]['stack'] < $amount) {
        throw new DomainException('Not enough chips.');
    }
    $state['players'][$id]['stack'] -= $amount;
    $state['players'][$id]['bet'] += $amount;
    $state['pot'] += $amount;
}

function poker_start_hand(array &$state): void {
    if ($state['phase'] !== 'lobby') throw new DomainException('Return to lobby first.');
    $ids = poker_ids($state);
    if (count($ids) < 2) throw new DomainException('At least two players are required.');
    foreach ($ids as $id) {
        if ($state['players'][$id]['stack'] < 10) {
            throw new DomainException('All players must have at least 10 chips. Refill in the lobby.');
        }
    }

    $state['dealer'] = poker_next_id($state, $state['dealer'], $ids);
    $state['smallBlind'] = count($ids) === 2
        ? $state['dealer'] : poker_next_id($state, $state['dealer'], $ids);
    $state['bigBlind'] = poker_next_id($state, $state['smallBlind'], $ids);
    $state['deck'] = poker_deck();
    $state['board'] = [];
    $state['pot'] = 0;
    $state['currentBet'] = 10;
    $state['raises'] = 0;
    $state['winners'] = [];
    $state['showdownByFold'] = false;
    $state['phase'] = 'preflop';
    $state['handNumber']++;

    foreach ($state['players'] as &$player) {
        $player['hand'] = [];
        $player['bet'] = 0;
        $player['acted'] = false;
        $player['status'] = 'active';
        $player['lastAction'] = '';
        $player['handName'] = null;
    }
    unset($player);

    // Deal one card at a time clockwise, starting left of the button.
    $order = [];
    $cursor = $state['dealer'];
    for ($i = 0; $i < count($ids); ++$i) {
        $cursor = poker_next_id($state, $cursor, $ids);
        $order[] = $cursor;
    }
    for ($round = 0; $round < 2; ++$round) {
        foreach ($order as $id) $state['players'][$id]['hand'][] = poker_draw_card($state);
    }
    poker_post_chips($state, $state['smallBlind'], 5);
    poker_post_chips($state, $state['bigBlind'], 10);
    $state['players'][$state['smallBlind']]['lastAction'] = 'Small blind · 5';
    $state['players'][$state['bigBlind']]['lastAction'] = 'Big blind · 10';
    $state['actor'] = poker_next_id($state, $state['bigBlind'], $ids);
}

function poker_award_pot(array &$state, array $winners, bool $byFold): void {
    if (!$winners) throw new RuntimeException('No winner to award.');
    $share = intdiv($state['pot'], count($winners));
    $remainder = $state['pot'] % count($winners);
    // Odd chips assigned in clockwise seat order after the dealer.
    $order = [];
    $cursor = $state['dealer'];
    for ($i = 0; $i < count($state['players']); ++$i) {
        $cursor = poker_next_id($state, $cursor, poker_ids($state));
        if (in_array($cursor, $winners, true)) $order[] = $cursor;
    }
    foreach ($order as $id) {
        $state['players'][$id]['stack'] += $share + ($remainder-- > 0 ? 1 : 0);
        $state['players'][$id]['wins']++;
    }
    $state['winners'] = $winners;
    $state['phase'] = 'showdown';
    $state['showdownByFold'] = $byFold;
    $state['actor'] = null;
    $state['deck'] = [];
    $state['currentBet'] = 0;
}

function poker_end_hand(array &$state): void {
    $live = poker_live_ids($state);
    if (count($live) === 1) {
        poker_award_pot($state, $live, true);
        return;
    }
    if (count($state['board']) !== 5) throw new RuntimeException('Community board is incomplete.');
    $best = null;
    $winners = [];
    foreach ($live as $id) {
        $score = poker_best_of_seven(array_merge($state['players'][$id]['hand'], $state['board']));
        $state['players'][$id]['handName'] = poker_hand_name($score);
        $compare = $best === null ? 1 : poker_compare($score, $best);
        if ($compare > 0) {
            $best = $score;
            $winners = [$id];
        } elseif ($compare === 0) {
            $winners[] = $id;
        }
    }
    poker_award_pot($state, $winners, false);
}

function poker_next_street(array &$state): void {
    if ($state['phase'] === 'river') {
        poker_end_hand($state);
        return;
    }
    if ($state['phase'] === 'preflop') {
        $state['phase'] = 'flop';
        for ($i = 0; $i < 3; ++$i) $state['board'][] = poker_draw_card($state);
    } elseif ($state['phase'] === 'flop') {
        $state['phase'] = 'turn';
        $state['board'][] = poker_draw_card($state);
    } elseif ($state['phase'] === 'turn') {
        $state['phase'] = 'river';
        $state['board'][] = poker_draw_card($state);
    } else throw new RuntimeException('Invalid betting street.');

    $state['currentBet'] = 0;
    $state['raises'] = 0;
    foreach ($state['players'] as &$player) {
        $player['bet'] = 0;
        $player['acted'] = false;
        $player['lastAction'] = '';
    }
    unset($player);
    $state['actor'] = poker_next_id($state, $state['dealer'], poker_live_ids($state));
}

function poker_action(array &$state, string $id, string $action): void {
    if (!in_array($state['phase'], ['preflop', 'flop', 'turn', 'river'], true)) {
        throw new DomainException('No betting round is in progress.');
    }
    if ($id !== $state['actor'] || $state['players'][$id]['status'] !== 'active') {
        throw new DomainException('It is not your turn.');
    }
    $owed = $state['currentBet'] - $state['players'][$id]['bet'];
    if ($action === 'fold') {
        $state['players'][$id]['status'] = 'folded';
        $state['players'][$id]['lastAction'] = 'Folded';
        $state['players'][$id]['acted'] = true;
    } elseif ($action === 'check') {
        if ($owed !== 0) throw new DomainException('You must call or fold.');
        $state['players'][$id]['lastAction'] = 'Checked';
        $state['players'][$id]['acted'] = true;
    } elseif ($action === 'call') {
        if ($owed <= 0) throw new DomainException('Nothing to call. Check instead.');
        poker_post_chips($state, $id, $owed);
        $state['players'][$id]['lastAction'] = 'Called · ' . $owed;
        $state['players'][$id]['acted'] = true;
    } elseif ($action === 'raise') {
        if ($state['raises'] >= 3) throw new DomainException('Maximum three raises per round.');
        $needed = $owed + 10;
        poker_post_chips($state, $id, $needed);
        $state['currentBet'] += 10;
        $state['raises']++;
        foreach ($state['players'] as $otherId => &$player) {
            if ($otherId !== $id && $player['status'] === 'active') $player['acted'] = false;
        }
        unset($player);
        $state['players'][$id]['lastAction'] = 'Raised to ' . $state['currentBet'];
        $state['players'][$id]['acted'] = true;
    } else {
        throw new DomainException('Unknown betting action.');
    }

    $live = poker_live_ids($state);
    if (count($live) === 1) {
        poker_end_hand($state);
        return;
    }
    $pending = [];
    foreach ($live as $candidate) {
        $player = $state['players'][$candidate];
        if (!$player['acted'] || $player['bet'] < $state['currentBet']) $pending[] = $candidate;
    }
    if (!$pending) poker_next_street($state);
    else $state['actor'] = poker_next_id($state, $id, $pending);
}

function poker_to_lobby(array &$state): void {
    if ($state['phase'] === 'lobby') return;
    $state['phase'] = 'lobby';
    $state['actor'] = null;
    $state['deck'] = [];
    $state['board'] = [];
    $state['pot'] = 0;
    $state['currentBet'] = 0;
    $state['raises'] = 0;
    $state['winners'] = [];
    $state['showdownByFold'] = false;
    foreach ($state['players'] as &$player) {
        $player['hand'] = [];
        $player['status'] = 'lobby';
        $player['bet'] = 0;
        $player['acted'] = false;
        $player['lastAction'] = '';
        $player['handName'] = null;
    }
    unset($player);
}

function poker_public_state(array $state, ?string $playerId): array {
    $players = [];
    foreach ($state['players'] as $id => $player) {
        $reveal = $id === $playerId ||
            ($state['phase'] === 'showdown' && !$state['showdownByFold'] && $player['status'] === 'active');
        $players[] = [
            'id' => $id, 'name' => $player['name'], 'stack' => $player['stack'],
            'bet' => $player['bet'], 'wins' => $player['wins'],
            'status' => $player['status'], 'lastAction' => $player['lastAction'],
            'hand' => $reveal ? $player['hand'] : [],
            'cardCount' => count($player['hand']), 'handName' => $player['handName'],
            'acted' => $player['acted'],
        ];
    }
    $me = is_string($playerId) && isset($state['players'][$playerId]) ? $playerId : null;
    $toCall = $me !== null && $state['actor'] === $me
        ? max(0, $state['currentBet'] - $state['players'][$me]['bet']) : 0;
    return [
        'phase' => $state['phase'], 'handNumber' => $state['handNumber'],
        'players' => $players, 'you' => $me, 'dealer' => $state['dealer'],
        'smallBlind' => $state['smallBlind'], 'bigBlind' => $state['bigBlind'],
        'actor' => $state['actor'], 'board' => $state['board'],
        'pot' => $state['pot'], 'currentBet' => $state['currentBet'],
        'toCall' => $toCall, 'raisesRemaining' => 3 - $state['raises'],
        'winners' => $state['winners'], 'showdownByFold' => $state['showdownByFold'],
        'maxPlayers' => 6, 'smallBlindAmount' => 5, 'bigBlindAmount' => 10,
    ];
}

function poker_with_state(callable $callback): array {
    $path = getenv('POKER_STATE_FILE') ?: '/var/lib/poker-lab/state.json';
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Game storage unavailable; contact administrator.');
    }
    $handle = fopen($path . '.lock', 'c+');
    if ($handle === false) throw new RuntimeException('Cannot open game lock.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock game state.');
        $state = poker_new_state();
        if (is_file($path)) {
            $stored = json_decode((string)file_get_contents($path), true);
            if (!is_array($stored) || ($stored['version'] ?? null) !== 2) {
                throw new RuntimeException('Incompatible game state: back up the old game file and reset it before deploying Hold’em.');
            }
            $state = $stored;
        }
        [$result, $dirty] = $callback($state);
        if ($dirty) {
            $tmp = tempnam($dir, 'pkr');
            if ($tmp === false) throw new RuntimeException('Cannot create state temp file.');
            try {
                if (file_put_contents($tmp, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    throw new RuntimeException('Cannot write state.');
                }
                chmod($tmp, 0600);
                if (!rename($tmp, $path)) throw new RuntimeException('Cannot save state.');
            } finally {
                if (is_file($tmp)) unlink($tmp);
            }
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
