<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_name('POKERLABSESSID');
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();
if (!isset($_SESSION['poker_csrf'])) {
    $_SESSION['poker_csrf'] = bin2hex(random_bytes(24));
}

require_once __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $input = [];
    $action = '';
    if ($method === 'POST') {
        if (!hash_equals($_SESSION['poker_csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            throw new DomainException('Invalid request token.');
        }
        $body = file_get_contents('php://input', false, null, 0, 4097);
        if (!is_string($body) || strlen($body) > 4096) {
            throw new DomainException('Request too large.');
        }
        $input = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new DomainException('Expected JSON object.');
        $action = (string)($input['action'] ?? '');
    } elseif ($method !== 'GET') {
        http_response_code(405);
        header('Allow: GET, POST');
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    $playerId = $_SESSION['poker_player_id'] ?? null;
    $result = poker_with_state(function (array &$state) use ($method, $action, $input, &$playerId): array {
        $dirty = false;
        if ($method === 'POST') {
            if ($action === 'join') {
                if ($state['phase'] !== 'lobby') throw new DomainException('Wait for the next round to join.');
                if (!isset($state['players'][$playerId ?? ''])) {
                    $name = trim((string)($input['name'] ?? ''));
                    if ($name === '' || strlen($name) > 48 ||
                        !preg_match('/^[\\p{L}\\p{N} ._-]+$/u', $name)) {
                        throw new DomainException('Choose a name using letters, numbers, spaces, dots, dashes, or underscores (max 48 bytes).');
                    }
                    if (count($state['players']) >= 6) throw new DomainException('Table is full (six players).');
                    foreach ($state['players'] as $player) {
                        if (strcasecmp($player['name'], $name) === 0) {
                            throw new DomainException('That name is already taken.');
                        }
                    }
                    $playerId = bin2hex(random_bytes(12));
                    $_SESSION['poker_player_id'] = $playerId;
                    $state['players'][$playerId] = [
                        'name' => $name, 'hand' => [], 'drawn' => false, 'wins' => 0,
                    ];
                    $dirty = true;
                }
            } else {
                if (!is_string($playerId) || !isset($state['players'][$playerId])) {
                    throw new DomainException('Join the table first.');
                }
                if ($action === 'start') {
                    if ($state['phase'] !== 'lobby') throw new DomainException('A hand is already in progress.');
                    if (count($state['players']) < 2) throw new DomainException('At least two players are required.');
                    $state['deck'] = poker_deck();
                    foreach ($state['players'] as &$player) {
                        $player['hand'] = [];
                        for ($i = 0; $i < 5; $i++) {
                            $player['hand'][] = poker_draw_card($state);
                        }
                        $player['drawn'] = false;
                    }
                    unset($player);
                    $state['phase'] = 'drawing';
                    $state['handNumber']++;
                    $state['winners'] = [];
                    $dirty = true;
                } elseif ($action === 'draw') {
                    if ($state['phase'] !== 'drawing') throw new DomainException('Not drawing cards right now.');
                    if ($state['players'][$playerId]['drawn']) throw new DomainException('You have already drawn.');
                    $positions = $input['positions'] ?? null;
                    if (!is_array($positions) || count($positions) > 3) {
                        throw new DomainException('Choose zero to three cards to replace.');
                    }
                    $seen = [];
                    foreach ($positions as $position) {
                        if (!is_int($position) || $position < 0 || $position > 4 || isset($seen[$position])) {
                            throw new DomainException('Invalid card selection.');
                        }
                        $seen[$position] = true;
                    }
                    foreach ($positions as $position) {
                        $state['players'][$playerId]['hand'][$position] = poker_draw_card($state);
                    }
                    $state['players'][$playerId]['drawn'] = true;
                    $dirty = true;
                    if (count(array_filter($state['players'], static fn($p) => $p['drawn'])) === count($state['players'])) {
                        $best = null;
                        $winners = [];
                        foreach ($state['players'] as $id => $player) {
                            $score = poker_evaluate($player['hand']);
                            $comparison = $best === null ? 1 : poker_compare($score, $best);
                            if ($comparison > 0) {
                                $best = $score;
                                $winners = [$id];
                            } elseif ($comparison === 0) {
                                $winners[] = $id;
                            }
                        }
                        foreach ($winners as $winner) $state['players'][$winner]['wins']++;
                        $state['winners'] = $winners;
                        $state['phase'] = 'showdown';
                        $state['deck'] = [];
                    }
                } elseif ($action === 'lobby') {
                    foreach ($state['players'] as &$player) {
                        $player['hand'] = [];
                        $player['drawn'] = false;
                    }
                    unset($player);
                    $state['deck'] = [];
                    $state['winners'] = [];
                    $state['phase'] = 'lobby';
                    $dirty = true;
                } elseif ($action === 'leave') {
                    if ($state['phase'] !== 'lobby') throw new DomainException('Return to the lobby before leaving.');
                    unset($state['players'][$playerId]);
                    unset($_SESSION['poker_player_id']);
                    $playerId = null;
                    $dirty = true;
                } else {
                    throw new DomainException('Unknown action.');
                }
            }
        }
        return [poker_public_state($state, $playerId), $dirty];
    });
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (DomainException | InvalidArgumentException | JsonException $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Poker server error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Ask the lab administrator to check the PHP logs and poker state directory.']);
}
