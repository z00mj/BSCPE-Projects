import { useState, useEffect } from "react";
import { useProfile } from "@/hooks/useProfile";
import Layout from "@/components/Layout";
import { useBannedCheck } from "@/hooks/useBannedCheck";
import { useQuestTracker } from "@/hooks/useQuestTracker";
import { useActivityTracker } from "@/hooks/useActivityTracker";
import { useAuth } from "@/hooks/useAuth";
import { usePetSystem } from "@/hooks/usePetSystem";
import { useGameHistory } from "@/hooks/useGameHistory";
import { useGameSounds } from "@/hooks/useGameSounds";
import GameHistory from "@/components/GameHistory";
import BannedOverlay from "@/components/BannedOverlay";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useToast } from "@/components/ui/use-toast";
import { Sparkles, TrendingUp, Target, DollarSign, Volume2 } from "lucide-react";

const FortuneReels = () => {
  const [currentBet, setCurrentBet] = useState("1");
  const [isSpinning, setIsSpinning] = useState(false);
  const [reels, setReels] = useState(["🍒", "🍒", "🍒"]);
  const [lastWin, setLastWin] = useState(0);
  const [gameEnded, setGameEnded] = useState(false);
  const { profile, updateBalance } = useProfile();
  const [balance, setBalance] = useState(0);
  const { toast } = useToast();
  const { isBanned } = useBannedCheck();
  const { trackGameWin, trackGameLoss, trackGamePlay, trackBet } = useQuestTracker();
  const { trackGameSession } = useActivityTracker();
  const { user } = useAuth();
  const { activePetBoosts } = usePetSystem();
  const { addHistoryEntry } = useGameHistory();
  const { playWheelSpinSound, playWheelStopSound, playWinSound, playLossSound, playJackpotSound, audioEnabled, enableAudio } = useGameSounds();

  const [sessionId] = useState(`fortune-reels_session_${Date.now()}`);
  const [sessionStartTime] = useState(Date.now());

  useEffect(() => {
    if (profile) {
      setBalance(profile.coins);
    }
  }, [profile]);

  useEffect(() => {
    return () => {
      if (user) {
        const sessionDuration = Math.floor((Date.now() - sessionStartTime) / 1000);
        trackGameSession('fortune-reels', sessionDuration, sessionId);
      }
    };
  }, [user, trackGameSession, sessionId, sessionStartTime]);

  const betAmounts = ["1", "5", "10", "25", "50", "100", "250", "500", "1000", "2500", "5000"];

  const symbols = ["🍒", "🍋", "🍑", "🔔", "💎", "⭐", "7️⃣", "🪙"];
  
  const payTable = {
    "🍒🍒🍒": 5,      // Cherries - 5x
    "🍋🍋🍋": 10,     // Lemons - 10x
    "🍑🍑🍑": 15,     // Peaches - 15x
    "🔔🔔🔔": 25,     // Bells - 25x
    "💎💎💎": 50,     // Diamonds - 50x
    "⭐⭐⭐": 100,     // Stars - 100x
    "7️⃣7️⃣7️⃣": 500,    // Sevens - 500x
    "🪙🪙🪙": 0,      // $ITLOG Jackpot
  };

  const payTableDisplay = [
    { symbols: "🍒🍒🍒", multiplier: 5, chance: "6%" },
    { symbols: "🍋🍋🍋", multiplier: 10, chance: "4%" },
    { symbols: "🍑🍑🍑", multiplier: 15, chance: "3%" },
    { symbols: "🔔🔔🔔", multiplier: 25, chance: "2.5%" },
    { symbols: "💎💎💎", multiplier: 50, chance: "2%" },
    { symbols: "⭐⭐⭐", multiplier: 100, chance: "1.5%" },
    { symbols: "7️⃣7️⃣7️⃣", multiplier: 500, chance: "1%" },
    { symbols: "🪙🪙🪙", multiplier: "JACKPOT", chance: "0.1%" },
  ];

  const spin = async () => {
    if (parseFloat(currentBet) > balance) {
      toast({
        title: "Insufficient balance",
        description: "You don't have enough coins to place this bet.",
        variant: "destructive"
      });
      return;
    }

    setIsSpinning(true);
    setGameEnded(false);
    setLastWin(0);
    const betAmount = parseFloat(currentBet);

    // Play spin sound
    playWheelSpinSound();

    // Update balance immediately and in database
    try {
      await updateBalance.mutateAsync({
        coinsChange: -betAmount
      });
      setBalance(prev => prev - betAmount);
      
      // Track bet for quest progress
      await trackBet(betAmount, 'fortune-reels');
      
      // Track game play for quest progress
      await trackGamePlay('fortune-reels');
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to place bet. Please try again.",
        variant: "destructive"
      });
      setIsSpinning(false);
      return;
    }

    // Simulate spinning animation
    const spinDuration = 2000;
    const spinInterval = 100;
    let elapsed = 0;

    const animate = setInterval(() => {
      setReels([
        symbols[Math.floor(Math.random() * symbols.length)],
        symbols[Math.floor(Math.random() * symbols.length)],
        symbols[Math.floor(Math.random() * symbols.length)]
      ]);
      
      elapsed += spinInterval;
      
      if (elapsed >= spinDuration) {
        clearInterval(animate);
        
        // Play stop sound
        playWheelStopSound();
        
        // Generate final result with weighted probabilities based on your image
        // Apply luck boost from active pets
        const luckBoost = activePetBoosts.find(boost => boost.trait_type === 'luck_boost');
        const luckMultiplier = luckBoost ? luckBoost.total_boost : 1.0;
        
        const random = Math.random() * 100; // Convert to percentage for easier comparison
        let finalReels: string[];
        
        // Apply luck boost to winning chances (reduce the random threshold for better odds)
        const adjustedRandom = random / luckMultiplier;
        
        if (adjustedRandom < 0.1) { // 0.1% chance for $ITLOG jackpot (improved with luck)
          finalReels = ["🪙", "🪙", "🪙"];
        } else if (adjustedRandom < 1.1) { // 1% chance for sevens (improved with luck)
          finalReels = ["7️⃣", "7️⃣", "7️⃣"];
        } else if (adjustedRandom < 2.6) { // 1.5% chance for stars (improved with luck)
          finalReels = ["⭐", "⭐", "⭐"];
        } else if (adjustedRandom < 4.6) { // 2% chance for diamonds (improved with luck)
          finalReels = ["💎", "💎", "💎"];
        } else if (adjustedRandom < 7.1) { // 2.5% chance for bells (improved with luck)
          finalReels = ["🔔", "🔔", "🔔"];
        } else if (adjustedRandom < 10.1) { // 3% chance for peaches (improved with luck)
          finalReels = ["🍑", "🍑", "🍑"];
        } else if (adjustedRandom < 14.1) { // 4% chance for lemons (improved with luck)
          finalReels = ["🍋", "🍋", "🍋"];
        } else if (adjustedRandom < 20.1) { // 6% chance for cherries (improved with luck)
          finalReels = ["🍒", "🍒", "🍒"];
        } else {
          // 80.9% chance for no match - generate random non-matching combination
          finalReels = [
            symbols[Math.floor(Math.random() * symbols.length)],
            symbols[Math.floor(Math.random() * symbols.length)],
            symbols[Math.floor(Math.random() * symbols.length)]
          ];
          // Ensure they don't match
          while (finalReels[0] === finalReels[1] && finalReels[1] === finalReels[2]) {
            finalReels[2] = symbols[Math.floor(Math.random() * symbols.length)];
          }
        }
        
        setReels(finalReels);
        setIsSpinning(false);
        setGameEnded(true);
        
        // Check for wins
        const combination = finalReels.join("");
        const key = combination as keyof typeof payTable;
        
        if (key === "🪙🪙🪙") {
          // Play jackpot sound
          playJackpotSound();
          
          // $ITLOG Jackpot: 10,000 to 1,000,000 tokens based on bet amount
          const baseReward = 10000;
          const maxReward = 1000000;
          const betMultiplier = betAmount * 1000; // Scale with bet amount
          const reward = Math.min(baseReward + betMultiplier, maxReward);
          
          updateBalance.mutateAsync({
            itlogChange: reward
          }).then(async () => {
            // Track the win for quest progress (convert ITLOG to coin equivalent for tracking)
            await trackGameWin(reward * 0.01, 'fortune-reels'); // Assuming 1 ITLOG = 0.01 coins equivalent
            
            // Add to game history for $ITLOG win
            await addHistoryEntry({
              game_type: 'fortune-reels',
              bet_amount: betAmount,
              result_type: 'win',
              win_amount: reward, // Store ITLOG amount in win_amount
              loss_amount: 0,
              multiplier: reward / betAmount, // Calculate effective multiplier
              game_details: { 
                reels: finalReels,
                combination,
                symbols: finalReels,
                isItlogWin: true,
                itlogReward: reward
              }
            });
            
            toast({
              title: "🎉 $ITLOG JACKPOT! 🎉",
              description: `Three $ITLOG symbols! You won ${reward.toLocaleString()} $ITLOG tokens!`,
            });
          }).catch(() => {
            toast({
              title: "Error updating $ITLOG balance",
              description: "Please contact support.",
              variant: "destructive"
            });
          });
        } else if (payTable[key]) {
          // Play win sound
          playWinSound();
          
          const winnings = betAmount * payTable[key];
          setLastWin(winnings);
          
          updateBalance.mutateAsync({
            coinsChange: winnings
          }).then(async () => {
            setBalance(prev => prev + winnings);
            
            // Track the win for quest progress
            await trackGameWin(winnings, 'fortune-reels');
            
            // Add to game history
            await addHistoryEntry({
              game_type: 'fortune-reels',
              bet_amount: betAmount,
              result_type: 'win',
              win_amount: winnings,
              loss_amount: 0,
              multiplier: payTable[key],
              game_details: { 
                reels: finalReels,
                combination,
                symbols: finalReels
              }
            });
            
            toast({
              title: "Winner!",
              description: `You won ${winnings.toFixed(2)} coins with ${payTable[key]}x multiplier!`
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
          
          // Track the loss for quest progress
          trackGameLoss('fortune-reels');
          
          // Add to game history
          addHistoryEntry({
            game_type: 'fortune-reels',
            bet_amount: betAmount,
            result_type: 'loss',
            win_amount: 0,
            loss_amount: betAmount,
            multiplier: 0,
            game_details: { 
              reels: finalReels,
              combination,
              symbols: finalReels
            }
          });
          
          toast({
            title: "No match",
            description: "Better luck next time!",
            variant: "destructive"
          });
        }
      }
    }, spinInterval);
  };

  const resetGame = () => {
    setGameEnded(false);
    setLastWin(0);
  };

  return (
    <Layout>
      {isBanned && <BannedOverlay />}
      <div className="casino-game-container py-8">
        <div className="responsive-container">
          <div className="casino-game-header">
            <h1 className="casino-game-title">
              Fortune Reels
            </h1>
            <p className="casino-game-subtitle">
              Spin the reels and match symbols for big wins!
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
                  <h2 className="casino-game-area-title">Slot Machine</h2>
                  {lastWin > 0 && (
                    <Badge className="bg-gradient-to-r from-emerald-500 to-green-600 text-white px-6 py-2 text-lg font-bold rounded-full shadow-lg">
                      <Sparkles className="w-5 h-5 mr-2" />
                      Won {lastWin.toFixed(2)} coins
                    </Badge>
                  )}
                </div>

                <div className="flex flex-col items-center space-y-8">
                  {/* Slot Machine Frame */}
                  <div className="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl p-8 shadow-2xl border-4 border-gray-600">
                    {/* Reels Container */}
                    <div className="bg-black/40 rounded-2xl p-6 mb-6 border-2 border-gray-500">
                      <div className="flex justify-center items-center space-x-4">
                        {reels.map((symbol, index) => (
                          <div
                            key={index}
                            className={`w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-white to-gray-100 rounded-xl border-4 flex items-center justify-center text-4xl sm:text-5xl transition-all duration-300 transform hover:scale-105 shadow-lg ${
                              isSpinning 
                                ? "animate-pulse border-yellow-400 shadow-yellow-400/50" 
                                : "border-gray-300 hover:border-blue-400 hover:shadow-blue-400/50"
                            }`}
                          >
                            <span className="drop-shadow-lg">{symbol}</span>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Win Display */}
                    {lastWin > 0 && (
                      <div className="text-center mb-6">
                        <Badge 
                          variant="secondary" 
                          className="text-2xl px-8 py-4 bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg shadow-emerald-500/50 rounded-full font-bold"
                        >
                          <DollarSign className="w-6 h-6 mr-2" />
                          Won: {lastWin.toFixed(2)} coins
                        </Badge>
                      </div>
                    )}

                    {/* Action Button */}
                    <div className="flex justify-center">
                      {!gameEnded ? (
                        <Button 
                          onClick={spin} 
                          className="casino-primary-button min-w-[200px]"
                          disabled={isSpinning}
                        >
                          {isSpinning ? (
                            <>
                              <Sparkles className="w-5 h-5 mr-2 animate-spin" />
                              Spinning...
                            </>
                          ) : (
                            <>
                              <DollarSign className="w-5 h-5 mr-2" />
                              Spin ({currentBet} coins)
                            </>
                          )}
                        </Button>
                      ) : (
                        <Button 
                          onClick={resetGame} 
                          className="casino-secondary-button min-w-[200px]"
                        >
                          Spin Again
                        </Button>
                      )}
                    </div>
                  </div>

                  {/* Current Combination Display */}
                  <div className="text-center bg-gradient-to-br from-gray-800/50 to-gray-900/50 rounded-xl p-4 border border-gray-600">
                    <h3 className="text-lg font-semibold mb-2 text-gray-300">Current Combination</h3>
                    <div className="text-3xl font-bold tracking-wider">
                      {reels.join(" - ")}
                    </div>
                  </div>
                </div>
              </div>

              <div className="block sm:hidden">
                <GameHistory gameType="fortune-reels" maxHeight="300px" />
              </div>

              <div className="hidden sm:block">
                <GameHistory gameType="fortune-reels" maxHeight="400px" />
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
                  Bet Amount
                </h3>
                <div className="casino-control-panel">
                  <div className="casino-input-group">
                    <label className="casino-input-label">Choose Amount</label>
                    <Select value={currentBet} onValueChange={setCurrentBet} disabled={isSpinning}>
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
                </div>
              </div>

              <div className="casino-payout-card">
                <h3 className="casino-payout-title">Pay Table</h3>
                <div className="space-y-2 text-sm">
                  {payTableDisplay.map((item, index) => (
                    <div key={index} className="casino-payout-row">
                      <span className="text-lg">{item.symbols}</span>
                      <div className="flex flex-col items-end">
                        <span className={`font-bold ${item.multiplier === "JACKPOT" ? "text-gold-400" : "text-green-400"}`}>
                          {item.multiplier === "JACKPOT" ? "JACKPOT!" : `${item.multiplier}x`}
                        </span>
                        <span className="text-xs text-gray-400">({item.chance})</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="casino-info-card">
                <div className="casino-info-icon">
                  <span className="text-black font-bold text-2xl">₿</span>
                </div>
                <p className="text-lg font-bold mb-2 text-amber-400">$ITLOG Jackpot</p>
                <p className="text-sm text-gray-300">
                  Get 3 🪙 symbols for 10,000-1M $ITLOG tokens based on your bet!
                </p>
              </div>

              {isSpinning && (
                <div className="casino-stats-card">
                  <h3 className="casino-stats-title">
                    <TrendingUp className="w-5 h-5 inline mr-2" />
                    Current Spin
                  </h3>
                  <div className="space-y-3">
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Bet Amount</span>
                      <span className="casino-stat-value">{currentBet} coins</span>
                    </div>
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Status</span>
                      <span className="casino-stat-value text-yellow-400">Spinning...</span>
                    </div>
                  </div>
                </div>
              )}

              {lastWin > 0 && (
                <div className="casino-stats-card">
                  <h3 className="casino-stats-title">
                    <TrendingUp className="w-5 h-5 inline mr-2" />
                    Last Win
                  </h3>
                  <div className="space-y-3">
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Combination</span>
                      <span className="casino-stat-value">{reels.join("")}</span>
                    </div>
                    <div className="casino-stat-row">
                      <span className="casino-stat-label">Payout</span>
                      <span className="casino-stat-value text-green-400">{lastWin.toFixed(2)} coins</span>
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

export default FortuneReels;
