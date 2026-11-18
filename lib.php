<?php
declare(strict_types=1);

/**
 * A small, server-authoritative, play-for-points five-card draw game.
 * Uses a single locked JSON state file outside the web document root.
 * No passwords, real-money wagering, or account system.
 */

function poker_new_state(): array {
    return ['phase' => 'lobby', 'handNumber' => 0, 'players' => [],
        'deck' => [], 'winners' => []];
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
    if (empty($state['deck'])) {
        throw new RuntimeException('Deck exhausted.');
    }
    return array_pop($state['deck']);
}

/** Ranking tuple: [category, tiebreakers...], higher is better. */
function poker_evaluate(array $cards): array {
    if (count($cards) !== 5) {
        throw new InvalidArgumentException('Exactly five cards are required.');
    }
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
    $straightHigh = 0;
    if (count($unique) === 5) {
        if ($unique[0] - $unique[4] === 4) {
            $straightHigh = (int)$unique[0];
        } elseif ($unique === [14, 5, 4, 3, 2]) {
            $straightHigh = 5; // wheel: ace plays low
        }
    }

    $groups = [];
    foreach ($counts as $value => $quantity) {
        $groups[] = [(int)$quantity, (int)$value];
    }
    usort($groups, static fn(array $a, array $b): int =>
        ($b[0] <=> $a[0]) ?: ($b[1] <=> $a[1]));

    if ($straightHigh && $flush) return [8, $straightHigh];
    if ($groups[0][0] === 4) return [7, $groups[0][1], $groups[1][1]];
    if ($groups[0][0] === 3 && $groups[1][0] === 2) return [6, $groups[0][1], $groups[1][1]];
    if ($flush) return array_merge([5], $values);
    if ($straightHigh) return [4, $straightHigh];
    if ($groups[0][0] === 3) return [3, $groups[0][1], $groups[1][1], $groups[2][1]];
    if ($groups[0][0] === 2 && $groups[1][0] === 2) {
        return [2, max($groups[0][1], $groups[1][1]), min($groups[0][1], $groups[1][1]), $groups[2][1]];
    }
    if ($groups[0][0] === 2) {
        return [1, $groups[0][1], $groups[1][1], $groups[2][1], $groups[3][1]];
    }
    return array_merge([0], $values);
}

function poker_compare(array $left, array $right): int {
    for ($i = 0; $i < max(count($left), count($right)); ++$i) {
        $comparison = (($left[$i] ?? 0) <=> ($right[$i] ?? 0));
        if ($comparison !== 0) return $comparison;
    }
    return 0;
}

function poker_hand_name(array $score): string {
    return ['High card', 'One pair', 'Two pair', 'Three of a kind',
        'Straight', 'Flush', 'Full house', 'Four of a kind',
        'Straight flush'][$score[0]] ?? 'Unknown';
}

/**
 * Serialize reads and writes with flock. Store data outside the web root.
 * The server administrator must create the parent directory with permissions
 * for the PHP worker; see README.md.
 */
function poker_with_state(callable $callback): array {
    $path = getenv('POKER_STATE_FILE') ?: '/var/lib/poker-lab/state.json';
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('The poker storage directory is not writable; contact the server administrator.');
    }
    $handle = fopen($path . '.lock', 'c+');
    if ($handle === false) throw new RuntimeException('Cannot open game lock.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock game.');
        $state = poker_new_state();
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (!is_array($decoded) || !isset($decoded['phase'], $decoded['players'])) {
                throw new RuntimeException('Game data is invalid; contact the server administrator.');
            }
            $state = $decoded;
        }
        [$result, $dirty] = $callback($state);
        if ($dirty) {
            $temp = tempnam($directory, 'pkr');
            if ($temp === false) throw new RuntimeException('Cannot create game state file.');
            try {
                if (file_put_contents($temp, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    throw new RuntimeException('Cannot write game state.');
                }
                chmod($temp, 0600);
                if (!rename($temp, $path)) throw new RuntimeException('Cannot save game state.');
            } finally {
                if (is_file($temp)) unlink($temp);
            }
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** Only return the current player's cards until showdown. Never expose deck. */
function poker_public_state(array $state, ?string $playerId): array {
    $out = [];
    foreach ($state['players'] as $id => $player) {
        $reveal = $state['phase'] === 'showdown' || $id === $playerId;
        $out[] = [
            'id' => $id, 'name' => $player['name'], 'wins' => $player['wins'],
            'drawn' => $player['drawn'],
            'hand' => $reveal ? $player['hand'] : [],
            'cardCount' => count($player['hand']),
            'handName' => $state['phase'] === 'showdown'
                ? poker_hand_name(poker_evaluate($player['hand'])) : null,
        ];
    }
    return [
        'phase' => $state['phase'], 'handNumber' => $state['handNumber'],
        'players' => $out, 'you' => isset($state['players'][$playerId ?? '']) ? $playerId : null,
        'winners' => $state['winners'],
        'maxPlayers' => 6,
    ];
}
