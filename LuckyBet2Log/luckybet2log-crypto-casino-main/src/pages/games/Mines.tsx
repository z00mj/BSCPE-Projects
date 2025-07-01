import { useState, useEffect } from "react";
import Layout from "@/components/Layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Bomb, Gem, DollarSign, Sparkles, Target, TrendingUp } from "lucide-react";
import { useToast } from "@/components/ui/use-toast";
import { useProfile } from "@/hooks/useProfile";
import { useBannedCheck } from "@/hooks/useBannedCheck";
import { useQuestTracker } from "@/hooks/useQuestTracker";
import { useActivityTracker } from "@/hooks/useActivityTracker";
import { useAuth } from "@/hooks/useAuth";
import { usePetSystem } from "@/hooks/usePetSystem";
import { useGameHistory } from "@/hooks/useGameHistory";
import { useGameSounds } from "@/hooks/useGameSounds";
import GameHistory from "@/components/GameHistory";
import BannedOverlay from "@/components/BannedOverlay";

type TileState = "hidden" | "safe" | "mine" | "itlog";

interface GameState {
  board: TileState[];
  minePositions: Set<number>;
  itlogPosition: number;
}

const Mines = () => {
  const [gameBoard, setGameBoard] = useState<TileState[]>(Array(25).fill("hidden"));
  const [gameState, setGameState] = useState<GameState | null>(null);
  const [gameStarted, setGameStarted] = useState(false);
  const [gameOver, setGameOver] = useState(false);
  const [currentBet, setCurrentBet] = useState("1.00");
  const [minesCount, setMinesCount] = useState("5");
  const [tilesRevealed, setTilesRevealed] = useState(0);
  const [currentMultiplier, setCurrentMultiplier] = useState(1.0);
  const { toast } = useToast();
  const { profile, updateBalance } = useProfile();
  const { isBanned } = useBannedCheck();
  const { trackGameWin, trackGameLoss, trackGamePlay, trackBet } = useQuestTracker();
  const { trackGameSession } = useActivityTracker();
  const { user } = useAuth();
  const { activePetBoosts } = usePetSystem();
  const { addHistoryEntry } = useGameHistory();
  const { playDiamondSound, playExplosionSound, playWinSound, playLossSound, playJackpotSound, audioEnabled, enableAudio } = useGameSounds();

  const [sessionId] = useState(`mines_session_${Date.now()}`);
  const [sessionStartTime] = useState(Date.now());

  const betAmounts = ["0.25", "0.50", "1.00", "1.50", "2.00", "5.00", "10.00", "50.00", "100.00", "500.00", "1000.00"];
  const minesOptions = ["3", "5", "7", "10"];

  const balance = profile?.coins || 0;

  const calculateMultiplier = (revealed: number, mines: number) => {
    const safeTiles = 25 - mines;
    if (revealed === 0) return 1.0;
    return 1 + (revealed / safeTiles) * 2;
  };

  const generateRandomGameState = (mineCount: number): GameState => {
    const allPositions = Array.from({ length: 25 }, (_, i) => i);
    
    for (let i = allPositions.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [allPositions[i], allPositions[j]] = [allPositions[j], allPositions[i]];
    }

    let effectiveMineCount = mineCount;
    const luckBoost = activePetBoosts.find(boost => boost.trait_type === 'luck_boost');
    if (luckBoost && luckBoost.total_boost > 1.0) {
      const luckBias = luckBoost.total_boost - 1.0;
      const shouldApplyLuck = Math.random() < luckBias;
      
      if (shouldApplyLuck) {
        effectiveMineCount = Math.max(1, mineCount - 1);
      }
    }

    const minePositions = new Set(allPositions.slice(0, effectiveMineCount));

    const hasItlog = Math.random() < 0.05;
    let itlogPosition = -1;
    
    if (hasItlog) {
      const safePositions = allPositions.slice(mineCount);
      if (safePositions.length > 0) {
        itlogPosition = safePositions[Math.floor(Math.random() * safePositions.length)];
      }
    }

    const board: TileState[] = Array(25).fill("safe");
    minePositions.forEach(pos => {
      board[pos] = "mine";
    });

    if (hasItlog && itlogPosition !== -1) {
      board[itlogPosition] = "itlog";
    }

    return {
      board,
      minePositions,
      itlogPosition
    };
  };

  const startGame = async () => {
    if (parseFloat(currentBet) > balance) {
      toast({
        title: "Insufficient balance",
        description: "You don't have enough coins to place this bet.",
        variant: "destructive"
      });
      return;
    }

    try {
      await updateBalance.mutateAsync({
        coinsChange: -parseFloat(currentBet)
      });

      await trackBet(parseFloat(currentBet), 'mines');
      await trackGamePlay('mines');

      const newGameState = generateRandomGameState(parseInt(minesCount));
      setGameState(newGameState);

      setGameBoard(Array(25).fill("hidden"));
      setGameStarted(true);
      setGameOver(false);
      setTilesRevealed(0);
      setCurrentMultiplier(1.0);

      toast({
        title: "Game Started!",
        description: `Bet placed: ${parseFloat(currentBet).toFixed(2)} coins`,
      });
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to start game. Please try again.",
        variant: "destructive"
      });
    }
  };

  const revealTile = (index: number) => {
    if (!gameStarted || gameOver || gameBoard[index] !== "hidden" || !gameState) return;

    const newBoard = [...gameBoard];
    const actualTileState = gameState.board[index];
    newBoard[index] = actualTileState;

    if (actualTileState === "mine") {
      console.log('Mine hit! Playing explosion sound...');
      playExplosionSound();
      trackGameLoss('mines');
      
      addHistoryEntry({
        game_type: 'mines',
        bet_amount: parseFloat(currentBet),
        result_type: 'loss',
        win_amount: 0,
        loss_amount: parseFloat(currentBet),
        multiplier: currentMultiplier,
        game_details: { 
          tilesRevealed, 
          minesCount: parseInt(minesCount),
          hitMine: true,
          position: index
        }
      });
      
      newBoard.forEach((tile, i) => {
        if (tile === "hidden") {
          newBoard[i] = gameState.board[i];
        }
      });
      
      setGameOver(false);
      setGameStarted(false);
      setGameState(null);
      setTilesRevealed(0);
      setCurrentMultiplier(1.0);
      
      setTimeout(() => {
        console.log('Playing loss sound after delay...');
        playLossSound();
      }, 500);
      
      toast({
        title: "Game Over!",
        description: "You hit a mine! Better luck next time.",
        variant: "destructive"
      });
    } else if (actualTileState === "itlog") {
      console.log('ITLOG hit! Playing jackpot sound...');
      playJackpotSound();
      
      const betMultiplier = parseFloat(currentBet) * 5000;
      const reward = Math.min(betMultiplier, 1000000);
      
      updateBalance.mutateAsync({
        itlogChange: reward
      }).then(async () => {
        await trackGameWin(reward * 0.01, 'mines');
        
        await addHistoryEntry({
          game_type: 'mines',
          bet_amount: parseFloat(currentBet),
          result_type: 'win',
          win_amount: reward,
          loss_amount: 0,
          multiplier: reward / parseFloat(currentBet),
          game_details: { 
            tilesRevealed, 
            minesCount: parseInt(minesCount),
            hitMine: false,
            position: index,
            isItlogWin: true,
            itlogReward: reward
          }
        });
      });

      newBoard.forEach((tile, i) => {
        if (tile === "hidden") {
          newBoard[i] = gameState.board[i];
        }
      });
      
      setGameOver(false);
      setGameStarted(false);
      setGameState(null);
      setTilesRevealed(0);
      setCurrentMultiplier(1.0);

      toast({
        title: "🎉 $ITLOG TOKEN WON! 🎉",
        description: `You found the exclusive $ITLOG token and won ${reward.toLocaleString()} tokens!`,
      });
    } else {
      console.log('Safe tile revealed! Playing diamond sound...');
      playDiamondSound();
      const newRevealed = tilesRevealed + 1;
      setTilesRevealed(newRevealed);
      setCurrentMultiplier(calculateMultiplier(newRevealed, parseInt(minesCount)));
    }

    setGameBoard(newBoard);
  };

  const cashOut = async () => {
    if (!gameStarted || gameOver) return;
    
    try {
      const winnings = parseFloat(currentBet) * currentMultiplier;
      
      await updateBalance.mutateAsync({
        coinsChange: winnings
      });

      await trackGameWin(winnings, 'mines');

      await addHistoryEntry({
        game_type: 'mines',
        bet_amount: parseFloat(currentBet),
        result_type: 'win',
        win_amount: winnings,
        loss_amount: 0,
        multiplier: currentMultiplier,
        game_details: { 
          tilesRevealed, 
          minesCount: parseInt(minesCount),
          cashedOut: true 
        }
      });

      setGameStarted(false);
      setGameOver(false);
      setGameState(null);
      setGameBoard(Array(25).fill("hidden"));
      setTilesRevealed(0);
      setCurrentMultiplier(1.0);
      
      console.log('Cash out successful! Playing win sound...');
      playWinSound();
      
      toast({
        title: "Cashed out successfully!",
        description: `You won ${winnings.toFixed(2)} coins with a ${currentMultiplier.toFixed(2)}x multiplier!`
      });
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to cash out. Please try again.",
        variant: "destructive"
      });
    }
  };

  const renderTile = (index: number) => {
    const tileState = gameBoard[index];
    let content = "";
    let className = "w-14 h-14 sm:w-16 sm:h-16 rounded-xl border-2 flex items-center justify-center transition-all duration-300 transform hover:scale-105 cursor-pointer text-2xl font-bold ";

    switch (tileState) {
      case "hidden":
        className += "bg-gradient-to-br from-gray-700 to-gray-800 border-gray-600 hover:border-purple-400 hover:shadow-lg hover:shadow-purple-400/50";
        content = "?";
        break;
      case "safe":
        className += "bg-gradient-to-br from-emerald-500 to-green-600 border-emerald-400 shadow-lg shadow-emerald-500/50";
        content = "💎";
        break;
      case "mine":
        className += "bg-gradient-to-br from-red-500 to-red-600 border-red-400 shadow-lg shadow-red-500/50";
        content = "💣";
        break;
      case "itlog":
        className += "bg-gradient-to-br from-amber-500 to-orange-500 border-amber-400 shadow-lg shadow-amber-500/50";
        content = "🪙";
        break;
    }

    return (
      <div
        key={index}
        className={className}
        onClick={() => revealTile(index)}
      >
        <span>{content}</span>
      </div>
    );
  };

  useEffect(() => {
    return () => {
      if (user) {
        const sessionDuration = Math.floor((Date.now() - sessionStartTime) / 1000);
        trackGameSession('mines', sessionDuration, sessionId);
      }
    };
  }, [user, trackGameSession, sessionId, sessionStartTime]);

  return (
    <Layout>
      {isBanned && <BannedOverlay />}
      <div className="casino-game-container py-8">
        <div className="responsive-container">
          <div className="casino-game-header">
            <h1 className="casino-game-title">
              Mines
            </h1>
            <p className="casino-game-subtitle">
              Navigate through the minefield and cash out before hitting a mine!
            </p>
            {!audioEnabled && (
              <div className="text-center mt-4">
                <Button onClick={enableAudio} className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                  🔊 Enable Sound Effects
                </Button>
                <p className="text-sm text-gray-400 mt-2">Click to enable audio for the game</p>
              </div>
            )}
          </div>

          <div className="responsive-grid">
            <div className="responsive-game-grid">
              <div className="casino-game-area">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                  <h2 className="casino-game-area-title">Game Board</h2>
                  {gameStarted && !gameOver && (
                    <Badge className="bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-2 text-lg font-bold rounded-full shadow-lg">
                      <Sparkles className="w-5 h-5 mr-2" />
                      {currentMultiplier.toFixed(2)}x
                    </Badge>
                  )}
                </div>
                
                <div className="grid grid-cols-5 gap-2 sm:gap-3 mb-8 max-w-lg mx-auto">
                  {Array.from({ length: 25 }, (_, i) => renderTile(i))}
                </div>
                
                {gameStarted && !gameOver && (
                  <Button 
                    onClick={cashOut}
                    className="casino-secondary-button"
                  >
                    <DollarSign className="w-5 h-5 mr-2" />
                    Cash Out {(parseFloat(currentBet) * currentMultiplier).toFixed(2)} coins
                  </Button>
                )}
              </div>

              <div className="block sm:hidden">
                <GameHistory gameType="mines" maxHeight="300px" />
              </div>

              <div className="hidden sm:block">
                <GameHistory gameType="mines" maxHeight="400px" />
              </div>
            </div>

            <div className="responsive-control-panel">
              <div className="casino-balance-card">
                <p className="casino-balance-label">Coins Balance</p>
                <p className="casino-balance-amount">{balance.toFixed(2)}</p>
              </div>

              {activePetBoosts.find(boost => boost.trait_type === 'luck_boost') && (
                <div className="casino-luck-boost-card">
                  <div className="casino-luck-icon">
                    <Sparkles className="text-white font-bold text-xl" />
                  </div>
                  <p className="text-lg font-bold mb-2 text-emerald-400">Luck Boost Active!</p>
                  <p className="text-sm text-gray-300">
                    +{(((activePetBoosts.find(boost => boost.trait_type === 'luck_boost')?.total_boost || 1) - 1) * 100).toFixed(1)}% Better odds!
                  </p>
                </div>
              )}

              <div className="casino-settings-card">
                <h3 className="casino-settings-title">
                  <Target className="w-6 h-6 inline mr-2" />
                  Game Settings
                </h3>
                <div className="casino-control-panel">
                  <div className="casino-input-group">
                    <label className="casino-input-label">Bet Amount</label>
                    <Select value={currentBet} onValueChange={setCurrentBet} disabled={gameStarted}>
                      <SelectTrigger className="casino-select">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {betAmounts.map(amount => (
                          <SelectItem key={amount} value={amount}>{amount} coins</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="casino-input-group">
                    <label className="casino-input-label">Number of Mines</label>
                    <Select value={minesCount} onValueChange={setMinesCount} disabled={gameStarted}>
                      <SelectTrigger className="casino-select">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {minesOptions.map(count => (
                          <SelectItem key={count} value={count}>{count} mines</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <Button 
                    onClick={startGame} 
                    className="casino-primary-button"
                    disabled={gameStarted}
                  >
                    {gameStarted ? "Game in Progress" : "Start New Game"}
                  </Button>
                </div>
              </div>

              <div className="casino-info-card">
                <div className="casino-info-icon">
                  <span className="text-black font-bold text-2xl">₿</span>
                </div>
                <p className="text-lg font-bold mb-2 text-amber-400">$ITLOG Token</p>
                <p className="text-sm text-gray-300">
                  5% chance to find the exclusive $ITLOG token worth 10,000-1M tokens!
                </p>
              </div>

              {gameStarted && (
                <div className="casino-stats-card">
                  <h3 className="casino-stats-title">
                    <TrendingUp className="w-5 h-5 inline mr-2" />
                    Game Stats
                  </h3>
                  <div className="space-y-3">
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Tiles Revealed</span>
                      <span className="casino-stat-value">{tilesRevealed}</span>
                    </div>
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Current Multiplier</span>
                      <span className="casino-stat-value">{currentMultiplier.toFixed(2)}x</span>
                    </div>
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Potential Win</span>
                      <span className="casino-stat-value">{(parseFloat(currentBet) * currentMultiplier).toFixed(2)} coins</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Mines;
