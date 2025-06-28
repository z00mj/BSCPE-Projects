import { useState, useEffect } from "react";
import { useAuth } from "@/hooks/use-auth";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiRequest } from "@/lib/queryClient";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import { formatNumber, getBetAmounts } from "@/lib/utils";
import { Link } from "wouter";
import { ArrowLeft, Zap, Target } from "lucide-react";

export default function BlowItBolims() {
  const { user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [betAmount, setBetAmount] = useState("");
  const [autoCashout, setAutoCashout] = useState(false);
  const [autoCashoutValue, setAutoCashoutValue] = useState("2.0");
  const [currentMultiplier, setCurrentMultiplier] = useState(1.0);
  const [gameActive, setGameActive] = useState(false);
  const [balloonSize, setBalloonSize] = useState(1);
  const [gameResult, setGameResult] = useState<any>(null);
  const [hasPopped, setHasPopped] = useState(false);

  const playGameMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiRequest('POST', '/api/games/play', data);
      return response.json();
    },
    onSuccess: (data) => {
      setGameResult(data);
      setGameActive(false);
      
      if (data.result.success) {
        toast({
          title: "Cashed out! 🎈💰",
          description: `You won ${formatNumber(data.winAmount)} coins at ${data.result.cashoutPoint}x!`,
        });
      } else {
        toast({
          title: "Balloon popped! 💥",
          description: `The balloon burst at ${data.result.crashPoint.toFixed(2)}x. Better luck next time!`,
          variant: "destructive",
        });
        setHasPopped(true);
      }
      
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
    onError: (error: any) => {
      setGameActive(false);
      toast({
        title: "Game error",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  // Balloon inflation animation
  useEffect(() => {
    let interval: NodeJS.Timeout;
    
    if (gameActive) {
      interval = setInterval(() => {
        setCurrentMultiplier(prev => {
          const newMultiplier = prev + 0.01;
          setBalloonSize(1 + (newMultiplier - 1) * 0.5); // Balloon grows with multiplier
          
          // Auto cashout check
          if (autoCashout && newMultiplier >= parseFloat(autoCashoutValue)) {
            cashOut(newMultiplier);
            return newMultiplier;
          }
          
          return newMultiplier;
        });
      }, 100);
    }
    
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [gameActive, autoCashout, autoCashoutValue]);

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
    setCurrentMultiplier(1.0);
    setBalloonSize(1);
    setGameResult(null);
    setHasPopped(false);
  };

  const cashOut = (multiplier?: number) => {
    if (!gameActive) return;
    
    const finalMultiplier = multiplier || currentMultiplier;
    
    playGameMutation.mutate({
      gameType: 'blow-it-bolims',
      betAmount,
      gameData: { 
        cashoutPoint: finalMultiplier,
        autoCashout: !!multiplier 
      }
    });
  };

  const getBalloonColor = () => {
    if (hasPopped) return "from-red-600 to-red-800";
    if (currentMultiplier > 5) return "from-red-400 to-red-600";
    if (currentMultiplier > 3) return "from-orange-400 to-red-400";
    if (currentMultiplier > 2) return "from-yellow-400 to-orange-400";
    return "from-casino-orange to-casino-red";
  };

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

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Game Area */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-2xl text-white flex items-center">
              🎈 Blow it Bolims!
              <span className="ml-auto bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                Crash Game
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {/* Balloon Area */}
            <div className="bg-casino-black rounded-xl p-8 mb-6 text-center">
              <div className="relative flex items-center justify-center h-64">
                {/* Balloon */}
                <div 
                  className={`
                    relative rounded-full bg-gradient-to-t ${getBalloonColor()}
                    transition-all duration-100 flex items-center justify-center
                    ${gameActive && !hasPopped ? 'animate-pulse' : ''}
                    ${hasPopped ? 'animate-ping' : ''}
                  `}
                  style={{
                    width: `${balloonSize * 120}px`,
                    height: `${balloonSize * 150}px`,
                    maxWidth: '240px',
                    maxHeight: '300px'
                  }}
                >
                  {/* Balloon string */}
                  <div className="absolute -bottom-8 left-1/2 transform -translate-x-1/2 w-1 h-12 bg-casino-gold"></div>
                  
                  {/* Multiplier display */}
                  <div className="text-white font-bold text-2xl">
                    {hasPopped ? "💥" : `${currentMultiplier.toFixed(2)}x`}
                  </div>
                </div>
              </div>
              
              {/* Current winnings */}
              {gameActive && (
                <div className="mt-4">
                  <div className="text-casino-gold text-xl font-bold">
                    Potential Win: {formatNumber(parseFloat(betAmount) * currentMultiplier)} coins
                  </div>
                  <div className="text-gray-400 text-sm">
                    Multiplier: {currentMultiplier.toFixed(2)}x
                  </div>
                </div>
              )}
            </div>

            {/* Game Controls */}
            <div className="space-y-4">
              {!gameActive ? (
                <Button
                  onClick={startGame}
                  disabled={!betAmount}
                  className="w-full casino-button text-xl py-6"
                >
                  START INFLATING! 🎈
                </Button>
              ) : (
                <Button
                  onClick={() => cashOut()}
                  className="w-full bg-casino-gold hover:bg-yellow-500 text-casino-dark text-xl py-6 font-bold"
                >
                  💰 CASH OUT: {formatNumber(parseFloat(betAmount) * currentMultiplier)} coins
                </Button>
              )}
            </div>

            {/* Result Display */}
            {gameResult && (
              <div className="bg-casino-dark rounded-lg p-4 mt-4">
                <div className="text-center">
                  <div className="text-white text-lg mb-2">
                    {gameResult.result.success ? "Successfully cashed out!" : "Balloon popped!"}
                  </div>
                  <div className="text-casino-gold text-xl font-bold">
                    {gameResult.result.success ? "Won" : "Lost"}: {formatNumber(gameResult.winAmount)} coins
                  </div>
                  <div className="text-gray-400 text-sm">
                    Crash point: {gameResult.result.crashPoint.toFixed(2)}x
                  </div>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Betting Interface */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">
              {gameActive ? "Game Active" : "Place Your Bet"}
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
              </>
            )}

            {/* Auto Cashout Settings */}
            <div className="bg-casino-dark rounded-lg p-4 space-y-3">
              <div className="flex items-center justify-between">
                <Label htmlFor="auto-cashout" className="text-white font-medium">
                  Auto Cashout
                </Label>
                <Switch
                  id="auto-cashout"
                  checked={autoCashout}
                  onCheckedChange={setAutoCashout}
                  disabled={gameActive}
                />
              </div>
              
              {autoCashout && (
                <div>
                  <Label className="text-gray-400 mb-2 block">Cash out at:</Label>
                  <div className="flex items-center space-x-2">
                    <Input
                      type="number"
                      value={autoCashoutValue}
                      onChange={(e) => setAutoCashoutValue(e.target.value)}
                      className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                      step="0.1"
                      min="1.1"
                      max="100"
                      disabled={gameActive}
                    />
                    <span className="text-white">x</span>
                  </div>
                </div>
              )}
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

            {/* Recent Multipliers */}
            <div className="bg-casino-dark rounded-lg p-4">
              <h4 className="text-white font-bold mb-3">Recent Crashes:</h4>
              <div className="grid grid-cols-5 gap-2">
                {[2.34, 1.89, 5.67, 1.23, 8.91].map((mult, index) => (
                  <div
                    key={index}
                    className={`
                      text-center py-2 rounded text-sm font-bold
                      ${mult > 2 ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}
                    `}
                  >
                    {mult}x
                  </div>
                ))}
              </div>
            </div>

            {/* Game Rules */}
            <div className="bg-casino-dark rounded-lg p-4 text-sm text-gray-400">
              <h4 className="text-white font-bold mb-2">Game Rules:</h4>
              <ul className="space-y-1">
                <li>• Balloon inflates and multiplier increases</li>
                <li>• Cash out before the balloon pops</li>
                <li>• Use auto-cashout for guaranteed wins</li>
                <li>• Higher multipliers = higher risk</li>
                <li>• Bonus balloons may award $BBC tokens</li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
