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
import { ArrowLeft, Zap, ShieldAlert } from "lucide-react";

export default function IpisSipi() {
  const { user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [betAmount, setBetAmount] = useState("");
  const [currentStep, setCurrentStep] = useState(0);
  const [currentWinnings, setCurrentWinnings] = useState(0);
  const [gameActive, setGameActive] = useState(false);
  const [isMoving, setIsMoving] = useState(false);
  const [gameResult, setGameResult] = useState<any>(null);

  // Dynamically generate 30 steps to match game logic
  const steps = Array.from({ length: 30 }, (_, i) => {
    // Match multipliers and hazard from game logic
    const multiplier = 1.2 * Math.pow(50 / 1.2, i / 29);
    const hazardChance = 0.05 + (0.60 - 0.05) * (i / 29);
    return {
      multiplier: parseFloat(multiplier.toFixed(2)),
      hazard: '☠️', // Use a generic hazard icon for all steps
      description: `Step ${i + 1}: ${Math.round(hazardChance * 100)}% hazard, ${multiplier.toFixed(2)}x multiplier`
    };
  });

  const playGameMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await apiRequest('POST', '/api/games/play', data);
      return response.json();
    },
    onSuccess: (data) => {
      setIsMoving(false);
      setGameResult(data);
      
      const stepsCompleted = data.result.steps;
      const survived = stepsCompleted === 9;
      
      if (survived) {
        toast({
          title: "Victory! 🪳👑",
          description: `You survived all 9 steps! Won ${formatNumber(data.winAmount)} coins!`,
        });
        
        if (data.bbcWon > 0) {
          toast({
            title: "Bonus $BBC! 💎",
            description: `You earned ${formatNumber(data.bbcWon, 6)} $BBC tokens for completing the challenge!`,
          });
        }
      } else {
        toast({
          title: `Caught at step ${stepsCompleted}!`,
          description: `You won ${formatNumber(data.winAmount)} coins with ${data.result.multiplier}x multiplier`,
        });
      }
      
      setGameActive(false);
      setCurrentStep(0);
      setCurrentWinnings(0);
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
    onError: (error: any) => {
      setIsMoving(false);
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
    setCurrentStep(0);
    setCurrentWinnings(parseFloat(betAmount));
    setGameResult(null);
  };

  const moveForward = () => {
    if (isMoving || currentStep >= 30) return;
    
    setIsMoving(true);
    
    // Animate roach movement
    setTimeout(() => {
      playGameMutation.mutate({
        gameType: 'ipis-sipi',
        betAmount,
        gameData: { currentStep }
      });
    }, 1000);
  };

  const cashOut = () => {
    if (currentWinnings > 0) {
      toast({
        title: "Cashed out safely! 🪳💰",
        description: `You won ${formatNumber(currentWinnings)} coins!`,
      });
      
      // In a real app, this would update the balance via API
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    }
    
    setGameActive(false);
    setCurrentStep(0);
    setCurrentWinnings(0);
  };

  const getStepWinnings = (step: number) => {
    if (step === 0) return parseFloat(betAmount);
    return parseFloat(betAmount) * steps[step - 1].multiplier;
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
              🪳 Ipis Sipi
              <span className="ml-auto bg-green-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                30 Steps
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {/* Kitchen Path */}
            <div className="bg-casino-black rounded-xl p-6 mb-6">
              <div className="relative">
                {/* Path visualization: show 10 steps per row for clarity */}
                <div className="grid grid-cols-5 gap-2 mb-6">
                  {steps.map((step, index) => (
                    <div
                      key={index}
                      className={`
                        relative h-16 rounded-lg border-2 flex flex-col items-center justify-center text-center
                        ${index < currentStep 
                          ? 'border-green-500 bg-green-900/20' 
                          : index === currentStep && gameActive
                          ? 'border-casino-orange bg-casino-orange/20 animate-pulse' 
                          : 'border-casino-orange/30 bg-casino-dark'
                        }
                      `}
                    >
                      <div className="text-lg mb-1">{step.hazard}</div>
                      <div className="text-xs text-gray-300">{step.multiplier}x</div>
                      
                      {/* Roach position */}
                      {index === currentStep && gameActive && (
                        <div className="absolute -bottom-2 left-1/2 transform -translate-x-1/2 text-2xl animate-bounce">
                          🪳
                        </div>
                      )}
                    </div>
                  ))}
                </div>

                {/* Current step info */}
                {gameActive && currentStep < 30 && (
                  <div className="text-center bg-casino-dark rounded-lg p-4">
                    <div className="text-casino-orange font-bold text-lg mb-2">
                      Step {currentStep + 1}: {steps[currentStep]?.description}
                    </div>
                    <div className="text-white">
                      Potential winnings: <span className="text-casino-gold font-bold">
                        {formatNumber(getStepWinnings(currentStep + 1))} coins
                      </span>
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Game Controls */}
            {gameActive && (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <Button
                    onClick={moveForward}
                    disabled={isMoving || currentStep >= 30}
                    className="casino-button py-6 text-lg"
                  >
                    {isMoving ? "Moving..." : "🪳 Move Forward"}
                  </Button>
                  <Button
                    onClick={cashOut}
                    disabled={isMoving || currentStep === 0}
                    className="bg-casino-gold hover:bg-yellow-500 text-casino-dark py-6 text-lg font-bold"
                  >
                    💰 Cash Out
                  </Button>
                </div>
                
                <div className="text-center text-gray-400 text-sm">
                  Current winnings: {formatNumber(getStepWinnings(currentStep))} coins
                </div>
              </div>
            )}

            {/* Result Display */}
            {gameResult && (
              <div className="bg-casino-dark rounded-lg p-4 mt-4">
                <div className="text-center">
                  <div className="text-white text-lg mb-2">
                    Game Over! Reached step: <span className="text-casino-orange font-bold">
                      {gameResult.result.steps}
                    </span>
                  </div>
                  <div className="text-casino-gold text-xl font-bold">
                    Won: {formatNumber(gameResult.winAmount)} coins
                  </div>
                  {gameResult.bbcWon > 0 && (
                    <div className="text-casino-orange text-lg font-bold">
                      Bonus: {formatNumber(gameResult.bbcWon, 6)} $BBC
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
            <CardTitle className="text-xl text-white">
              {gameActive ? "Survival Challenge" : "Start Adventure"}
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
                  START ADVENTURE! 🪳
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

            {/* Multiplier Table */}
            <div className="bg-casino-dark rounded-lg p-4">
              <h4 className="text-white font-bold mb-3">Step Multipliers:</h4>
              <div className="grid grid-cols-5 gap-2 text-xs">
                {steps.map((step, index) => (
                  <div
                    key={index}
                    className={`
                      text-center py-2 rounded
                      ${index < currentStep 
                        ? 'bg-green-600 text-white' 
                        : index === currentStep && gameActive
                        ? 'bg-casino-orange text-white' 
                        : 'bg-gray-600 text-gray-300'
                      }
                    `}
                  >
                    {step.multiplier}x
                  </div>
                ))}
              </div>
            </div>

            {/* Game Rules */}
            <div className="bg-casino-dark rounded-lg p-4 text-sm text-gray-400">
              <h4 className="text-white font-bold mb-2">Game Rules:</h4>
              <ul className="space-y-1">
                <li>• Navigate through 30 increasingly dangerous steps</li>
                <li>• Each step increases your multiplier (up to 50x)</li>
                <li>• Cash out anytime to secure winnings</li>
                <li>• Survive all 30 steps to win 0.2x bet in $BBC</li>
                <li>• Getting caught ends the game</li>
                <li>• Hazard chance increases each step</li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
