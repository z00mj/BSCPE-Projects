<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
error_log("Update_balance.php called. Session email: " . ($_SESSION['email'] ?? 'NOT SET'));
include("connect.php");

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.',
    'newBalance' => 0,
    'serverDiceResults' => [],
    'revealedCard' => null,
    'nextCard' => null
];

// Helper function to get card value (for Hi-Lo logic)
function getCardValue($rank) {
    switch ($rank) {
        case 'J': return 11;
        case 'Q': return 12;
        case 'K': return 13;
        case 'A': return 14; // Ace is high for Hi-Lo
        default: return (int)$rank; // For numeric cards 2-10
    }
}

// Helper function to get number color for Roulette (mirroring client-side)
function getRouletteNumberColor($number) {
    if ($number == 0) return 'green';
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    return in_array($number, $redNumbers) ? 'red' : 'black';
}

// 1. Check if user is logged in
if (!isset($_SESSION['email'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

$email = $_SESSION['email'];

// 2. Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);

// 3. Validate and extract common data
$game = $input['game'] ?? null;
$action = $input['action'] ?? null;

if (!in_array($game, ['color_game', 'hi_lo', 'roulette', 'baccarat', 'mines'])) {
    $response['message'] = 'Invalid game specified.';
    echo json_encode($response);
    exit();
}

// 4. Fetch User's Current Balance
$stmt = mysqli_prepare($conn, "SELECT balance FROM users WHERE email=?");
if (!$stmt) {
    $response['message'] = 'Database query preparation failed: ' . mysqli_error($conn);
    echo json_encode($response);
    exit();
}
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$userRow = mysqli_fetch_assoc($result)) {
    $response['message'] = 'User not found in database.';
    echo json_encode($response);
    exit();
}
$currentDBBalance = $userRow['balance'];
$response['newBalance'] = $currentDBBalance;

// Handle 'get_balance' action directly here (no balance modification)
if ($action === 'get_balance') {
    $response['success'] = true;
    $response['message'] = 'Balance fetched successfully.';
    echo json_encode($response);
    exit();
}

// Initialize variables for universal balance calculation
$betToDeduct = 0; // Total amount to deduct from balance for the current game
$winningsToAdd = 0; // Total amount to add to balance (winnings + returned stake)
$gameMessage = ""; // Message for the user

// --- Game Logic based on $game type ---
switch ($game) {
    case 'color_game':
        $betFromInput = $input['bet'] ?? 0;
        if (!is_numeric($betFromInput) || $betFromInput <= 0) {
            $response['message'] = 'Invalid bet amount for Color Game.';
            echo json_encode($response);
            exit();
        }
        if ($currentDBBalance < $betFromInput) {
            $response['message'] = 'Insufficient funds for Color Game.';
            echo json_encode($response);
            exit();
        }
        $betToDeduct = $betFromInput; // Mark for deduction

        $chosenColor = $input['chosenColor'] ?? null;
        $allowedColors = ['red', 'green', 'blue', 'yellow', 'white', 'magenta'];
        if (!in_array($chosenColor, $allowedColors)) {
            $response['message'] = 'Invalid color chosen.';
            echo json_encode($response);
            exit();
        }

        $matches = 0;
        $response['serverDiceResults'] = [];
        for ($i = 0; $i < 3; $i++) {
            $rolledColor = $allowedColors[array_rand($allowedColors)];
            $response['serverDiceResults'][] = $rolledColor;
            if ($rolledColor === $chosenColor) {
                $matches++;
            }
        }

        $tempWinAmount = 0; // Temp variable for winnings before adding stake back
        if ($matches === 3) $tempWinAmount = $betFromInput * 5;
        elseif ($matches === 2) $tempWinAmount = $betFromInput * 3;
        elseif ($matches === 1) $tempWinAmount = $betFromInput * 2;

        $winningsToAdd = $tempWinAmount; // Amount to add back (profit + initial stake if won)
        if ($tempWinAmount > 0) { // If there's a win, return the stake as part of winnings
            $winningsToAdd = $tempWinAmount; // The multiplier includes the stake already (e.g., 2x means 1x profit + 1x stake)
            $gameMessage = "You matched $matches color(s) and won ₱" . number_format($tempWinAmount, 2) . "!";
        } else {
            $gameMessage = "You lost. No matches.";
        }
        break;

    case 'hi_lo':
        $betFromInput = $input['bet'] ?? 0;
        if (!is_numeric($betFromInput) || $betFromInput <= 0) {
            $response['message'] = 'Invalid bet amount for Hi-Lo Game.';
            echo json_encode($response);
            exit();
        }
        if ($currentDBBalance < $betFromInput) {
            $response['message'] = 'Insufficient funds for Hi-Lo Game.';
            echo json_encode($response);
            exit();
        }
        $betToDeduct = $betFromInput; // Mark for deduction

        $currentCardRank = $input['currentCardRank'] ?? null;
        $choice = $input['choice'] ?? null;

        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        $suits = ['hearts', 'diamonds', 'clubs', 'spades'];

        if (!in_array($currentCardRank, $ranks) || !in_array($choice, ['higher', 'lower'])) {
            $response['message'] = 'Invalid Hi-Lo game parameters (invalid current card rank or choice).';
            echo json_encode($response);
            exit();
        }

        $currentCardValue = getCardValue($currentCardRank);
        $nextCardRank = $ranks[array_rand($ranks)];
        $nextCardSuit = $suits[array_rand($suits)];
        $nextCardValue = getCardValue($nextCardRank);

        $response['revealedCard'] = ['rank' => $nextCardRank, 'suit' => $nextCardSuit];
        $response['nextCard'] = $response['revealedCard'];

        $tempWinAmount = 0;
        if ($nextCardValue === $currentCardValue) {
            $tempWinAmount = $betFromInput; // Tie, return bet
            $gameMessage = "It's a tie! Your bet of ₱" . number_format($betFromInput, 2) . " is returned.";
        } elseif (($choice === 'higher' && $nextCardValue > $currentCardValue) || ($choice === 'lower' && $nextCardValue < $currentCardValue)) {
            $tempWinAmount = $betFromInput * 2; // Win (stake + profit)
            $gameMessage = "You guessed " . strtoupper($choice) . " and won! You won ₱" . number_format($tempWinAmount, 2) . "!";
        } else {
            $tempWinAmount = 0; // Loss
            $gameMessage = "You guessed " . strtoupper($choice) . " but lost. The card was " . $nextCardRank . ".";
        }
        $winningsToAdd = $tempWinAmount;
        break;

    case 'roulette':
        $betsArray = $input['bets'] ?? [];
        $winningNumberDetails = $input['winningNumberDetails'] ?? null;

        if (empty($betsArray) || !$winningNumberDetails || !isset($winningNumberDetails['number']) || !isset($winningNumberDetails['color'])) {
            $response['message'] = 'Invalid roulette game data. Missing bets or winning number details.';
            echo json_encode($response);
            exit();
        }

        $totalBetAmount = 0;
        foreach ($betsArray as $betItem) {
            if (!isset($betItem['amount']) || !is_numeric($betItem['amount']) || $betItem['amount'] <= 0) {
                $response['message'] = 'Invalid bet amount found in one of your bets.';
                echo json_encode($response);
                exit();
            }
            $totalBetAmount += (float)$betItem['amount'];
        }

        if ($currentDBBalance < $totalBetAmount) {
            $response['message'] = "Insufficient funds. You need ₱" . number_format($totalBetAmount, 2) . " but only have ₱" . number_format($currentDBBalance, 2) . ".";
            echo json_encode($response);
            exit();
        }
        $betToDeduct = $totalBetAmount; // Mark for deduction

        $winningNumber = (int)$winningNumberDetails['number'];
        $winningColor = getRouletteNumberColor($winningNumber);

        $totalIndividualWinnings = 0;
        $detailedWinLossMessage = "The ball landed on: <span class=\"font-bold text-yellow-300\">{$winningNumber}</span> (" . strtoupper($winningColor) . ")!<br>";

        foreach ($betsArray as $betItem) {
            $betType = $betItem['type'];
            $betValue = $betItem['value'];
            $betAmountPlaced = (float)$betItem['amount'];
            $payoutMultiplier = 0;
            $betWon = false;

            switch ($betType) {
                case 'number':
                    if ((int)$betValue === $winningNumber) {
                        $betWon = true;
                        $payoutMultiplier = 35;
                    }
                    break;
                case 'color':
                    if ($betValue === $winningColor) {
                        $betWon = true;
                        $payoutMultiplier = 1;
                    }
                    break;
                case 'parity':
                    if ($winningNumber !== 0) {
                        if ($betValue === 'even' && $winningNumber % 2 === 0) {
                            $betWon = true;
                            $payoutMultiplier = 1;
                        } elseif ($betValue === 'odd' && $winningNumber % 2 !== 0) {
                            $betWon = true;
                            $payoutMultiplier = 1;
                        }
                    }
                    break;
                case 'half':
                    if ($winningNumber !== 0) {
                        if ($betValue === '1-18' && $winningNumber >= 1 && $winningNumber <= 18) {
                            $betWon = true;
                            $payoutMultiplier = 1;
                        } elseif ($betValue === '19-36' && $winningNumber >= 19 && $winningNumber <= 36) {
                            $betWon = true;
                            $payoutMultiplier = 1;
                        }
                    }
                    break;
                case 'dozen':
                    if ($winningNumber !== 0) {
                        if ($betValue === '1st12' && $winningNumber >= 1 && $winningNumber <= 12) {
                            $betWon = true;
                            $payoutMultiplier = 2;
                        } elseif ($betValue === '2nd12' && $winningNumber >= 13 && $winningNumber <= 24) {
                            $betWon = true;
                            $payoutMultiplier = 2;
                        } elseif ($betValue === '3rd12' && $winningNumber >= 25 && $winningNumber <= 36) {
                            $betWon = true;
                            $payoutMultiplier = 2;
                        }
                    }
                    break;
                case 'column':
                    if ($winningNumber !== 0) {
                        if ($betValue === 'col1' && ($winningNumber - 1) % 3 === 0) {
                            $betWon = true;
                            $payoutMultiplier = 2;
                        } elseif ($betValue === 'col2' && ($winningNumber - 2) % 3 === 0) {
                            $betWon = true;
                            $payoutMultiplier = 2;
                        } elseif ($betValue === 'col3' && $winningNumber % 3 === 0) {
                            $betWon = true;
                            $payoutMultiplier = 2;
                        }
                    }
                    break;
            }

            if ($betWon) {
                $amountReturnedForThisBet = $betAmountPlaced + ($betAmountPlaced * $payoutMultiplier);
                $totalIndividualWinnings += $amountReturnedForThisBet;
                $detailedWinLossMessage .= "Your ₱{$betAmountPlaced} bet on {$betType} ({$betValue}) won! You get back ₱" . number_format($amountReturnedForThisBet, 2) . ".<br>";
            } else {
                $detailedWinLossMessage .= "Your ₱{$betAmountPlaced} bet on {$betType} ({$betValue}) lost.<br>";
            }
        }
        $winningsToAdd = $totalIndividualWinnings; // Total amount to add back (winnings + winning stakes)

        $gameMessage = $detailedWinLossMessage;
        $profit = $totalIndividualWinnings - $totalBetAmount;
        if ($profit > 0) {
            $gameMessage .= "<br>Total returned: ₱" . number_format($totalIndividualWinnings, 2) . ". Net Profit: <span class=\"font-bold text-green-400\">₱" . number_format($profit, 2) . "</span>";
        } elseif ($profit < 0) {
            $gameMessage .= "<br>Total returned: ₱" . number_format($totalIndividualWinnings, 2) . ". Net Loss: <span class=\"font-bold text-red-400\">₱" . number_format(abs($profit), 2) . "</span>";
        } else {
            $gameMessage .= "<br>Total returned: ₱" . number_format($totalIndividualWinnings, 2) . ". Broke Even on this spin.";
        }

        break;

    case 'baccarat':
        $baccaratBets = $input['betsPlaced'] ?? null;
        $gameOutcome = $input['gameOutcome'] ?? null;

        if (!$baccaratBets || !is_array($baccaratBets) || !$gameOutcome || !in_array($gameOutcome, ['player', 'banker', 'tie'])) {
            $response['message'] = 'Baccarat: Invalid game data received. Bets or outcome missing/invalid.';
            echo json_encode($response);
            exit();
        }

        $playerBet = isset($baccaratBets['player']) && is_numeric($baccaratBets['player']) ? (float)$baccaratBets['player'] : 0;
        $bankerBet = isset($baccaratBets['banker']) && is_numeric($baccaratBets['banker']) ? (float)$baccaratBets['banker'] : 0;
        $tieBet = isset($baccaratBets['tie']) && is_numeric($baccaratBets['tie']) ? (float)$baccaratBets['tie'] : 0; // Corrected

        if ($playerBet < 0 || $bankerBet < 0 || $tieBet < 0) {
            $response['message'] = 'Baccarat: Bet amounts cannot be negative.';
            echo json_encode($response);
            exit();
        }

        $totalBetAmount = $playerBet + $bankerBet + $tieBet;

        if ($totalBetAmount <= 0) {
            $response['message'] = 'Baccarat: No bets were placed for the game.';
            echo json_encode($response);
            exit();
        }

        if ($currentDBBalance < $totalBetAmount) {
            $response['message'] = "Baccarat: Insufficient funds. You bet ₱" . number_format($totalBetAmount, 2) . ", but only have ₱" . number_format($currentDBBalance, 2) . ".";
            echo json_encode($response);
            exit();
        }
        $betToDeduct = $totalBetAmount; // Mark for deduction

        $amountReturnedToPlayer = 0;

        if ($gameOutcome === 'player' && $playerBet > 0) {
            $amountReturnedToPlayer += $playerBet * 2;
        }
        if ($gameOutcome === 'banker' && $bankerBet > 0) {
            $amountReturnedToPlayer += $bankerBet * 1.95;
        }
        if ($gameOutcome === 'tie' && $tieBet > 0) {
            $amountReturnedToPlayer += $tieBet * 9;
        }

        $winningsToAdd = $amountReturnedToPlayer; // Total amount to add back (winnings + winning stakes)

        $netProfitOrLoss = $amountReturnedToPlayer - $totalBetAmount;

        if ($netProfitOrLoss > 0) {
            $gameMessage = "Baccarat: Won ₱" . number_format($netProfitOrLoss, 2) . ".";
        } elseif ($netProfitOrLoss < 0) {
            $gameMessage = "Baccarat: Lost ₱" . number_format(abs($netProfitOrLoss), 2) . ".";
        } else {
            $gameMessage = "Baccarat: Broke even on this round.";
        }
        break;

   case 'mines':
    $betAmount = $input['betAmount'] ?? 0;
    $winningsAmount = $input['winningsAmount'] ?? 0;

    if ($action === 'start_game') {
        if ($currentDBBalance < $betAmount) {
            $response['message'] = 'Mines: Insufficient funds.';
            echo json_encode($response);
            exit();
        }
        $betToDeduct = $betAmount;
        $gameMessage = "Mines: Started with a ₱" . number_format($betAmount, 2) . " bet.";
    } elseif ($action === 'cashout') {
        $winningsToAdd = $winningsAmount;
        $gameMessage = "Mines: Cashed out ₱" . number_format($winningsAmount, 2) . "!";
    } else {
        $response['message'] = 'Mines: Invalid action.';
        echo json_encode($response);
        exit();
    }
    break;


    default:
        $response['message'] = 'Selected game logic is not available.';
        echo json_encode($response);
        exit();
}

// Universal Final Balance Calculation
$finalBalance = $currentDBBalance - $betToDeduct + $winningsToAdd;


// 8. Update Balance in the Database
$updateStmt = mysqli_prepare($conn, "UPDATE users SET balance=? WHERE email=?");
if (!$updateStmt) {
    $response['message'] = 'Database update preparation failed: ' . mysqli_error($conn);
    $response['newBalance'] = $currentDBBalance;
    echo json_encode($response);
    exit();
}
mysqli_stmt_bind_param($updateStmt, "ds", $finalBalance, $email);
$updateSuccess = mysqli_stmt_execute($updateStmt);

if ($updateSuccess) {
    $response['success'] = true;
    $response['message'] = $gameMessage;
    $response['newBalance'] = $finalBalance;
} else {
    $response['message'] = 'Failed to update balance in database. Transaction rolled back by not saving.';
    $response['newBalance'] = $currentDBBalance;
}

echo json_encode($response);
exit();
?>