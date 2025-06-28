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
import { ArrowLeft, DollarSign } from "lucide-react";

export default function LuckAndRoll() {
  const { user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [betAmount, setBetAmount] = useState("");
  const [isSpinning, setIsSpinning] = useState(false);
  const [lastResult, setLastResult] = useState<any>(null);
  const [wheelRotation, setWheelRotation] = useState(0);

  // Distribute bankrupt slices for more randomness
  const wheelOutcomes = [
    'bankrupt', 1.1, 'bankrupt', 1.3, 'bankrupt', 1.5, 1.8, 'bankrupt',
    2.0, 4.0, 'bankrupt', 5.0, 8.0, 'bankrupt', 10.0, 'jackpot'
  ];

  const getSliceColor = (outcome: any) => {
    if (outcome === 'bankrupt') return 'bg-red-600';
    if (outcome === 'jackpot') return 'bg-casino-gold';
    return 'bg-casino-orange';
  };

  const playGameMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiRequest('POST', '/api/games/play', data);
      return response.json();
    },
    onSuccess: (data) => {
      setLastResult(data);
      setIsSpinning(false);
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
      
      const outcome = data.result.outcome;
      if (outcome === 'bankrupt') {
        toast({
          title: "Bankrupt!",
          description: "Better luck next time!",
          variant: "destructive",
        });
      } else if (outcome === 'jackpot') {
        toast({
          title: "JACKPOT! 🎉",
          description: `You won ${formatNumber(data.bbcWon, 6)} $BBC tokens!`,
        });
      } else {
        toast({
          title: "Winner!",
          description: `You won ${formatNumber(data.winAmount)} coins with ${outcome}x multiplier!`,
        });
      }
    },
    onError: (error: any) => {
      setIsSpinning(false);
      toast({
        title: "Game error",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const handleSpin = () => {
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

    setIsSpinning(true);
    setLastResult(null);
    
    // Animate wheel spin
    const spins = 5 + Math.random() * 5; // 5-10 full rotations
    const finalRotation = wheelRotation + (spins * 360);
    setWheelRotation(finalRotation);

    // Delay the API call to match animation
    setTimeout(() => {
      playGameMutation.mutate({
        gameType: 'luck-and-roll',
        betAmount,
        gameData: {}
      });
    }, 3000);
  };

  // Find the winning slice index after a spin
  const winningIndex = lastResult && lastResult.result && typeof lastResult.result.wheelResult === 'number'
    ? lastResult.result.wheelResult
    : null;

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
              🎰 Luck and Roll
              <span className="ml-auto bg-casino-orange text-black px-3 py-1 rounded-full text-sm font-bold">
                16 Slices
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {/* Wheel */}
            <div className="bg-casino-black rounded-xl p-6 mb-6">
              <div className="flex items-center justify-center">
                <div className="relative">
                  {/* Wheel */}
                  <div 
                    className={`w-64 h-64 border-4 border-casino-orange rounded-full relative overflow-hidden ${isSpinning ? 'spin-wheel' : ''}`}
                    style={{ transform: `rotate(${wheelRotation}deg)` }}
                  >
                    {/* Wheel segments */}
                    {wheelOutcomes.map((outcome, index) => (
                      <div
                        key={index}
                        className={`absolute w-full h-full ${getSliceColor(outcome)} opacity-80 ${winningIndex === index && !isSpinning ? 'ring-4 ring-casino-gold z-10' : ''}`}
                        style={{
                          clipPath: `polygon(50% 50%, ${50 + 50 * Math.cos((index * 22.5 - 90) * Math.PI / 180)}% ${50 + 50 * Math.sin((index * 22.5 - 90) * Math.PI / 180)}%, ${50 + 50 * Math.cos(((index + 1) * 22.5 - 90) * Math.PI / 180)}% ${50 + 50 * Math.sin(((index + 1) * 22.5 - 90) * Math.PI / 180)}%)`,
                        }}
                      >
                        <div 
                          className="absolute inset-0 flex items-center justify-center text-white font-bold text-xs"
                          style={{
                            transform: `rotate(${index * 22.5 + 11.25}deg)`,
                            transformOrigin: '50% 50%'
                          }}
                        >
                          {outcome === 'bankrupt' ? '💀' : 
                           outcome === 'jackpot' ? '💎' : 
                           `${outcome}x`}
                        </div>
                      </div>
                    ))}
                    {/* Center circle */}
                    <div className="absolute inset-1/4 bg-casino-dark rounded-full border-2 border-casino-gold flex items-center justify-center">
                      <div className="text-casino-gold font-bold text-lg">SPIN</div>
                    </div>
                  </div>
                  {/* Pointer */}
                  <div className="absolute -top-3 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-8 border-l-transparent border-r-transparent border-b-casino-gold"></div>
                  {/* Winning label at pointer */}
                  {winningIndex !== null && !isSpinning && (
                    <div className="absolute -top-10 left-1/2 transform -translate-x-1/2 bg-casino-gold text-casino-dark px-3 py-1 rounded font-bold shadow-lg text-lg z-20">
                      {wheelOutcomes[winningIndex] === 'bankrupt' ? 'BANKRUPT' :
                       wheelOutcomes[winningIndex] === 'jackpot' ? 'JACKPOT' :
                       `${wheelOutcomes[winningIndex]}x`}
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Result Display */}
            {lastResult && (
              <div className="bg-casino-dark rounded-lg p-4 mb-4">
                <div className="text-center">
                  <div className="text-white text-lg mb-2">
                    Result: <span className="text-casino-orange font-bold">
                      {lastResult.result.outcome === 'bankrupt' ? 'Bankrupt 💀' :
                       lastResult.result.outcome === 'jackpot' ? 'Jackpot 💎' :
                       `${lastResult.result.outcome}x Multiplier`}
                    </span>
                  </div>
                  <div className="text-casino-gold text-xl font-bold">
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
          </CardContent>
        </Card>

        {/* Betting Interface */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">Place Your Bet</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
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

            {/* Balance Display */}
            <div className="bg-casino-black rounded-lg p-4">
              <div className="flex items-center justify-between">
                <span className="text-gray-400">Your Balance:</span>
                <span className="text-casino-gold font-bold">
                  {user ? formatNumber(user.balance) : "0.00"} coins
                </span>
              </div>
            </div>

            {/* Spin Button */}
            <Button
              onClick={handleSpin}
              disabled={isSpinning || !betAmount}
              className="w-full casino-button text-xl py-4"
            >
              {isSpinning ? "SPINNING..." : "SPIN THE WHEEL!"}
            </Button>

            {/* Game Rules */}
            <div className="bg-casino-dark rounded-lg p-4 text-sm text-gray-400">
              <h4 className="text-white font-bold mb-2">Game Rules:</h4>
              <ul className="space-y-1">
                <li>• 6 Bankrupt slices = lose bet</li>
                <li>• 9 Multiplier slices = win bet × multiplier</li>
                <li>• 1 Jackpot slice = win 0.05x bet in $BBC</li>
                <li>• 0-10% chance for bonus $BBC on any spin</li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
