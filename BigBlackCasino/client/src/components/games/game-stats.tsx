import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Trophy, TrendingUp, Coins, Gem } from 'lucide-react';

export default function GameStats() {
  const { data: statsData } = useQuery({
    queryKey: ['/api/games/stats'],
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  const stats = statsData?.stats || {};
  const jackpotPool = statsData?.jackpotPool || '0';

  const winRate = stats.totalBets > 0 ? 
    ((parseFloat(stats.totalWinnings || '0') / (parseFloat(stats.totalWinnings || '0') + parseFloat(stats.totalLosses || '0'))) * 100).toFixed(1) 
    : '0.0';

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <Card className="bg-casino-dark border-casino-orange/30">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium text-gray-400">Total Bets</CardTitle>
          <Coins className="h-4 w-4 casino-orange" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-white">
            {stats.totalBets?.toLocaleString() || '0'}
          </div>
        </CardContent>
      </Card>

      <Card className="bg-casino-dark border-casino-orange/30">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium text-gray-400">Total Winnings</CardTitle>
          <Trophy className="h-4 w-4 casino-gold" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold casino-gold">
            ₱{parseFloat(stats.totalWinnings || '0').toLocaleString()}
          </div>
        </CardContent>
      </Card>

      <Card className="bg-casino-dark border-casino-orange/30">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium text-gray-400">Win Rate</CardTitle>
          <TrendingUp className="h-4 w-4 text-green-400" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-green-400">
            {winRate}%
          </div>
        </CardContent>
      </Card>

      <Card className="bg-casino-dark border-casino-orange/30">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium text-gray-400">$BBC Earned</CardTitle>
          <Gem className="h-4 w-4 casino-orange" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold casino-orange">
            {parseFloat(stats.bbcEarned || '0').toFixed(3)} $BBC
          </div>
        </CardContent>
      </Card>

      {/* Jackpot Pool */}
      <Card className="bg-gradient-to-r from-casino-orange to-casino-red col-span-full">
        <CardContent className="text-center py-6">
          <h3 className="text-2xl font-bold text-white mb-2">
            <Gem className="inline mr-2" />
            $BBC Jackpot Pool
          </h3>
          <div className="text-4xl font-black casino-gold mb-2">
            {parseFloat(jackpotPool).toFixed(3)} $BBC
          </div>
          <p className="text-white/90">Next winner could be you! Play any game for a chance to win.</p>
        </CardContent>
      </Card>
    </div>
  );
}
