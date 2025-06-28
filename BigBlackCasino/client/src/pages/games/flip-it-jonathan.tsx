import { useState } from "react";
import { useAuth } from "@/hooks/use-auth";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiRequest } from "@/lib/queryClient";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToast } from "@/hooks/use-toast";
import { formatNumber, getBetAmounts } from "@/lib/utils";
import { Link } from "wouter";
import { ArrowLeft, Coins } from "lucide-react";

export default function FlipItJonathan() {
  const { user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [betAmount, setBetAmount] = useState("");
  const [currentStreak, setCurrentStreak] = useState(0);
  const [currentWinnings, setCurrentWinnings] = useState(0);
  const [isFlipping, setIsFlipping] = useState(false);
  const [lastFlip, setLastFlip] = useState<'heads' | 'tails' | null>(null);
  const [gameActive, setGameActive] = useState(false);
  const [choice, setChoice] = useState<'heads' | 'tails' | null>(null);

  const getMultiplier = (streak: number) => {
    const multipliers = [1.5, 2, 2.5, 3, 4, 5, 7, 10, 15, 20];
    return multipliers[Math.min(streak, multipliers.length - 1)] || 20;
  };

  const playGameMutation = useMutation({
    mutationFn: async (data: any) => {
      // Generate the flip result here for full frontend control
      const flipResult = Math.random() > 0.5 ? 'heads' : 'tails';
      return { flipResult };
    },
    onSuccess: (data) => {
      setIsFlipping(false);
      const flipResult = data.flipResult;
      setLastFlip(flipResult as 'heads' | 'tails');
      const isCorrect = choice === flipResult;
      if (isCorrect) {
        const newStreak = currentStreak + 1;
        const newWinnings = parseFloat(betAmount) * getMultiplier(newStreak);
        setCurrentStreak(newStreak);
        setCurrentWinnings(newWinnings);
        toast({
          title: "Correct! 🎉",
          description: `Streak: ${newStreak}. Current winnings: ${formatNumber(newWinnings)} coins`,
        });
      } else {
        toast({
          title: "Wrong choice!",
          description: "Your streak has ended. Better luck next time!",
          variant: "destructive",
        });
        setGameActive(false);
        setCurrentStreak(0);
        setCurrentWinnings(0);
        queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
      }
    },
    onError: (error: any) => {
      setIsFlipping(false);
      toast({
        title: "Game error",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const startGame = () => {
    if (!betAmount || parseFloat(betAmount) <= 0) {
      toast({
        title: "Invalid bet",
        description: "Please enter a valid bet amount",
        variant: "destructive",
      });
      return;
    }

    if (!user || parseFloat(betAmount) > parseFloat(user.balance)) {
      toast({
        title: "Insufficient funds",
        description: "You don't have enough coins for this bet",
        variant: "destructive",
      });
      return;
    }

    setGameActive(true);
    setCurrentStreak(0);
    setCurrentWinnings(parseFloat(betAmount));
    setLastFlip(null);
  };

  const makeChoice = (selectedChoice: 'heads' | 'tails') => {
    if (isFlipping) return;
    
    setChoice(selectedChoice);
    setIsFlipping(true);
    
    // Animate coin flip
    const coin = document.getElementById('coin');
    if (coin) {
      coin.classList.add('flip-coin');
      setTimeout(() => coin.classList.remove('flip-coin'), 1000);
    }

    setTimeout(() => {
      playGameMutation.mutate({
        gameType: 'flip-it-jonathan',
        betAmount: currentWinnings.toString(),
        gameData: { choice: selectedChoice }
      });
    }, 1000);
  };

  const cashOut = () => {
    if (currentWinnings > 0) {
      toast({
        title: "Cashed out!",
        description: `You won ${formatNumber(currentWinnings)} coins!`,
      });
      
      // Add winnings to balance (this would be handled by the backend in a real scenario)
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    }
    
    setGameActive(false);
    setCurrentStreak(0);
    setCurrentWinnings(0);
    setLastFlip(null);
    setChoice(null);
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="mb-6">
        <Link href="/games">
          <Button variant="outline" className="casino-button-secondary">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Games
          </Button>
        </Link>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Game Area */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-2xl text-white flex items-center">
              🪙 Flip it Jonathan!
              <span className="ml-auto bg-casino-gold text-casino-dark px-3 py-1 rounded-full text-sm font-bold">
                Streak Game
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {/* Coin Area */}
            <div className="bg-casino-black rounded-xl p-6 mb-6">
              <div className="text-center">
                <div 
                  id="coin"
                  className={`w-32 h-32 rounded-full mx-auto mb-4 flex items-center justify-center cursor-pointer transition-transform hover:scale-105 text-6xl font-bold select-none ${lastFlip === 'heads' ? 'bg-casino-gold text-casino-dark border-4 border-casino-gold' : lastFlip === 'tails' ? 'bg-casino-red text-white border-4 border-casino-red' : 'bg-gray-700 text-white border-4 border-gray-700'}`}
                >
                  {lastFlip === 'heads' && 'H'}
                  {lastFlip === 'tails' && 'T'}
                  {!lastFlip && <span className="text-4xl text-casino-gold"><Coins className="w-12 h-12" /></span>}
                </div>
                
                {lastFlip && (
                  <div className="mb-4">
                    <div className="text-white text-lg">
                      Result: <span className="text-casino-orange font-bold capitalize">{lastFlip}</span>
                    </div>
                    <div className="text-gray-400 text-sm">
                      Your choice: <span className="capitalize">{choice}</span>
                    </div>
                  </div>
                )}
                
                <div className="space-y-2">
                  <div className="text-white">
                    Current Streak: <span className="text-casino-gold font-bold">{currentStreak}</span>
                  </div>
                  <div className="text-white">
                    Current Winnings: <span className="text-casino-orange font-bold">
                      {formatNumber(currentWinnings)} coins
                    </span>
                  </div>
                  {gameActive && (
                    <div className="text-white">
                      Next Multiplier: <span className="text-casino-red font-bold">
                        {getMultiplier(currentStreak + 1)}x
                      </span>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Choice Buttons */}
            {gameActive && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <Button
                    onClick={() => makeChoice('heads')}
                    disabled={isFlipping}
                    className="casino-button py-6 text-lg"
                  >
                    <div className="text-center">
                      <div className="text-2xl mb-1">⚪</div>
                      <div>HEADS</div>
                    </div>
                  </Button>
                  <Button
                    onClick={() => makeChoice('tails')}
                    disabled={isFlipping}
                    variant="outline"
                    className="casino-button-secondary py-6 text-lg"
                  >
                    <div className="text-center">
                      <div className="text-2xl mb-1">⚫</div>
                      <div>TAILS</div>
                    </div>
                  </Button>
                </div>
                
                {currentStreak > 0 && (
                  <div className="text-center">
                    <Button
                      onClick={cashOut}
                      className="bg-casino-gold hover:bg-yellow-500 text-casino-dark font-bold px-8 py-3"
                    >
                      Cash Out: {formatNumber(currentWinnings)} coins
                    </Button>
                  </div>
                )}
              </div>
            )}

            {/* Multiplier Path */}
            {gameActive && (
              <div className="mt-6 bg-casino-dark rounded-lg p-4">
                <h4 className="text-white font-bold mb-3">Multiplier Path:</h4>
                <div className="grid grid-cols-5 gap-2 text-xs">
                  {[1.5, 2, 2.5, 3, 4, 5, 7, 10, 15, 20].map((mult, index) => (
                    <div
                      key={index}
                      className={`
                        text-center py-2 rounded
                        ${index < currentStreak 
                          ? 'bg-green-600 text-white' 
                          : index === currentStreak 
                          ? 'bg-casino-orange text-white' 
                          : 'bg-gray-600 text-gray-300'
                        }
                      `}
                    >
                      {mult}x
                    </div>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Betting Interface */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">
              {gameActive ? "Game in Progress" : "Start New Game"}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!gameActive && (
              <>
                {/* Quick Bet Buttons */}
                <div>
                  <label className="block text-sm font-medium text-gray-400 mb-2">Quick Bet</label>
                  <div className="grid grid-cols-4 gap-2">
                    {getBetAmounts().slice(0, 8).map((amount) => (
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

                {/* Start Game Button */}
                <Button
                  onClick={startGame}
                  disabled={!betAmount}
                  className="w-full casino-button text-xl py-4"
                >
                  START FLIPPING!
                </Button>
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

            {/* Game Rules */}
            <div className="bg-casino-dark rounded-lg p-4 text-sm text-gray-400">
              <h4 className="text-white font-bold mb-2">Game Rules:</h4>
              <ul className="space-y-1">
                <li>• Choose heads or tails for each flip</li>
                <li>• Correct guess increases your multiplier</li>
                <li>• Cash out anytime to keep your winnings</li>
                <li>• Wrong guess ends the game and you lose everything</li>
                <li>• Maximum multiplier: 20x</li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
