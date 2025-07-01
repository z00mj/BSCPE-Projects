
import { useState, useEffect } from "react";
import Layout from "@/components/Layout";
import { useBannedCheck } from "@/hooks/useBannedCheck";
import BannedOverlay from "@/components/BannedOverlay";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { TrendingUp, Play, Pause, Coins, Clock, Trophy, Gift, Sparkles, Zap } from "lucide-react";
import { useFarmingSessions } from "@/hooks/useFarmingSessions";
import { useActivityTracker } from "@/hooks/useActivityTracker";
import QuestSystem from "@/components/QuestSystem";
import EggShop from "@/components/EggShop";
import Incubator from "@/components/Incubator";
import PetGarden from "@/components/PetGarden";
import { usePetSystem } from "@/hooks/usePetSystem";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

const Earn = () => {
  const [stakingAmount, setStakingAmount] = useState("");
  const { isBanned, reason } = useBannedCheck();
  const [error, setError] = useState<string | null>(null);

  // Error boundary effect
  useEffect(() => {
    const handleError = (event: ErrorEvent) => {
      console.error('Page error:', event.error);
      setError('An error occurred. Please refresh the page.');
    };

    window.addEventListener('error', handleError);
    return () => window.removeEventListener('error', handleError);
  }, []);

  const {
    farmingSession,
    stakingSession,
    farmingProgress,
    stakingProgress,
    earningHistory,
    loading,
    startFarming,
    stopFarming,
    harvestFarming,
    startStaking,
    stopStaking,
    claimStaking
  } = useFarmingSessions();

  const { trackFarmingClaim, trackStaking } = useActivityTracker();
  const { activePetBoosts } = usePetSystem();

  const handleStartStaking = () => {
    const amount = parseFloat(stakingAmount);
    if (!amount || amount <= 0) {
      return;
    }
    startStaking(amount);
    trackStaking(amount);
    setStakingAmount("");
  };

  const handleHarvestFarming = async () => {
    await harvestFarming();
    trackFarmingClaim(0.002);
  };

  const handleClaimStaking = async () => {
    if (stakingSession?.stake_amount) {
      const rewardAmount = stakingSession.stake_amount * 0.0005;
      await claimStaking();
      trackFarmingClaim(rewardAmount);
    }
  };

  const canHarvest = farmingSession && farmingProgress >= 100;
  const canClaim = stakingSession && stakingProgress >= 100;

  if (error) {
    return (
      <Layout>
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center space-y-4">
            <p className="text-red-400">{error}</p>
            <button 
              onClick={() => window.location.reload()} 
              className="px-4 py-2 bg-primary text-white rounded"
            >
              Refresh Page
            </button>
          </div>
        </div>
      </Layout>
    );
  }

  if (loading) {
    return (
      <Layout>
        {isBanned && <BannedOverlay reason={reason} />}
        <div className="min-h-screen flex items-center justify-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-primary"></div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      {isBanned && <BannedOverlay reason={reason} />}
      <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5">
        {/* Animated Background Elements */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-yellow-500/5 rounded-full blur-3xl animate-float"></div>
          <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-orange-500/5 rounded-full blur-3xl animate-bounce-gentle"></div>
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-yellow-500/3 to-orange-500/3 rounded-full blur-3xl"></div>
        </div>

        <div className="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
          {/* Hero Section */}
          <div className="text-center mb-8 sm:mb-12">
            <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
              <div className="w-6 h-6 itlog-token rounded-full flex items-center justify-center">
                <span className="text-black font-bold text-sm">₿</span>
              </div>
              <span className="text-sm font-medium text-gradient-gold">$ITLOG Token Rewards</span>
            </div>
            
            <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black mb-4">
              <span className="bg-gradient-to-r from-yellow-400 via-orange-400 to-gold-400 bg-clip-text text-transparent">
                Earn $ITLOG
              </span>
            </h1>
            
            <p className="text-lg sm:text-xl text-muted-foreground max-w-3xl mx-auto">
              Farm and stake to earn exclusive $ITLOG tokens. Every action rewards you with our revolutionary cryptocurrency.
            </p>
          </div>

          {/* Quest System */}
          <div className="mb-8 sm:mb-12">
            <QuestSystem />
          </div>

          {/* Main Content Tabs */}
          <Tabs defaultValue="farming" className="w-full">
            <TabsList className="grid w-full grid-cols-2 lg:grid-cols-4 h-auto gap-2 p-2 bg-card/50 backdrop-blur-sm border border-primary/20 rounded-2xl">
              <TabsTrigger 
                value="farming" 
                className="flex items-center justify-center px-4 py-4 rounded-xl text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-purple-500 data-[state=active]:to-pink-500 data-[state=active]:text-white transition-all duration-300"
              >
                <TrendingUp className="w-4 h-4 mr-2" />
                <span className="hidden sm:inline">Farming & Staking</span>
                <span className="sm:hidden">Farming</span>
              </TabsTrigger>
              <TabsTrigger 
                value="shop" 
                className="flex items-center justify-center px-4 py-4 rounded-xl text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-blue-500 data-[state=active]:to-cyan-500 data-[state=active]:text-white transition-all duration-300"
              >
                <Gift className="w-4 h-4 mr-2" />
                Egg Shop
              </TabsTrigger>
              <TabsTrigger 
                value="incubator" 
                className="flex items-center justify-center px-4 py-4 rounded-xl text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-green-500 data-[state=active]:to-emerald-500 data-[state=active]:text-white transition-all duration-300"
              >
                <Zap className="w-4 h-4 mr-2" />
                Incubator
              </TabsTrigger>
              <TabsTrigger 
                value="garden" 
                className="flex items-center justify-center px-4 py-4 rounded-xl text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-orange-500 data-[state=active]:to-red-500 data-[state=active]:text-white transition-all duration-300"
              >
                <Trophy className="w-4 h-4 mr-2" />
                <span className="hidden sm:inline">Pet Garden</span>
                <span className="sm:hidden">Garden</span>
              </TabsTrigger>
            </TabsList>
            
            <TabsContent value="farming" className="space-y-8 mt-8">
              {/* Farming and Staking Cards */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                {/* Token Farming */}
                <Card className="modern-card bg-gradient-to-br from-purple-500/10 to-pink-500/10 border-purple-500/30 glow-purple hover-lift">
                  <CardHeader className="pb-6">
                    <CardTitle className="flex items-center text-xl">
                      <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mr-4">
                        <TrendingUp className="w-6 h-6 text-white" />
                      </div>
                      Token Farming
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-6">
                    <div className="text-center">
                      <div className="w-20 h-20 itlog-token rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-2xl">
                        <span className="text-black font-bold text-2xl">₿</span>
                      </div>
                      <p className="text-sm text-muted-foreground mb-2">
                        Earn <span className="font-bold text-purple-400">0.0021 $ITLOG</span> every 5 minutes
                        {activePetBoosts.some(boost => boost.trait_type === 'farming_boost' || boost.trait_type === 'token_multiplier') && (
                          <span className="text-green-400 block mt-1">
                            (+ pet boosts active!)
                          </span>
                        )}
                      </p>
                      <p className="text-lg font-bold">
                        Status: <span className={farmingSession ? 'text-green-400' : 'text-muted-foreground'}>
                          {farmingSession ? 'Active' : 'Inactive'}
                        </span>
                      </p>
                    </div>

                    {farmingSession && (
                      <div className="space-y-3">
                        <div className="flex justify-between text-sm font-medium">
                          <span>Progress</span>
                          <span>{farmingProgress.toFixed(1)}%</span>
                        </div>
                        <Progress value={farmingProgress} className="h-3 bg-background border border-purple-500/30">
                          <div className="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-300 rounded-full" style={{ width: `${farmingProgress}%` }} />
                        </Progress>
                        <p className="text-xs text-muted-foreground text-center">
                          {farmingProgress >= 100 ? 'Ready to harvest!' : `Next reward in ${Math.ceil((100 - farmingProgress) * 3)} seconds`}
                        </p>
                      </div>
                    )}

                    {canHarvest ? (
                      <Button 
                        onClick={handleHarvestFarming}
                        className="w-full h-14 modern-button bg-gradient-to-r from-gold-500 to-amber-500 hover:from-gold-600 hover:to-amber-600 text-black font-bold text-lg glow-gold group"
                      >
                        <Gift className="w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-300" />
                        Harvest Now
                        <Sparkles className="w-4 h-4 ml-2 group-hover:rotate-12 transition-transform duration-300" />
                      </Button>
                    ) : (
                      <Button 
                        onClick={farmingSession ? stopFarming : startFarming}
                        className={`w-full h-14 text-lg font-bold ${
                          farmingSession 
                            ? "border-red-500 text-red-400 hover:bg-red-500/10 bg-transparent" 
                            : "modern-button button-primary glow-purple"
                        }`}
                        variant={farmingSession ? "outline" : "default"}
                      >
                        {farmingSession ? (
                          <>
                            <Pause className="w-5 h-5 mr-2" />
                            Stop Farming
                          </>
                        ) : (
                          <>
                            <Play className="w-5 h-5 mr-2" />
                            Start Farming
                          </>
                        )}
                      </Button>
                    )}
                  </CardContent>
                </Card>

                {/* Token Staking */}
                <Card className="modern-card bg-gradient-to-br from-green-500/10 to-emerald-500/10 border-green-500/30 glow-green hover-lift">
                  <CardHeader className="pb-6">
                    <CardTitle className="flex items-center text-xl">
                      <div className="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center mr-4">
                        <Coins className="w-6 h-6 text-white" />
                      </div>
                      Token Staking
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-6">
                    <div className="text-center">
                      <div className="w-20 h-20 bg-gradient-to-r from-green-500 to-emerald-500 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-2xl">
                        <Coins className="w-10 h-10 text-white" />
                      </div>
                      <p className="text-sm text-muted-foreground mb-2">
                        Stake PHP to earn <span className="font-bold text-green-400">$ITLOG tokens</span> (0.0005% of stake)
                      </p>
                      <p className="text-lg font-bold">
                        Status: <span className={stakingSession ? 'text-green-400' : 'text-muted-foreground'}>
                          {stakingSession ? `Staking ₱${stakingSession.stake_amount?.toFixed(2)}` : 'Inactive'}
                        </span>
                      </p>
                    </div>

                    {stakingSession && (
                      <div className="space-y-3">
                        <div className="flex justify-between text-sm font-medium">
                          <span>Progress</span>
                          <span>{stakingProgress.toFixed(1)}%</span>
                        </div>
                        <Progress value={stakingProgress} className="h-3 bg-background border border-green-500/30">
                          <div className="h-full bg-gradient-to-r from-green-500 to-emerald-500 transition-all duration-300 rounded-full" style={{ width: `${stakingProgress}%` }} />
                        </Progress>
                        <p className="text-xs text-muted-foreground text-center">
                          {stakingProgress >= 100 ? 'Ready to claim!' : `Next reward in ${Math.ceil((100 - stakingProgress) * 3)} seconds`}
                        </p>
                      </div>
                    )}

                    {canClaim ? (
                      <Button 
                        onClick={handleClaimStaking}
                        className="w-full h-14 modern-button bg-gradient-to-r from-gold-500 to-amber-500 hover:from-gold-600 hover:to-amber-600 text-black font-bold text-lg glow-gold group"
                      >
                        <Trophy className="w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-300" />
                        Claim Now
                        <Sparkles className="w-4 h-4 ml-2 group-hover:rotate-12 transition-transform duration-300" />
                      </Button>
                    ) : !stakingSession ? (
                      <div className="space-y-4">
                        <div>
                          <Label htmlFor="stake-amount" className="text-sm font-medium">Stake Amount (PHP)</Label>
                          <Input
                            id="stake-amount"
                            type="number"
                            step="0.01"
                            placeholder="Enter amount to stake"
                            value={stakingAmount}
                            onChange={(e) => setStakingAmount(e.target.value)}
                            className="h-12 text-base mt-2"
                          />
                        </div>
                        <Button 
                          onClick={handleStartStaking}
                          className="w-full h-14 modern-button button-secondary text-lg font-bold"
                        >
                          <Play className="w-5 h-5 mr-2" />
                          Start Staking
                        </Button>
                      </div>
                    ) : (
                      <Button 
                        onClick={stopStaking}
                        variant="outline"
                        className="w-full h-14 border-red-500 text-red-400 hover:bg-red-500/10 text-lg font-bold"
                      >
                        <Pause className="w-5 h-5 mr-2" />
                        Stop Staking
                      </Button>
                    )}
                  </CardContent>
                </Card>
              </div>

              {/* Token Earning History */}
              <Card className="modern-card hover-lift">
                <CardHeader className="pb-6">
                  <CardTitle className="flex items-center text-xl">
                    <div className="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center mr-4">
                      <Clock className="w-6 h-6 text-white" />
                    </div>
                    Token Earning History
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {earningHistory.length === 0 ? (
                    <div className="text-center py-12">
                      <div className="w-16 h-16 bg-gradient-to-r from-purple-500/20 to-pink-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <TrendingUp className="w-8 h-8 text-purple-400" />
                      </div>
                      <p className="text-lg text-muted-foreground">
                        No earnings yet. Start farming or staking to begin earning $ITLOG tokens!
                      </p>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {earningHistory.map((earning) => (
                        <div key={earning.id} className="flex items-center justify-between p-6 glass rounded-2xl border border-primary/20 hover:border-primary/40 transition-all duration-300">
                          <div className="flex items-center space-x-4 flex-1 min-w-0">
                            <div className={`w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 ${
                              earning.session_type === 'farming' 
                                ? 'bg-gradient-to-r from-purple-500 to-pink-500' 
                                : 'bg-gradient-to-r from-green-500 to-emerald-500'
                            }`}>
                              {earning.session_type === 'farming' ? 
                                <TrendingUp className="w-6 h-6 text-white" /> : 
                                <Coins className="w-6 h-6 text-white" />
                              }
                            </div>
                            <div className="min-w-0 flex-1">
                              <p className="font-bold capitalize text-lg">{earning.session_type}</p>
                              <p className="text-sm text-muted-foreground">
                                {new Date(earning.created_at).toLocaleDateString()} {new Date(earning.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                              </p>
                              {earning.stake_amount && (
                                <p className="text-xs text-muted-foreground">
                                  Staked: ₱{earning.stake_amount.toFixed(2)}
                                </p>
                              )}
                            </div>
                          </div>
                          <div className="text-right flex-shrink-0">
                            <p className="font-black text-gold-400 text-lg">
                              +{earning.tokens_earned.toFixed(4)}
                            </p>
                            <p className="text-sm text-gold-400 font-medium">$ITLOG</p>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* How It Works */}
              <Card className="modern-card">
                <CardHeader className="pb-6">
                  <CardTitle className="text-xl">How It Works</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="space-y-3">
                      <h4 className="font-bold text-lg flex items-center">
                        <TrendingUp className="w-5 h-5 mr-2 text-purple-400" />
                        Token Farming
                      </h4>
                      <p className="text-sm text-muted-foreground leading-relaxed">
                        Activate farming to automatically earn 0.02 $ITLOG tokens every 5 minutes. 
                        No staking required, just activate and let it run. Your progress is saved even if you navigate away.
                      </p>
                    </div>
                    <div className="space-y-3">
                      <h4 className="font-bold text-lg flex items-center">
                        <Coins className="w-5 h-5 mr-2 text-green-400" />
                        Token Staking
                      </h4>
                      <p className="text-sm text-muted-foreground leading-relaxed">
                        Stake your PHP balance to earn $ITLOG tokens. You'll earn 50% of your staked amount as $ITLOG tokens every 5 minutes. 
                        Your staked amount will be returned when you claim your rewards.
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="shop">
              <EggShop />
            </TabsContent>

            <TabsContent value="incubator">
              <Incubator />
            </TabsContent>

            <TabsContent value="garden">
              <PetGarden />
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </Layout>
  );
};

export default Earn;
