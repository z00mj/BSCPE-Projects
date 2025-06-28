import { useState, useEffect, useRef } from "react";
import { useAuth } from "@/hooks/use-auth";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiRequest } from "@/lib/queryClient";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToast } from "@/hooks/use-toast";
import { formatNumber, getBetAmounts } from "@/lib/utils";
import { Link } from "wouter";
import { ArrowLeft, Gem, Star, Heart, Diamond, Club, Spade } from "lucide-react";

export default function Paldo() {
  const { user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [betAmount, setBetAmount] = useState("");
  const [isSpinning, setIsSpinning] = useState(false);
  const [reels, setReels] = useState([
    ['A', 'K', 'Q'],
    ['J', '10', '9'],
    ['♠', '♥', '♦'],
    ['♣', 'A', 'K'],
    ['Q', 'J', '10']
  ]);
  const [lastResult, setLastResult] = useState<any>(null);
  const [freeSpins, setFreeSpins] = useState(0);
  const [inFreeSpins, setInFreeSpins] = useState(false);
  const [winningPositions, setWinningPositions] = useState<{reel: number, row: number}[]>([]);
  const [winningPaylines, setWinningPaylines] = useState<{reel: number, row: number}[][]>([]);
  const [autospinCount, setAutospinCount] = useState(0);
  const [turbo, setTurbo] = useState(false);
  const autospinRef = useRef(0);
  const spinIntervalRef = useRef<NodeJS.Timeout | null>(null);

  const symbols = ['A', 'K', 'Q', 'J', '10', '9', '♠', '♥', '♦', '♣', '⭐', '💎'];

  const getSymbolIcon = (symbol: string) => {
    switch (symbol) {
      case '♠': return <Spade className="w-8 h-8 text-black" />;
      case '♥': return <Heart className="w-8 h-8 text-red-500" />;
      case '♦': return <Diamond className="w-8 h-8 text-red-500" />;
      case '♣': return <Club className="w-8 h-8 text-black" />;
      case '⭐': return <Star className="w-8 h-8 text-yellow-400" />;
      case '💎': return <Gem className="w-8 h-8 text-casino-orange" />;
      default: return <span className="text-white font-bold text-lg">{symbol}</span>;
    }
  };

  // --- Backend Integration ---
  const playGameMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiRequest('POST', '/api/games/play', data);
      return response.json();
    },
    onSuccess: (data) => {
      // Wait until the 2s animation is done before showing result
      setTimeout(() => {
        setReels(data.reels);
        setLastResult(data);
        if (data.winningPositions) setWinningPositions(data.winningPositions);
        else setWinningPositions([]);
        if (data.winningPaylines) setWinningPaylines(data.winningPaylines);
        else setWinningPaylines([]);

        // Free spins logic
        if (data.freeSpinsAwarded) {
          setFreeSpins((prev) => prev + data.freeSpinsAwarded);
          setInFreeSpins(true);
          toast({
            title: "Free Spins Triggered! 🎰",
            description: `You won ${data.freeSpinsAwarded} free spins!`,
          });
        }

        // Win toast
        if (data.winAmount > 0) {
          toast({
            title: "Winner! 🎉",
            description: `You won ${formatNumber(data.winAmount)} coins!`,
          });
        }

        // Bonus toast
        if (data.bbcWon > 0) {
          toast({
            title: "Bonus $BBC! 💎",
            description: `You earned ${formatNumber(data.bbcWon, 6)} $BBC tokens!`,
          });
        }

        // Update user balance
        queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });

        // Free spins decrement
        if (inFreeSpins) {
          setFreeSpins((prev) => prev - 1);
          if (freeSpins <= 1) setInFreeSpins(false);
        }
      }, 2000);
    },
    onError: (error: any) => {
      setIsSpinning(false);
      if (spinIntervalRef.current) clearInterval(spinIntervalRef.current);
      setReels([
        ['A', 'K', 'Q'],
        ['J', '10', '9'],
        ['♠', '♥', '♦'],
        ['♣', 'A', 'K'],
        ['Q', 'J', '10']
      ]);
      toast({
        title: "Game error",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const handleSpin = (_auto?: boolean) => {
    setIsSpinning(true);

    // Start animation: change reels every 100ms
    if (spinIntervalRef.current) clearInterval(spinIntervalRef.current);
    spinIntervalRef.current = setInterval(() => {
      setReels(Array.from({ length: 5 }, () =>
        Array.from({ length: 3 }, () => {
          const symbols = ["A", "K", "Q", "J", "10", "9", "♠", "♥", "♦", "♣", "⭐", "💎"];
          return symbols[Math.floor(Math.random() * symbols.length)];
        })
      ));
    }, 100);

    // Request backend result
    playGameMutation.mutate({
      gameType: 'paldo',
      betAmount: inFreeSpins ? "0" : betAmount,
      gameData: { freeSpins: inFreeSpins }
    });

    // Always stop spinning after 2 seconds, even if backend is slow
    setTimeout(() => {
      if (spinIntervalRef.current) {
        clearInterval(spinIntervalRef.current);
        spinIntervalRef.current = null;
      }
      setIsSpinning(false);
    }, 2000);
  };

  // After a spin completes, trigger the next autospin if needed
  useEffect(() => {
    if (
      !isSpinning &&
      autospinRef.current > 0 &&
      !inFreeSpins &&
      (!lastResult || !lastResult.freeSpinsAwarded)
    ) {
      autospinRef.current -= 1;
      setAutospinCount(autospinRef.current);
      setTimeout(() => handleSpin(true), turbo ? 200 : 1200);
    }
  }, [isSpinning, lastResult, inFreeSpins, turbo]);

  useEffect(() => {
    return () => {
      if (spinIntervalRef.current) clearInterval(spinIntervalRef.current);
    };
  }, []);

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-6">
        <Link href="/games">
          <Button variant="outline" className="casino-button-secondary">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Games
          </Button>
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Game Area */}
        <Card className="casino-card lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-2xl text-white flex items-center">
              🎰 Paldo!
              <span className="ml-auto bg-casino-red text-white px-3 py-1 rounded-full text-sm font-bold">
                25 Paylines
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {/* Free Spins Banner */}
            {inFreeSpins && (
              <div className="bg-casino-gold text-casino-dark rounded-lg p-4 mb-4 text-center font-bold">
                🎰 FREE SPINS MODE - {freeSpins} spins remaining!
              </div>
            )}

            {/* Slot Machine */}
            <div className="bg-casino-black rounded-xl p-6 mb-6" style={{position: "relative"}}>
              {/* SVG overlay for winning paylines */}
              <svg
                className="absolute top-0 left-0 w-full h-full pointer-events-none z-20"
                width={5 * 56}
                height={3 * 88}
                style={{width: "100%", height: "100%"}}
              >
                {winningPaylines.map((payline, idx) => (
                  <polyline
                    key={idx}
                    points={payline.map(({reel, row}) => {
                      // Adjust these numbers to match your symbol size/gap
                      const x = (reel + 0.5) * 56;
                      const y = (row + 0.5) * 88;
                      return `${x},${y}`;
                    }).join(" ")}
                    fill="none"
                    stroke="#FFD700"
                    strokeWidth="6"
                    opacity="0.7"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                    style={{ filter: "drop-shadow(0 0 8px gold)" }}
                  />
                ))}
              </svg>
              {Array.isArray(reels) && reels.length === 5 && reels.every(col => Array.isArray(col) && col.length === 3) ? (
                <div className="grid grid-cols-5 gap-2 mb-4 relative z-10">
                  {reels.map((reel, reelIndex) => (
                    <div key={reelIndex} className="space-y-2">
                      {reel.map((symbol, symbolIndex) => {
                        const isWinning = winningPositions.some(
                          pos => pos.reel === reelIndex && pos.row === symbolIndex
                        );
                        return (
                          <div
                            key={`${reelIndex}-${symbolIndex}`}
                            className={`
                              h-20 border-2 rounded-lg flex items-center justify-center
                              ${isSpinning ? 'animate-slot-spin' : ''}
                              ${isWinning ? 'winning-symbol' : ''}
                              ${symbol === '💎' ? 'bg-casino-gold/20' :
                                symbol === '⭐' ? 'bg-yellow-400/20' :
                                'bg-casino-dark'}
                              ${isWinning ? 'border-yellow-400 shadow-lg shadow-yellow-400/40' : 'border-casino-orange/30'}
                            `}
                            style={{
                              transition: "border 0.2s, box-shadow 0.2s",
                              position: "relative",
                              zIndex: isWinning ? 30 : 10,
                            }}
                          >
                            {getSymbolIcon(symbol)}
                          </div>
                        );
                      })}
                    </div>
                  ))}
                </div>
              ) : (
                // fallback UI if reels is invalid
                <div className="text-center text-gray-400">Loading reels...</div>
              )}
              <div className="text-center text-gray-400 text-sm">
                25 Active Paylines
              </div>
            </div>

            {/* Result Display */}
            {lastResult && (
              <div className="bg-casino-dark rounded-lg p-4 mb-4">
                <div className="text-center">
                  <div className="text-casino-gold text-xl font-bold mb-2">
                    Win: {formatNumber(lastResult.winAmount)} coins
                  </div>
                  {lastResult.bbcWon > 0 && (
                    <div className="text-casino-orange text-lg font-bold">
                      Bonus: {formatNumber(lastResult.bbcWon, 6)} $BBC
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Spin Button */}
            <Button
              onClick={() => handleSpin()}
              disabled={isSpinning}
              className="w-full casino-button text-xl py-6"
            >
              {isSpinning ? "SPINNING..." : inFreeSpins ? "FREE SPIN!" : "SPIN!"}
            </Button>

            {/* Autospin and Turbo Controls */}
            <div className="flex gap-2 mt-4">
              {[10, 25, 50, 100].map((count) => (
                <Button
                  key={count}
                  disabled={isSpinning || autospinCount > 0}
                  onClick={() => {
                    autospinRef.current = count;
                    setAutospinCount(count);
                    handleSpin(true);
                  }}
                  className="casino-button-secondary"
                >
                  Autospin {count}
                </Button>
              ))}
              <Button
                onClick={() => setTurbo((t) => !t)}
                className={turbo ? "casino-button bg-yellow-400" : "casino-button-secondary"}
              >
                Turbo: {turbo ? "ON" : "OFF"}
              </Button>
              {autospinCount > 0 && (
                <span className="ml-2 text-casino-gold font-bold">
                  Autospins left: {autospinCount}
                </span>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Betting Interface & Info */}
        <div className="space-y-6">
          {/* Betting Panel */}
          <Card className="casino-card">
            <CardHeader>
              <CardTitle className="text-xl text-white">
                {inFreeSpins ? "Free Spins Active" : "Place Your Bet"}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {!inFreeSpins && (
                <>
                  {/* Quick Bet Buttons */}
                  <div>
                    <label className="block text-sm font-medium text-gray-400 mb-2">Quick Bet</label>
                    <div className="grid grid-cols-2 gap-2">
                      {getBetAmounts().slice(0, 6).map((amount) => (
                        <Button
                          key={amount}
                          onClick={() => setBetAmount(amount.toString())}
                          variant="outline"
                          size="sm"
                          className="casino-button-secondary"
                        >
                          {amount}
                        </Button>
                      ))}
                    </div>
                  </div>

                  {/* Custom Amount */}
                  <div>
                    <label className="block text-sm font-medium text-gray-400 mb-2">Custom Amount</label>
                    <Input
                      type="number"
                      placeholder="Enter bet amount"
                      value={betAmount}
                      onChange={(e) => setBetAmount(e.target.value)}
                      className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                      step="0.01"
                      min="0.25"
                      max="1000"
                    />
                  </div>
                </>
              )}

              {/* Balance Display */}
              <div className="bg-casino-black rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <span className="text-gray-400">Your Balance:</span>
                  <span className="text-casino-gold font-bold">
                    {user ? formatNumber(user.balance) : "0.00"} coins
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Paytable */}
          <Card className="casino-card">
            <CardHeader>
              <CardTitle className="text-lg text-white">Paytable</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Gem className="w-4 h-4 text-casino-orange" />
                  <span className="text-white">5 Scatters</span>
                </div>
                <span className="text-casino-gold">Jackpot + 20 Free Spins</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Gem className="w-4 h-4 text-casino-orange" />
                  <span className="text-white">4 Scatters</span>
                </div>
                <span className="text-casino-gold">15 Free Spins</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Gem className="w-4 h-4 text-casino-orange" />
                  <span className="text-white">3 Scatters</span>
                </div>
                <span className="text-casino-gold">10 Free Spins</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Star className="w-4 h-4 text-yellow-400" />
                  <span className="text-white">Wild Symbol</span>
                </div>
                <span className="text-casino-gold">Substitutes All</span>
              </div>
            </CardContent>
          </Card>

          {/* Game Features */}
          <Card className="casino-card">
            <CardHeader>
              <CardTitle className="text-lg text-white">Features</CardTitle>
            </CardHeader>
            <CardContent className="text-sm text-gray-400 space-y-2">
              <div>• Wilds substitute for all symbols except scatters</div>
              <div>• 3+ Scatters trigger free spins</div>
              <div>• Free spins can be retriggered</div>
              <div>• Enhanced multipliers during free spins</div>
              <div>• Progressive jackpot on max bet</div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
