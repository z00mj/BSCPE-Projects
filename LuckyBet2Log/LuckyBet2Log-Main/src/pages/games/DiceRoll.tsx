import { useState, useEffect } from "react";
import Layout from "@/components/Layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Slider } from "@/components/ui/slider";
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
import { Sparkles, TrendingUp, Target, DollarSign, Dice1, Volume2 } from "lucide-react";

const DiceRoll = () => {
  const [currentBet, setCurrentBet] = useState("1");
  const [prediction, setPrediction] = useState("over");
  const [targetNumber, setTargetNumber] = useState(50);
  const [isRolling, setIsRolling] = useState(false);
  const [lastRoll, setLastRoll] = useState<number | null>(null);
  const [balance, setBalance] = useState(0);
  const [multiplier, setMultiplier] = useState(1.98);
  const [gameEnded, setGameEnded] = useState(false);
  const [showItlogDice, setShowItlogDice] = useState(false);
  const { toast } = useToast();
  const { profile, updateBalance } = useProfile();
  const { isBanned } = useBannedCheck();
  const { trackGameWin, trackGamePlay, trackBet } = useQuestTracker();
  const { trackGameSession } = useActivityTracker();
  const { user } = useAuth();
  const { activePetBoosts } = usePetSystem();
  const { addHistoryEntry, history } = useGameHistory('dice-roll');
  const { playWheelSpinSound, playWheelStopSound, playWinSound, playLossSound, playJackpotSound, audioEnabled, enableAudio } = useGameSounds();

  const [sessionId] = useState(`dice-roll_session_${Date.now()}`);
  const [sessionStartTime] = useState(Date.now());

  // Debug: Log when history changes
  useEffect(() => {
    console.log('DiceRoll: Game history updated, length:', history.length);
  }, [history]);

  useEffect(() => {
    if (profile) {
      setBalance(profile.coins);
    }
  }, [profile]);

  useEffect(() => {
    return () => {
      if (user) {
        const sessionDuration = Math.floor((Date.now() - sessionStartTime) / 1000);
        trackGameSession('dice-roll', sessionDuration, sessionId);
      }
    };
  }, [user, trackGameSession, sessionId, sessionStartTime]);

  const betAmounts = ["1", "5", "10", "25", "50", "100", "250", "500", "1000", "2500", "5000"];

  // Calculate multiplier based on win chance
  const calculateMultiplier = (target: number, isOver: boolean) => {
    const winChance = isOver ? (100 - target) / 100 : target / 100;
    return Math.max(1.01, (0.99 / winChance)); // 1% house edge
  };

  const updateMultiplier = (target: number, pred: string) => {
    const mult = calculateMultiplier(target, pred === "over");
    setMultiplier(mult);
  };

  const rollDice = async () => {
    if (parseFloat(currentBet) > balance) {
      toast({
        title: "Insufficient balance",
        description: "You don't have enough coins to place this bet.",
        variant: "destructive"
      });
      return;
    }

    setIsRolling(true);
    setGameEnded(false);
    setShowItlogDice(false);
    const betAmount = parseFloat(currentBet);

    // Play rolling sound
    playWheelSpinSound();

    // Update balance immediately and in database
    try {
      await updateBalance.mutateAsync({
        coinsChange: -betAmount
      });
      setBalance(prev => prev - betAmount);
      
      // Track bet for quest progress
      await trackBet(betAmount, 'dice-roll');
      
      // Track game play for quest progress
      await trackGamePlay('dice-roll');
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to place bet. Please try again.",
        variant: "destructive"
      });
      setIsRolling(false);
      return;
    }

    // Check for 0.1% chance of $ITLOG dice
    const itlogChance = Math.random();
    const isItlogRoll = itlogChance < 0.001; // 0.1% chance

    if (isItlogRoll) {
      setShowItlogDice(true);
    }

    // Simulate rolling animation
    const rollDuration = 2000;
    const rollInterval = 100;
    let elapsed = 0;

    const animate = setInterval(() => {
      setLastRoll(Math.floor(Math.random() * 100) + 1);
      elapsed += rollInterval;
      
      if (elapsed >= rollDuration) {
        clearInterval(animate);
        
        // Play stop sound
        playWheelStopSound();
        
        // Generate final result
        let finalRoll = Math.floor(Math.random() * 100) + 1;
        
        // Apply luck boost from active pets - bias the roll towards winning
        const luckBoost = activePetBoosts.find(boost => boost.trait_type === 'luck_boost');
        if (luckBoost && luckBoost.total_boost > 1.0) {
          const luckBias = (luckBoost.total_boost - 1.0) * 100; // Convert to percentage
          const shouldBiasRoll = Math.random() < luckBias;
          
          if (shouldBiasRoll) {
            // Bias the roll towards a winning outcome
            if (prediction === "over") {
              // Generate a roll more likely to be above target
              const minWinRoll = Math.min(targetNumber + 1, 100);
              finalRoll = Math.floor(Math.random() * (100 - minWinRoll + 1)) + minWinRoll;
            } else {
              // Generate a roll more likely to be below target
              const maxWinRoll = Math.max(targetNumber - 1, 1);
              finalRoll = Math.floor(Math.random() * maxWinRoll) + 1;
            }
          }
        }
        
        if (isItlogRoll) {
          // Play jackpot sound
          playJackpotSound();
          
          // Force a winning roll for $ITLOG
          if (prediction === "over") {
            finalRoll = Math.max(targetNumber + 1, Math.floor(Math.random() * (100 - targetNumber)) + targetNumber + 1);
          } else {
            finalRoll = Math.min(targetNumber - 1, Math.floor(Math.random() * targetNumber) + 1);
          }
          
          // Calculate $ITLOG reward based on bet amount (10,000 to 1,000,000)
          const baseReward = 10000;
          const maxReward = 1000000;
          const betMultiplier = betAmount * 1000;
          const reward = Math.min(baseReward + betMultiplier, maxReward);
          
          setLastRoll(finalRoll);
          setIsRolling(false);
          setGameEnded(true);
          
          updateBalance.mutateAsync({
            itlogChange: reward
          }).then(async () => {
            // Track the win for quest progress (convert ITLOG to coin equivalent for tracking)
            await trackGameWin(reward * 0.01, 'dice-roll'); // Assuming 1 ITLOG = 0.01 coins equivalent
            
            // Add to game history for $ITLOG win
            await addHistoryEntry({
              game_type: 'dice-roll',
              bet_amount: betAmount,
              result_type: 'win',
              win_amount: reward, // Store ITLOG amount in win_amount
              loss_amount: 0,
              multiplier: reward / betAmount, // Calculate effective multiplier
              game_details: { 
                prediction, 
                targetNumber, 
                finalRoll,
                isWin: true,
                isItlogWin: true,
                itlogReward: reward,
                sessionDuration: Math.floor((Date.now() - sessionStartTime) / 1000)
              }
            });
            
            toast({
              title: "🎉 $ITLOG TOKEN WON! 🎉",
              description: `You rolled ${finalRoll} and won ${reward.toLocaleString()} $ITLOG tokens!`,
            });
          }).catch(() => {
            toast({
              title: "Error updating $ITLOG balance",
              description: "Please contact support.",
              variant: "destructive"
            });
          });
          return;
        }
        
        setLastRoll(finalRoll);
        setIsRolling(false);
        setGameEnded(true);
        
        // Check for win
        const isWin = (prediction === "over" && finalRoll > targetNumber) || 
                     (prediction === "under" && finalRoll < targetNumber);
        
        if (isWin) {
          // Play win sound
          playWinSound();
          
          const winnings = betAmount * multiplier;
          updateBalance.mutateAsync({
            coinsChange: winnings
          }).then(async () => {
            setBalance(prev => prev + winnings);
            
            // Track the win for quest progress
            await trackGameWin(winnings, 'dice-roll');
            
            // Add to game history
            await addHistoryEntry({
              game_type: 'dice-roll',
              bet_amount: betAmount,
              result_type: 'win',
              win_amount: winnings,
              loss_amount: 0,
              multiplier,
              game_details: { 
                prediction, 
                targetNumber, 
                finalRoll,
                isWin: true,
                sessionDuration: Math.floor((Date.now() - sessionStartTime) / 1000)
              }
            });
            
            toast({
              title: "Winner!",
              description: `You rolled ${finalRoll} and won ${winnings.toFixed(2)} coins!`
            });
          }).catch(() => {
            toast({
              title: "Error updating balance",
              description: "Please contact support.",
              variant: "destructive"
            });
          });
        } else {
          // Play loss sound
          playLossSound();
          
          // Add to game history for loss
          addHistoryEntry({
            game_type: 'dice-roll',
            bet_amount: betAmount,
            result_type: 'loss',
            win_amount: 0,
            loss_amount: betAmount,
            multiplier: 0,
            game_details: { 
              prediction, 
              targetNumber, 
              finalRoll,
              isWin: false,
              sessionDuration: Math.floor((Date.now() - sessionStartTime) / 1000)
            }
          });
          
          toast({
            title: "Better luck next time!",
            description: `You rolled ${finalRoll}. Try again!`,
            variant: "destructive"
          });
        }
      }
    }, rollInterval);
  };

  const resetGame = () => {
    setGameEnded(false);
    setLastRoll(null);
    setShowItlogDice(false);
  };

  const winChance = prediction === "over" ? (100 - targetNumber) : targetNumber;
  const potentialWin = parseFloat(currentBet) * multiplier;

  return (
    <Layout>
      {isBanned && <BannedOverlay />}
      <div className="casino-game-container py-8">
        <div className="responsive-container">
          <div className="casino-game-header">
            <h1 className="casino-game-title">
              Dice Roll
            </h1>
            <p className="casino-game-subtitle">
              Roll a number from 1-100 and customize your risk!
            </p>
            {!audioEnabled && (
              <Button 
                onClick={enableAudio} 
                className="mt-4 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-bold shadow-lg"
              >
                <Volume2 className="w-5 h-5 mr-2" />
                Enable Sound Effects
              </Button>
            )}
          </div>

          <div className="responsive-grid">
            <div className="responsive-game-grid">
              <div className="casino-game-area">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                  <h2 className="casino-game-area-title">Dice Roll</h2>
                  {gameEnded && lastRoll && (
                    <Badge 
                      className={`px-6 py-2 text-lg font-bold rounded-full shadow-lg ${
                        showItlogDice
                          ? "bg-gradient-to-r from-gold-500 to-amber-600 text-white shadow-gold-500/50"
                          : ((prediction === "over" && lastRoll > targetNumber) || 
                             (prediction === "under" && lastRoll < targetNumber))
                          ? "bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-emerald-500/50" 
                          : "bg-gradient-to-r from-red-500 to-pink-600 text-white shadow-red-500/50"
                      }`}
                    >
                      {showItlogDice 
                        ? "$ITLOG TOKEN WON!" 
                        : ((prediction === "over" && lastRoll > targetNumber) || 
                           (prediction === "under" && lastRoll < targetNumber))
                        ? "Winner!" 
                        : "Try Again!"
                      }
                    </Badge>
                  )}
                </div>

                <div className="flex flex-col items-center space-y-8">
                  {/* Dice Display */}
                  <div className="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-8 shadow-2xl border-4 border-gray-600">
                    <div className={`w-32 h-32 sm:w-40 sm:h-40 mx-auto rounded-2xl border-4 flex items-center justify-center text-6xl sm:text-7xl font-bold transition-all duration-300 transform ${
                      isRolling 
                        ? "animate-bounce bg-yellow-400/20 border-yellow-400 shadow-lg shadow-yellow-400/50" 
                        : showItlogDice 
                        ? "bg-gradient-to-r from-gold-500 to-amber-500 border-gold-400 shadow-lg shadow-gold-500/50"
                        : "bg-gradient-to-br from-white to-gray-100 border-gray-300 hover:border-blue-400 hover:shadow-lg hover:shadow-blue-400/50"
                    }`}>
                      {showItlogDice ? (
                        <div className="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-yellow-400 via-orange-400 to-pink-500 rounded-full flex items-center justify-center border-4 border-black/20">
                          <span className="text-black font-bold text-3xl sm:text-4xl">₿</span>
                        </div>
                      ) : (
                        <span className={showItlogDice ? "text-black" : "text-black drop-shadow-lg"}>
                          {lastRoll || "?"}
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Prediction Display */}
                  <div className="w-full max-w-md bg-gradient-to-br from-gray-800/50 to-gray-900/50 rounded-xl p-6 border border-gray-600">
                    <h3 className="text-lg font-semibold mb-4 text-center text-gray-300">Your Prediction</h3>
                    <div className="flex items-center justify-center space-x-4 text-lg">
                      <span className="text-muted-foreground">Roll</span>
                      <Badge variant="secondary" className="px-4 py-2 bg-gradient-to-r from-purple-500 to-blue-500 text-white">
                        {prediction.toUpperCase()}
                      </Badge>
                      <span className="text-muted-foreground">than</span>
                      <Badge variant="outline" className="px-4 py-2 text-lg border-blue-400 text-blue-400">
                        {targetNumber}
                      </Badge>
                    </div>
                    <div className="mt-4 text-center">
                      <Badge variant="secondary" className="text-xl px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg">
                        <DollarSign className="w-5 h-5 mr-2" />
                        {multiplier.toFixed(2)}x Multiplier
                      </Badge>
                    </div>
                  </div>

                  {/* Action Button */}
                  <div className="w-full max-w-md">
                    <Button 
                      onClick={gameEnded ? resetGame : rollDice} 
                      className={`w-full text-xl py-6 ${gameEnded ? "casino-secondary-button" : "casino-primary-button"}`}
                      disabled={isRolling}
                    >
                      {isRolling ? (
                        <>
                          <Dice1 className="w-6 h-6 mr-2 animate-spin" />
                          Rolling...
                        </>
                      ) : gameEnded ? (
                        "Roll Again"
                      ) : (
                        <>
                          <DollarSign className="w-6 h-6 mr-2" />
                          Roll Dice ({currentBet} coins)
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </div>

              <div className="block sm:hidden">
                <GameHistory gameType="dice-roll" maxHeight="300px" />
              </div>

              <div className="hidden sm:block">
                <GameHistory gameType="dice-roll" maxHeight="400px" />
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
                    <Select value={currentBet} onValueChange={setCurrentBet} disabled={isRolling}>
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
                    <label className="casino-input-label">Prediction</label>
                    <Select 
                      value={prediction} 
                      onValueChange={(value) => {
                        setPrediction(value);
                        updateMultiplier(targetNumber, value);
                      }} 
                      disabled={isRolling}
                    >
                      <SelectTrigger className="casino-select">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="over">Roll Over</SelectItem>
                        <SelectItem value="under">Roll Under</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="casino-input-group">
                    <label className="casino-input-label">
                      Target Number: {targetNumber}
                    </label>
                    <Slider
                      value={[targetNumber]}
                      onValueChange={(value) => {
                        setTargetNumber(value[0]);
                        updateMultiplier(value[0], prediction);
                      }}
                      max={99}
                      min={1}
                      step={1}
                      disabled={isRolling}
                      className="w-full mt-2"
                    />
                    <div className="flex justify-between text-xs text-muted-foreground mt-1">
                      <span>1</span>
                      <span>99</span>
                    </div>
                  </div>
                </div>
              </div>

              <div className="casino-stats-card">
                <h3 className="casino-stats-title">
                  <TrendingUp className="w-5 h-5 inline mr-2" />
                  Bet Stats
                </h3>
                <div className="space-y-3">
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Win Chance</span>
                    <span className="casino-stat-value text-blue-400">{winChance}%</span>
                  </div>
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Multiplier</span>
                    <span className="casino-stat-value text-green-400">{multiplier.toFixed(2)}x</span>
                  </div>
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Potential Win</span>
                    <span className="casino-stat-value text-green-400">{potentialWin.toFixed(2)} coins</span>
                  </div>
                </div>
              </div>

              <div className="casino-info-card">
                <div className="casino-info-icon">
                  <span className="text-black font-bold text-2xl">₿</span>
                </div>
                <p className="text-lg font-bold mb-2 text-amber-400">$ITLOG Token</p>
                <p className="text-sm text-gray-300">
                  0.1% chance for $ITLOG dice to appear and win 10,000-1M tokens on winning roll!
                </p>
              </div>

              {lastRoll && (
                <div className="casino-stats-card">
                  <h3 className="casino-stats-title">
                    <Dice1 className="w-5 h-5 inline mr-2" />
                    Last Roll
                  </h3>
                  <div className="space-y-3">
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Result</span>
                      <span className="casino-stat-value text-2xl font-bold">{lastRoll}</span>
                    </div>
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Outcome</span>
                      <span className={`casino-stat-value ${
                        showItlogDice 
                          ? "text-gold-400"
                          : ((prediction === "over" && lastRoll > targetNumber) || 
                             (prediction === "under" && lastRoll < targetNumber)) 
                          ? "text-green-400" 
                          : "text-red-400"
                      }`}>
                        {showItlogDice 
                          ? "$ITLOG Token!"
                          : ((prediction === "over" && lastRoll > targetNumber) || 
                             (prediction === "under" && lastRoll < targetNumber)) 
                          ? "Winner!" 
                          : "Try again!"
                        }
                      </span>
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

export default DiceRoll;
