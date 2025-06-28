import { useState, useEffect } from "react";
import { useAuth } from "@/hooks/use-auth";
import { useWallet } from "@/hooks/use-wallet";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { formatNumber } from "@/lib/utils";
import { Mountain, Pickaxe, Hammer, Bot, Coins } from "lucide-react";

export default function Mining() {
  const { user } = useAuth();
  const { claimMining, isMiningClaimLoading } = useWallet();
  const [miningProgress, setMiningProgress] = useState(65);
  const [availableClaim, setAvailableClaim] = useState(0.003);
  const [dailyMined, setDailyMined] = useState(0.147);
  const [isActive, setIsActive] = useState(true);
  const [clicks, setClicks] = useState(0);

  useEffect(() => {
    const interval = setInterval(() => {
      if (isActive) {
        setMiningProgress(prev => {
          const newProgress = prev + 0.5;
          if (newProgress >= 100) {
            setAvailableClaim(prev => prev + 0.003);
            return 0;
          }
          return newProgress;
        });
      }
    }, 30000); // Progress every 30 seconds

    return () => clearInterval(interval);
  }, [isActive]);

  const handleMinerClick = () => {
    setClicks(prev => prev + 1);
    setAvailableClaim(prev => prev + 0.001);
    
    // Add visual feedback animation
    const miner = document.getElementById('miner-icon');
    if (miner) {
      miner.classList.add('pulse-glow');
      setTimeout(() => miner.classList.remove('pulse-glow'), 500);
    }
  };

  const handleClaimReward = () => {
    claimMining();
    setAvailableClaim(0);
    setDailyMined(prev => prev + availableClaim);
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h2 className="text-3xl font-bold text-white mb-8">$BBC Token Mining</h2>
      
      {/* Main Mining Interface */}
      <Card className="casino-card mb-8">
        <CardContent className="p-8">
          <div className="text-center mb-8">
            <div className="relative inline-block">
              <Mountain className="text-casino-gold text-8xl w-24 h-24" />
              <div 
                id="miner-icon"
                className="absolute -bottom-4 -right-4 bg-casino-orange text-white rounded-full w-12 h-12 flex items-center justify-center cursor-pointer transition-all hover:scale-110"
                onClick={handleMinerClick}
              >
                <Pickaxe className="text-xl w-6 h-6" />
              </div>
            </div>
            <h3 className="text-2xl font-bold text-white mt-4 mb-2">Underground $BBC Mine</h3>
            <p className="text-gray-400">Click the miner to earn passive $BBC tokens!</p>
          </div>
          
          {/* Mining Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-casino-black rounded-lg p-4 text-center">
              <div className="text-casino-gold text-2xl font-bold">
                {formatNumber(dailyMined, 6)}
              </div>
              <div className="text-gray-400 text-sm">$BBC Mined Today</div>
            </div>
            <div className="bg-casino-black rounded-lg p-4 text-center">
              <div className="text-casino-orange text-2xl font-bold">0.025</div>
              <div className="text-gray-400 text-sm">$BBC per Hour</div>
            </div>
            <div className="bg-casino-black rounded-lg p-4 text-center">
              <div className={`text-2xl font-bold ${isActive ? 'text-green-400' : 'text-red-400'}`}>
                {isActive ? 'Active' : 'Inactive'}
              </div>
              <div className="text-gray-400 text-sm">Mining Status</div>
            </div>
          </div>
          
          {/* Mining Progress */}
          <div className="bg-casino-black rounded-xl p-6">
            <div className="flex items-center justify-between mb-4">
              <div>
                <div className="text-white font-bold">Mining Progress</div>
                <div className="text-gray-400 text-sm">
                  Next reward in {Math.ceil((100 - miningProgress) * 0.5)} minutes
                </div>
              </div>
              <Button 
                onClick={handleMinerClick}
                className="bg-casino-gold hover:bg-yellow-500 text-casino-dark font-bold"
              >
                Click to Mine! ({clicks})
              </Button>
            </div>
            
            <Progress 
              value={miningProgress} 
              className="mb-4 h-4"
            />
            
            <div className="text-center">
              <div className="text-casino-gold font-bold text-xl">
                +{formatNumber(availableClaim, 6)} $BBC
              </div>
              <div className="text-gray-400 text-sm mb-4">Available to claim</div>
              <Button
                onClick={handleClaimReward}
                disabled={availableClaim <= 0 || isMiningClaimLoading}
                className="casino-button"
              >
                {isMiningClaimLoading ? "Claiming..." : "Claim Reward"}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
      
      {/* Mining Upgrades */}
      <Card className="casino-card">
        <CardHeader>
          <CardTitle className="text-xl text-white">Mining Upgrades</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-casino-black rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <div className="text-white font-bold">Better Pickaxe</div>
                  <div className="text-gray-400 text-sm">+50% mining speed</div>
                </div>
                <Hammer className="text-casino-orange text-2xl w-8 h-8" />
              </div>
              <div className="flex items-center justify-between">
                <div className="text-casino-gold font-bold">2.5 $BBC</div>
                <Button 
                  className="casino-button"
                  disabled={!user || parseFloat(user.bbcTokens) < 2.5}
                >
                  Upgrade
                </Button>
              </div>
            </div>
            
            <div className="bg-casino-black rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <div>
                  <div className="text-white font-bold">Auto Miner</div>
                  <div className="text-gray-400 text-sm">Mines automatically</div>
                </div>
                <Bot className="text-casino-orange text-2xl w-8 h-8" />
              </div>
              <div className="flex items-center justify-between">
                <div className="text-casino-gold font-bold">5.0 $BBC</div>
                <Button 
                  className="casino-button"
                  disabled={!user || parseFloat(user.bbcTokens) < 5.0}
                >
                  Upgrade
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
