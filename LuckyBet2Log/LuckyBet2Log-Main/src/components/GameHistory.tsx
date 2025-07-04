
import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useGameHistory, GameHistoryEntry } from '@/hooks/useGameHistory';
import { Trash2, TrendingUp, TrendingDown, Target, RotateCcw } from 'lucide-react';

interface GameHistoryProps {
  gameType?: string;
  showStats?: boolean;
  maxHeight?: string;
}

const GameHistory = ({ gameType, showStats = true, maxHeight = "400px" }: GameHistoryProps) => {
  const { history, loading, clearHistory, getStats, refreshHistory } = useGameHistory(gameType);
  const [activeTab, setActiveTab] = useState("history");
  const [lastUpdate, setLastUpdate] = useState<Date>(new Date());
  const [isClearing, setIsClearing] = useState(false);
  
  const stats = getStats();

  // Update timestamp when history changes (indicates real-time update)
  React.useEffect(() => {
    console.log('GameHistory: History changed, length:', history.length);
    setLastUpdate(new Date());
  }, [history.length]);

  // Debug history changes
  React.useEffect(() => {
    console.log('GameHistory: Full history updated:', history);
  }, [history]);

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
  };

  const getResultIcon = (resultType: string) => {
    switch (resultType) {
      case 'win':
        return '🎉';
      case 'loss':
        return '😔';
      case 'push':
        return '🤝';
      default:
        return '❓';
    }
  };

  const getResultColor = (resultType: string) => {
    switch (resultType) {
      case 'win':
        return 'text-green-400';
      case 'loss':
        return 'text-red-400';
      case 'push':
        return 'text-blue-400';
      default:
        return 'text-gray-400';
    }
  };

  const renderHistoryItem = (entry: GameHistoryEntry) => {
    // Check if this is an $ITLOG win
    const isItlogWin = entry.game_details && 
                      typeof entry.game_details === 'object' && 
                      entry.game_details !== null &&
                      'isItlogWin' in entry.game_details && 
                      entry.game_details.isItlogWin === true;
    
    return (
      <div key={entry.id} className="border-b border-border/20 pb-3 mb-3 last:border-b-0">
        <div className="flex justify-between items-start mb-2">
          <div className="flex items-center gap-2">
            <span className="text-lg">{isItlogWin ? '🪙' : getResultIcon(entry.result_type)}</span>
            <div>
              <span className="font-medium capitalize">{entry.game_type.replace('-', ' ')}</span>
              {isItlogWin && <span className="ml-2 text-xs bg-gradient-to-r from-gold-500 to-amber-500 text-black px-2 py-1 rounded">$ITLOG</span>}
              <p className="text-xs text-muted-foreground">{formatDate(entry.created_at)}</p>
            </div>
          </div>
          <Badge variant="secondary" className={isItlogWin ? "bg-gradient-to-r from-gold-500 to-amber-500 text-black" : getResultColor(entry.result_type)}>
            {isItlogWin ? '$ITLOG WIN' : entry.result_type.toUpperCase()}
          </Badge>
        </div>
        
        <div className="grid grid-cols-2 gap-2 text-sm">
          <div>
            <span className="text-muted-foreground">Bet: </span>
            <span className="font-medium">{entry.bet_amount.toFixed(2)} coins</span>
          </div>
          {entry.result_type === 'win' && (
            <div>
              <span className="text-muted-foreground">Won: </span>
              {isItlogWin ? (
                <span className="font-medium text-amber-400">+{entry.win_amount.toFixed(0)} $ITLOG</span>
              ) : (
                <span className="font-medium text-green-400">+{entry.win_amount.toFixed(2)} coins</span>
              )}
            </div>
          )}
          {entry.result_type === 'loss' && (
            <div>
              <span className="text-muted-foreground">Lost: </span>
              <span className="font-medium text-red-400">-{entry.loss_amount.toFixed(2)} coins</span>
            </div>
          )}
          {entry.multiplier > 0 && (
          <div>
            <span className="text-muted-foreground">Multiplier: </span>
            <span className="font-medium">{entry.multiplier.toFixed(2)}x</span>
          </div>
          )}
      </div>
    </div>
  );
  };

  return (
    <Card className="bg-card/50 backdrop-blur-sm border-primary/20">
      <CardHeader>
        <CardTitle className="flex items-center justify-between">
          <span>Game History ({history.length})</span>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              onClick={refreshHistory}
              className="text-xs"
            >
              <RotateCcw className="w-3 h-3 mr-1" />
              Refresh
            </Button>
            {history.length > 0 && (
              <Button
                size="sm"
                variant="outline"
                onClick={async () => {
                  setIsClearing(true);
                  try {
                    await clearHistory(gameType);
                  } finally {
                    setIsClearing(false);
                  }
                }}
                disabled={isClearing || loading}
                className="text-xs"
              >
                <Trash2 className="w-3 h-3 mr-1" />
                {isClearing ? "Clearing..." : "Clear"}
              </Button>
            )}
          </div>
        </CardTitle>
      </CardHeader>
      <CardContent>
        {showStats && (
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="history">History</TabsTrigger>
              <TabsTrigger value="stats">Stats</TabsTrigger>
            </TabsList>
            
            <TabsContent value="stats" className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center p-3 bg-blue-500/10 rounded-lg">
                  <Target className="w-6 h-6 mx-auto mb-1 text-blue-400" />
                  <p className="text-sm text-muted-foreground">Total Games</p>
                  <p className="text-xl font-bold">{stats.totalGames}</p>
                </div>
                <div className="text-center p-3 bg-green-500/10 rounded-lg">
                  <TrendingUp className="w-6 h-6 mx-auto mb-1 text-green-400" />
                  <p className="text-sm text-muted-foreground">Win Rate</p>
                  <p className="text-xl font-bold text-green-400">{stats.winRate.toFixed(1)}%</p>
                </div>
                <div className="text-center p-3 bg-green-500/10 rounded-lg">
                  <span className="text-2xl">🎉</span>
                  <p className="text-sm text-muted-foreground">Wins</p>
                  <p className="text-xl font-bold text-green-400">{stats.wins}</p>
                </div>
                <div className="text-center p-3 bg-red-500/10 rounded-lg">
                  <TrendingDown className="w-6 h-6 mx-auto mb-1 text-red-400" />
                  <p className="text-sm text-muted-foreground">Losses</p>
                  <p className="text-xl font-bold text-red-400">{stats.losses}</p>
                </div>
              </div>
              
              <div className="text-center p-4 bg-card/30 rounded-lg">
                <p className="text-sm text-muted-foreground mb-1">Net Profit/Loss</p>
                <p className={`text-2xl font-bold ${stats.netProfit >= 0 ? 'text-green-400' : 'text-red-400'}`}>
                  {stats.netProfit >= 0 ? '+' : ''}{stats.netProfit.toFixed(2)} coins
                </p>
              </div>
            </TabsContent>
            
            <TabsContent value="history">
              <ScrollArea className="w-full" style={{ height: maxHeight }}>
                {loading ? (
                  <p className="text-center text-muted-foreground py-8">Loading history...</p>
                ) : history.length === 0 ? (
                  <p className="text-center text-muted-foreground py-8">
                    No game history yet. Start playing to see your results here!
                  </p>
                ) : (
                  <div className="space-y-3">
                    {history.map(renderHistoryItem)}
                  </div>
                )}
              </ScrollArea>
            </TabsContent>
          </Tabs>
        )}
        
        {!showStats && (
          <ScrollArea className="w-full" style={{ height: maxHeight }}>
            {loading ? (
              <p className="text-center text-muted-foreground py-8">Loading history...</p>
            ) : history.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">
                No game history yet. Start playing to see your results here!
              </p>
            ) : (
              <div className="space-y-3">
                {history.map(renderHistoryItem)}
              </div>
            )}
          </ScrollArea>
        )}
      </CardContent>
    </Card>
  );
};

export default GameHistory;
