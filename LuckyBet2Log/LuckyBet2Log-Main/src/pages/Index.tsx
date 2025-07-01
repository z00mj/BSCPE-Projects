
import { Link } from "react-router-dom";
import Layout from "@/components/Layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { 
  Gamepad2, 
  TrendingUp, 
  Coins, 
  Crown,
  Zap,
  Star,
  Wallet,
  Sparkles,
  Trophy,
  Target,
  Users
} from "lucide-react";

const Index = () => {
  // Fetch real-time statistics from database
  const { data: statsData } = useQuery({
    queryKey: ['homepage-stats'],
    queryFn: async () => {
      // Get total users count
      const { count: totalUsers } = await supabase
        .from('profiles')
        .select('*', { count: 'exact', head: true });

      // Get active users (non-banned users)
      const { count: activeUsers } = await supabase
        .from('profiles')
        .select('*', { count: 'exact', head: true })
        .eq('is_banned', false);

      // Get total games played from game_history
      const { count: gamesPlayed } = await supabase
        .from('game_history')
        .select('*', { count: 'exact', head: true });

      // Calculate total winnings as sum of all users' portfolio values
      const { data: profilesData } = await supabase
        .from('profiles')
        .select('php_balance, coins, itlog_tokens');

      const totalWinnings = profilesData?.reduce((sum, profile) => {
        // Convert everything to PHP equivalent for total portfolio value
        const phpValue = profile.php_balance || 0;
        const coinsValue = (profile.coins || 0) * 0.01; // Assuming 1 coin = 0.01 PHP
        const itlogValue = (profile.itlog_tokens || 0) * 0.02; // Assuming 1 ITLOG = 0.02 PHP
        return sum + phpValue + coinsValue + itlogValue;
      }, 0) || 0;

      // Get total ITLOG tokens distributed from multiple sources
      let totalItlogDistributed = 0;

      // From earning_history table
      const { data: earningData } = await supabase
        .from('earning_history')
        .select('tokens_earned');
      
      const earningItlog = earningData?.reduce((sum, earning) => sum + (earning.tokens_earned || 0), 0) || 0;

      // From game_history where ITLOG tokens were won
      const { data: gameItlogData } = await supabase
        .from('game_history')
        .select('win_amount, game_details')
        .eq('result_type', 'win')
        .not('game_details', 'is', null);

      const gameItlog = gameItlogData?.reduce((sum, game) => {
        // Check if this was an ITLOG win based on game_details
        const details = game.game_details as any;
        if (details && (details.isItlogWin || details.itlogReward)) {
          return sum + (details.itlogReward || game.win_amount || 0);
        }
        return sum;
      }, 0) || 0;

      // From quest rewards (quest_rewards_claimed table)
      const { data: questRewardsData } = await supabase
        .from('quest_rewards_claimed')
        .select('total_reward');

      const questItlog = questRewardsData?.reduce((sum, reward) => sum + (reward.total_reward || 0), 0) || 0;

      totalItlogDistributed = earningItlog + gameItlog + questItlog;

      return {
        totalUsers: totalUsers || 0,
        activeUsers: activeUsers || 0,
        gamesPlayed: gamesPlayed || 0,
        totalWinnings,
        totalItlogDistributed
      };
    },
    refetchInterval: 30000, // Refetch every 30 seconds
    staleTime: 10000, // Consider data stale after 10 seconds
  });

  // Format numbers for display
  const formatNumber = (num: number) => {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
      return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
  };

  const formatCurrency = (num: number) => {
    if (num >= 1000000) {
      return '₱' + (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
      return '₱' + (num / 1000).toFixed(1) + 'K';
    }
    return '₱' + num.toFixed(2);
  };

  const featuredGames = [
    {
      id: "mines",
      name: "Mines",
      description: "Navigate through dangerous minefields to uncover hidden treasures and multiply your winnings",
      icon: "💣",
      href: "/games/mines",
      gradient: "from-red-500 via-orange-500 to-yellow-500",
      multiplier: "x5000",
      difficulty: "Medium"
    },
    {
      id: "wheel",
      name: "Wheel of Fortune",
      description: "Spin the legendary wheel of fortune and watch your destiny unfold with every rotation",
      icon: "🎡",
      href: "/games/wheel",
      gradient: "from-purple-500 via-pink-500 to-rose-500",
      multiplier: "x50",
      difficulty: "Easy"
    },
    {
      id: "slots",
      name: "Fortune Reels",
      description: "Experience the thrill of classic slot machines with modern crypto rewards and bonus features",
      icon: "🎰",
      href: "/games/slots",
      gradient: "from-blue-500 via-cyan-500 to-teal-500",
      multiplier: "x10000",
      difficulty: "Easy"
    },
    {
      id: "blackjack",
      name: "Blackjack",
      description: "Master the art of 21 in this classic card game where strategy meets luck",
      icon: "🃏",
      href: "/games/blackjack",
      gradient: "from-green-500 via-emerald-500 to-teal-500",
      multiplier: "x2",
      difficulty: "Hard"
    },
    {
      id: "dice",
      name: "Dice Roll",
      description: "Roll the dice of destiny and customize your risk for maximum rewards",
      icon: "🎲",
      href: "/games/dice",
      gradient: "from-yellow-500 via-amber-500 to-orange-500",
      multiplier: "x99",
      difficulty: "Medium"
    }
  ];

  const stats = [
    { 
      label: "Active Players", 
      value: statsData ? formatNumber(statsData.activeUsers) : "Loading...", 
      icon: Users, 
      color: "text-blue-400",
      bg: "from-blue-500/10 to-cyan-500/10",
      border: "border-blue-500/20"
    },
    { 
      label: "Games Played", 
      value: statsData ? formatNumber(statsData.gamesPlayed) : "Loading...", 
      icon: Gamepad2, 
      color: "text-purple-400",
      bg: "from-purple-500/10 to-pink-500/10",
      border: "border-purple-500/20"
    },
    { 
      label: "Total Winnings", 
      value: statsData ? formatCurrency(statsData.totalWinnings) : "Loading...", 
      icon: Trophy, 
      color: "text-green-400",
      bg: "from-green-500/10 to-emerald-500/10",
      border: "border-green-500/20"
    },
    { 
      label: "$ITLOG Distributed", 
      value: statsData ? formatNumber(statsData.totalItlogDistributed) : "Loading...", 
      icon: Sparkles, 
      color: "text-yellow-400",
      bg: "from-yellow-500/10 to-orange-500/10",
      border: "border-yellow-500/20"
    }
  ];

  const features = [
    {
      icon: Target,
      title: "Provably Fair",
      description: "All games use cryptographic algorithms to ensure complete fairness and transparency"
    },
    {
      icon: Zap,
      title: "Instant Payouts",
      description: "Lightning-fast withdrawals with our advanced blockchain integration"
    },
    {
      icon: Crown,
      title: "VIP Rewards",
      description: "Exclusive bonuses and privileges for our most valued players"
    }
  ];

  return (
    <Layout>
      <div className="min-h-screen">
        {/* Revolutionary Hero Section */}
        <section className="relative min-h-screen flex items-center justify-center overflow-hidden">
          {/* Animated Background */}
          <div className="absolute inset-0">
            <div className="absolute inset-0 bg-gradient-to-br from-purple-900/20 via-blue-900/20 to-green-900/20"></div>
            <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl animate-float"></div>
            <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-bounce-gentle"></div>
            <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-purple-500/5 to-blue-500/5 rounded-full blur-3xl"></div>
          </div>

          <div className="relative z-10 max-w-7xl mx-auto mobile-container text-center">
            <div className="responsive-margin">
              {/* Main Hero Content */}
              <div className="space-y-8">
                <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
                  <Sparkles className="w-5 h-5 text-yellow-400" />
                  <span className="text-sm font-medium text-gradient">The Future of Crypto Gaming</span>
                </div>

                <h1 className="mobile-hero leading-tight mb-6">
                  <span className="block text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-black bg-gradient-to-r from-purple-400 via-pink-400 to-gold-400 bg-clip-text text-transparent animate-pulse">
                    LuckyBet2Log
                  </span>
                  <span className="block text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-muted-foreground mt-4">
                    Where Fortune Meets Innovation
                  </span>
                </h1>

                <p className="mobile-subtitle text-muted-foreground max-w-4xl mx-auto leading-relaxed">
                  Experience the ultimate crypto casino with exclusive $ITLOG token rewards, 
                  provably fair games, and revolutionary blockchain technology that puts you in control.
                </p>

                {/* Enhanced CTA Buttons */}
                <div className="flex flex-col sm:flex-row gap-6 justify-center mt-12">
                  <Link to="/games">
                    <Button className="modern-button button-primary group px-8 py-6 text-lg">
                      <Gamepad2 className="w-6 h-6 mr-3 group-hover:rotate-12 transition-transform duration-300" />
                      Start Your Journey
                      <Sparkles className="w-4 h-4 ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                    </Button>
                  </Link>
                  <Link to="/earn">
                    <Button className="modern-button button-secondary group px-8 py-6 text-lg">
                      <TrendingUp className="w-6 h-6 mr-3 group-hover:scale-110 transition-transform duration-300" />
                      Earn $ITLOG Tokens
                      <Crown className="w-4 h-4 ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                    </Button>
                  </Link>
                </div>
              </div>
            </div>
          </div>

          {/* Scroll Indicator */}
          <div className="absolute bottom-8 left-1/2 transform -translate-x-1/2">
            <div className="w-6 h-10 border-2 border-white/30 rounded-full flex justify-center">
              <div className="w-1 h-3 bg-white/50 rounded-full mt-2 animate-bounce"></div>
            </div>
          </div>
        </section>

        {/* Enhanced Stats Section */}
        <section className="py-20 relative">
          <div className="max-w-7xl mx-auto mobile-container">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
              {stats.map((stat, index) => {
                const Icon = stat.icon;
                return (
                  <Card 
                    key={index} 
                    className={`modern-card bg-gradient-to-br ${stat.bg} border ${stat.border} hover-lift group`}
                  >
                    <CardContent className="p-6 text-center">
                      <div className={`inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br ${stat.bg} border ${stat.border} mb-4 group-hover:scale-110 transition-transform duration-300`}>
                        <Icon className={`w-8 h-8 ${stat.color}`} />
                      </div>
                      <div className="text-3xl font-black text-foreground mb-2">{stat.value}</div>
                      <div className="text-sm font-medium text-muted-foreground">{stat.label}</div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          </div>
        </section>

        {/* Redesigned Featured Games */}
        <section className="py-24 relative">
          <div className="max-w-7xl mx-auto mobile-container">
            <div className="text-center mb-16">
              <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
                <Gamepad2 className="w-5 h-5 text-purple-400" />
                <span className="text-sm font-medium text-gradient">Featured Games</span>
              </div>
              <h2 className="mobile-title mb-6">
                <span className="text-gradient">Epic Gaming Experience</span>
              </h2>
              <p className="mobile-text text-muted-foreground max-w-3xl mx-auto">
                Dive into our collection of meticulously crafted games, each offering unique rewards and thrilling gameplay
              </p>
            </div>

            <div className="game-grid">
              {featuredGames.map((game, index) => (
                <Card key={game.id} className="game-card relative overflow-hidden">
                  <div className={`absolute inset-0 bg-gradient-to-br ${game.gradient} opacity-5`}></div>
                  
                  <CardHeader className="relative z-10 pb-4">
                    <div className="flex items-center justify-between mb-6">
                      <div className={`w-20 h-20 rounded-3xl bg-gradient-to-br ${game.gradient} flex items-center justify-center text-3xl shadow-lg group-hover:scale-110 group-hover:rotate-6 transition-all duration-500`}>
                        {game.icon}
                      </div>
                      <div className="text-right">
                        <div className="text-xs text-muted-foreground">Max Win</div>
                        <div className="text-lg font-bold text-green-400">{game.multiplier}</div>
                      </div>
                    </div>
                    
                    <CardTitle className="text-xl font-bold mb-2">{game.name}</CardTitle>
                    
                    <div className="flex items-center space-x-2 mb-4">
                      <span className={`px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r ${game.gradient} text-black`}>
                        {game.difficulty}
                      </span>
                      <div className="flex items-center space-x-1">
                        {[...Array(3)].map((_, i) => (
                          <Star key={i} className="w-3 h-3 text-yellow-400 fill-current" />
                        ))}
                      </div>
                    </div>
                  </CardHeader>
                  
                  <CardContent className="relative z-10 pt-0">
                    <p className="text-muted-foreground text-sm mb-6 leading-relaxed">
                      {game.description}
                    </p>
                    <Link to={game.href}>
                      <Button className={`w-full modern-button bg-gradient-to-r ${game.gradient} text-white border-0 font-semibold group`}>
                        <span>Play {game.name}</span>
                        <Zap className="w-4 h-4 ml-2 group-hover:rotate-12 transition-transform duration-300" />
                      </Button>
                    </Link>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        </section>

        {/* Enhanced $ITLOG Token Section */}
        <section className="py-24 relative overflow-hidden">
          <div className="absolute inset-0 bg-gradient-to-r from-yellow-500/5 via-orange-500/5 to-red-500/5"></div>
          <div className="absolute top-0 left-0 w-full h-full">
            <div className="absolute top-1/4 left-1/4 w-64 h-64 bg-yellow-500/10 rounded-full blur-3xl animate-pulse"></div>
            <div className="absolute bottom-1/4 right-1/4 w-64 h-64 bg-orange-500/10 rounded-full blur-3xl animate-pulse"></div>
          </div>

          <div className="relative z-10 max-w-7xl mx-auto mobile-container text-center">
            <div className="inline-flex items-center justify-center w-24 h-24 itlog-token rounded-3xl text-4xl mb-8 shadow-2xl animate-bounce-gentle">
              <Coins className="w-12 h-12 text-black" />
            </div>
            
            <h2 className="mobile-title mb-6">
              <span className="text-gradient-gold">Exclusive $ITLOG Token</span>
            </h2>
            
            <p className="mobile-text text-muted-foreground mb-12 max-w-4xl mx-auto">
              Every spin, every bet, every game offers a chance to win our revolutionary $ITLOG token! 
              With 0-10% chance per game and rewards ranging from 10,000 to 1,000,000 tokens based on your bet multiplier.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
              {features.map((feature, index) => {
                const Icon = feature.icon;
                return (
                  <Card key={index} className="modern-card hover-lift group">
                    <CardContent className="p-8 text-center">
                      <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-yellow-500/20 to-orange-500/20 rounded-2xl mb-6 group-hover:scale-110 transition-transform duration-300">
                        <Icon className="w-8 h-8 text-yellow-400" />
                      </div>
                      <h3 className="text-xl font-bold mb-4">{feature.title}</h3>
                      <p className="text-muted-foreground">{feature.description}</p>
                    </CardContent>
                  </Card>
                );
              })}
            </div>

            <div className="flex flex-col sm:flex-row gap-6 justify-center">
              <Link to="/earn">
                <Button className="modern-button button-gold group px-8 py-6 text-lg">
                  <TrendingUp className="w-6 h-6 mr-3 group-hover:scale-110 transition-transform duration-300" />
                  Start Earning
                  <Sparkles className="w-4 h-4 ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </Button>
              </Link>
              <Link to="/wallet">
                <Button className="modern-button button-secondary group px-8 py-6 text-lg">
                  <Wallet className="w-6 h-6 mr-3 group-hover:scale-110 transition-transform duration-300" />
                  View Wallet
                  <Crown className="w-4 h-4 ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </Button>
              </Link>
            </div>
          </div>
        </section>
      </div>
    </Layout>
  );
};

export default Index;
