import { useAuth } from "@/hooks/use-auth";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent } from "@/components/ui/card";
import { formatNumber, formatCurrency } from "@/lib/utils";
import GameCard from "@/components/game-card";
import { 
  Dice1, 
  Coins, 
  Trophy, 
  TrendingUp, 
  Gem,
  Banknote,
  DollarSign,
  Mountain
} from "lucide-react";

export default function Dashboard() {
  const { user } = useAuth();

  const { data: systemSettings } = useQuery({
    queryKey: ['/api/system/settings'],
  });

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Welcome Header */}
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-white mb-2">
          Welcome back, {user?.username}!
        </h2>
        <p className="text-gray-400">Ready to win big? Choose your game and let's roll!</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Current Balance</p>
                <p className="text-2xl font-bold text-white">
                  {user ? formatNumber(user.balance) : "0.00"}
                </p>
              </div>
              <div className="bg-casino-orange/20 p-3 rounded-lg">
                <Coins className="text-casino-orange text-xl w-6 h-6" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">$BBC Tokens</p>
                <p className="text-2xl font-bold text-casino-orange">
                  {user ? formatNumber(user.bbcTokens, 6) : "0.000000"}
                </p>
              </div>
              <div className="bg-casino-gold/20 p-3 rounded-lg">
                <Gem className="text-casino-gold text-xl w-6 h-6" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Games Played</p>
                <p className="text-2xl font-bold text-green-400">0</p>
              </div>
              <div className="bg-green-400/20 p-3 rounded-lg">
                <TrendingUp className="text-green-400 text-xl w-6 h-6" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Total Winnings</p>
                <p className="text-2xl font-bold text-casino-gold">₱0.00</p>
              </div>
              <div className="bg-casino-gold/20 p-3 rounded-lg">
                <Trophy className="text-casino-gold text-xl w-6 h-6" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Jackpot Pool */}
      <div className="casino-gradient rounded-xl p-6 mb-8 text-center">
        <h3 className="text-2xl font-bold text-white mb-2">
          <Gem className="inline-block w-6 h-6 mr-2" />
          $BBC Jackpot Pool
        </h3>
        <div className="text-4xl font-black text-casino-gold mb-2">
          {systemSettings?.settings.jackpotPool 
            ? formatNumber(systemSettings.settings.jackpotPool, 6) + " $BBC"
            : "0.100000 $BBC"
          }
        </div>
        <p className="text-white/90">Next winner could be you! Play any game for a chance to win.</p>
      </div>

      {/* Featured Games Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <GameCard
          title="Luck and Roll"
          description="Spin the wheel of fortune! 16 slices with multipliers up to 10x."
          icon={
            <div className="w-32 h-32 border-4 border-casino-orange rounded-full flex items-center justify-center relative">
              <div className="w-24 h-24 border-2 border-casino-gold rounded-full flex items-center justify-center">
                <Dice1 className="text-casino-gold text-3xl w-8 h-8" />
              </div>
              <div className="absolute -top-2 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-8 border-l-transparent border-r-transparent border-b-casino-gold"></div>
            </div>
          }
          badge="HOT"
          minBet="0.25"
          maxBet="1000.00"
          href="/games/luck-and-roll"
          gradient="bg-gradient-to-br from-casino-orange/20 to-casino-red/20"
        />

        <GameCard
          title="Flip it Jonathan!"
          description="Call heads or tails! Chain wins for massive multipliers."
          icon={
            <div className="w-20 h-20 bg-casino-gold rounded-full flex items-center justify-center relative animate-pulse">
              <Coins className="text-casino-dark text-2xl w-6 h-6" />
            </div>
          }
          minBet="0.25"
          maxBet="1000.00"
          href="/games/flip-it-jonathan"
          gradient="bg-gradient-to-br from-casino-gold/20 to-casino-orange/20"
        />

        <GameCard
          title="Paldo!"
          description="5-reel slot with 25 paylines. Free spins and wilds!"
          icon={
            <div className="grid grid-cols-3 gap-2">
              <div className="w-12 h-12 bg-casino-dark border border-casino-orange rounded flex items-center justify-center">
                <Gem className="text-casino-orange w-6 h-6" />
              </div>
              <div className="w-12 h-12 bg-casino-dark border border-casino-gold rounded flex items-center justify-center">
                <Trophy className="text-casino-gold w-6 h-6" />
              </div>
              <div className="w-12 h-12 bg-casino-dark border border-casino-orange rounded flex items-center justify-center">
                <Gem className="text-casino-orange w-6 h-6" />
              </div>
            </div>
          }
          badge="NEW"
          badgeColor="bg-casino-red"
          href="/games/paldo"
          gradient="bg-gradient-to-br from-casino-red/20 to-casino-orange/20"
        />

        <GameCard
          title="Ipis Sipi"
          description="Guide the roach through kitchen hazards for up to 20x!"
          icon={
            <div className="relative">
              <div className="w-16 h-12 bg-casino-dark rounded-full flex items-center justify-center border border-casino-orange">
                <span className="text-casino-orange text-xl">🪳</span>
              </div>
              <div className="absolute -right-2 -top-1 text-casino-gold text-xs">💰</div>
            </div>
          }
          href="/games/ipis-sipi"
          gradient="bg-gradient-to-br from-green-900/20 to-casino-orange/20"
        />

        <GameCard
          title="Blow it Bolims!"
          description="Inflate the balloon but cash out before it pops!"
          icon={
            <div className="w-20 h-24 rounded-full bg-gradient-to-t from-casino-orange to-casino-red flex items-center justify-center relative">
              <span className="text-white text-xl">💣</span>
              <div className="absolute -top-2 w-2 h-6 bg-casino-gold rounded-full"></div>
            </div>
          }
          href="/games/blow-it-bolims"
          gradient="bg-gradient-to-br from-blue-900/20 to-casino-orange/20"
        />

        <GameCard
          title="$BBC Mining"
          description="Mine $BBC tokens passively while you play!"
          icon={
            <div className="relative">
              <Mountain className="text-casino-gold text-4xl w-12 h-12" />
              <div className="absolute -bottom-2 -right-2 bg-casino-orange text-white rounded-full w-6 h-6 flex items-center justify-center text-xs">
                ⛏️
              </div>
            </div>
          }
          badge="MINE"
          badgeColor="bg-casino-gold text-casino-dark"
          href="/mining"
          gradient="bg-gradient-to-br from-amber-900/20 to-casino-gold/20"
        />
      </div>
    </div>
  );
}
