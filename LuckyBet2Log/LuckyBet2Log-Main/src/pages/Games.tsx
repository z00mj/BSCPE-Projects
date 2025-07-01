
import Layout from "@/components/Layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";
import { Coins, Sparkles, Trophy } from "lucide-react";

const Games = () => {
  const games = [
    {
      id: "mines",
      name: "Mines",
      description: "Navigate through a minefield to collect treasures. Cash out before hitting a mine!",
      icon: "💣",
      href: "/games/mines",
      gradient: "from-red-500 to-orange-500",
      difficulty: "Medium",
      maxWin: "x5000"
    },
    {
      id: "wheel",
      name: "Wheel of Fortune",
      description: "Spin the wheel and bet on colors, numbers, or multipliers for instant wins!",
      icon: "🎡",
      href: "/games/wheel",
      gradient: "from-purple-500 to-pink-500",
      difficulty: "Easy",
      maxWin: "x50"
    },
    {
      id: "slots",
      name: "Fortune Reels",
      description: "Classic 3-reel slot machine with crypto symbols, wilds, and bonus rounds!",
      icon: "🎰",
      href: "/games/slots",
      gradient: "from-blue-500 to-cyan-500",
      difficulty: "Easy",
      maxWin: "x10000"
    },
    {
      id: "blackjack",
      name: "Blackjack",
      description: "Beat the dealer by getting as close to 21 as possible without going over!",
      icon: "🃏",
      href: "/games/blackjack",
      gradient: "from-green-500 to-emerald-500",
      difficulty: "Hard",
      maxWin: "x2"
    },
    {
      id: "dice",
      name: "Dice Roll",
      description: "Roll a number from 1-100 with customizable risk ranges and multipliers!",
      icon: "🎲",
      href: "/games/dice",
      gradient: "from-yellow-500 to-amber-500",
      difficulty: "Medium",
      maxWin: "x99"
    }
  ];

  return (
    <Layout>
      <div className="min-h-screen bg-background py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold mb-6">
              <span className="bg-gradient-to-r from-purple-400 to-gold-400 bg-clip-text text-transparent">
                Casino Games
              </span>
            </h1>
            <p className="text-lg text-muted-foreground max-w-3xl mx-auto">
              Choose from our collection of provably fair games. Each game offers a chance to win exclusive $ITLOG tokens!
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            {games.map((game) => (
              <Card key={game.id} className="bg-card border-border hover:border-primary/40 transition-colors">
                <CardHeader className="pb-4">
                  <div className={`w-20 h-20 rounded-full bg-gradient-to-r ${game.gradient} flex items-center justify-center text-3xl mb-4 mx-auto`}>
                    {game.icon}
                  </div>
                  <CardTitle className="text-2xl text-center">{game.name}</CardTitle>
                  <div className="flex justify-between items-center text-sm">
                    <span className="px-3 py-1 bg-primary/20 rounded-full">{game.difficulty}</span>
                    <span className="text-green-400 font-semibold">Max Win: {game.maxWin}</span>
                  </div>
                </CardHeader>
                <CardContent className="pt-0">
                  <p className="text-muted-foreground mb-6 text-center">
                    {game.description}
                  </p>
                  <Link to={game.href}>
                    <Button className="w-full">
                      Play {game.name}
                    </Button>
                  </Link>
                </CardContent>
              </Card>
            ))}
          </div>

          {/* $ITLOG Info Section */}
          <Card className="bg-card border-border">
            <CardContent className="p-8 text-center">
              <div className="mb-6">
                <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 mb-4">
                  <span className="text-3xl">🪙</span>
                </div>
              </div>

              <h3 className="text-4xl font-bold mb-4">
                <span className="bg-gradient-to-r from-yellow-400 via-orange-400 to-red-400 bg-clip-text text-transparent">
                  $ITLOG Token Rewards
                </span>
              </h3>

              <p className="text-xl text-muted-foreground mb-8 max-w-4xl mx-auto">
                Every game offers a <span className="text-yellow-400 font-semibold">0-10% chance</span> to win exclusive $ITLOG tokens! 
                Rewards range from <span className="text-green-400 font-semibold">10,000 to 1,000,000</span> tokens based on your bet multiplier.
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div className="bg-card/50 rounded-xl p-4 border border-primary/20">
                  <div className="text-2xl font-bold text-yellow-400">0-10%</div>
                  <div className="text-sm text-muted-foreground">Win Chance</div>
                </div>
                <div className="bg-card/50 rounded-xl p-4 border border-primary/20">
                  <div className="text-2xl font-bold text-green-400">1M</div>
                  <div className="text-sm text-muted-foreground">Max Tokens</div>
                </div>
                <div className="bg-card/50 rounded-xl p-4 border border-primary/20">
                  <div className="text-2xl font-bold text-blue-400">All</div>
                  <div className="text-sm text-muted-foreground">Games Eligible</div>
                </div>
              </div>

              <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <Link to="/earn" className="w-full sm:w-auto">
                  <Button 
                    size="lg" 
                    className="w-full sm:w-auto bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-black font-bold border-0"
                  >
                    <Coins className="w-5 h-5 mr-2" />
                    Learn More About $ITLOG
                    <Sparkles className="w-4 h-4 ml-2" />
                  </Button>
                </Link>
                <Link to="/games" className="w-full sm:w-auto">
                  <Button 
                    variant="outline" 
                    size="lg"
                    className="w-full sm:w-auto border-primary/50 hover:bg-primary/10"
                  >
                    <Trophy className="w-5 h-5 mr-2" />
                    Start Playing Now
                  </Button>
                </Link>
              </div>

              <div className="mt-6 p-4 bg-card/50 rounded-lg border border-primary/20">
                <p className="text-sm text-muted-foreground">
                  💡 <span className="font-semibold text-yellow-400">Pro Tip:</span> Higher bet multipliers increase your potential $ITLOG rewards!
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </Layout>
  );
};

export default Games;
