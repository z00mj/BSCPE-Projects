import { useQuests } from '@/hooks/useQuests';
import { useActivityTracker } from '@/hooks/useActivityTracker';


import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Trophy, Target, Clock, CheckCircle, Gift } from "lucide-react";


const QuestSystem = () => {
  const { dailyQuests, canClaimRewards, hasClaimedToday, loading, claimRewards, fixQuestProgress } = useQuests();
  const { trackActivity } = useActivityTracker();

  // Add error boundary for null/undefined data
  if (!dailyQuests) {
    return (
      <Card className="bg-card/50 backdrop-blur-sm border-purple-500/30">
        <CardContent className="p-6">
          <div className="flex items-center justify-center h-32">
            <p className="text-muted-foreground">Loading quests...</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  const getDifficultyColor = (tier: string) => {
    switch (tier) {
      case 'easy': return 'bg-green-500/20 text-green-400 border-green-500/30';
      case 'medium': return 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
      case 'hard': return 'bg-red-500/20 text-red-400 border-red-500/30';
      default: return 'bg-gray-500/20 text-gray-400 border-gray-500/30';
    }
  };

  const getDifficultyIcon = (tier: string) => {
    switch (tier) {
      case 'easy': return '⭐';
      case 'medium': return '⭐⭐';
      case 'hard': return '⭐⭐⭐';
      default: return '⭐';
    }
  };

  if (loading) {
    return (
      <Card className="bg-card/50 backdrop-blur-sm border-purple-500/30">
        <CardContent className="p-6">
          <div className="flex items-center justify-center h-32">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
          </div>
        </CardContent>
      </Card>
    );
  }

  const completedQuests = dailyQuests.filter(quest => quest.is_completed).length;
  const totalQuests = dailyQuests.length;

  const handleClaimRewards = async () => {
    await claimRewards();
    trackActivity({
      activityType: 'Claimed Daily Quest Rewards',
      activityValue: 1
    });
  };

  return (
    <Card className="bg-card/50 backdrop-blur-sm border-purple-500/30 glow-purple">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Target className="w-6 h-6 text-purple-400" />
          <span className="bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
            Daily Quests & Tasks
          </span>
        </CardTitle>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4 text-sm text-muted-foreground">
            <div className="flex items-center gap-1">
              <Clock className="w-4 h-4" />
              Resets daily at midnight
            </div>
            <div className="flex items-center gap-1">
              <Trophy className="w-4 h-4" />
              {completedQuests}/{totalQuests} completed
            </div>
          </div>
          <Button 
            variant="outline" 
            size="sm" 
            onClick={fixQuestProgress}
            className="text-xs"
          >
            Refresh Progress
          </Button>
        </div>
      </CardHeader>

      <CardContent className="p-6 space-y-6">
        {/* Quest Progress Overview */}
        <div className="space-y-2">
          <div className="flex justify-between text-sm">
            <span>Daily Progress</span>
            <span>{completedQuests}/{totalQuests} quests</span>
          </div>
          <Progress 
            value={(completedQuests / Math.max(totalQuests, 1)) * 100} 
            className="h-2"
          />
        </div>

        <Separator />

        {/* Individual Quests */}
        <div className="space-y-4">
          {dailyQuests.map((quest) => {
            // Safe progress calculation with null checks
            const progress = quest?.progress ?? 0;
            const targetValue = quest?.quest_definition?.target_value ?? 1;
            const progressPercentage = Math.min((progress / targetValue) * 100, 100);

            return (
              <div 
                key={quest.id} 
                className={`p-4 rounded-lg border transition-all duration-200 ${
                  quest.is_completed 
                    ? 'bg-green-500/10 border-green-500/30' 
                    : 'bg-card/30 border-border/50'
                }`}
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 space-y-2">
                    <div className="flex items-center gap-2">
                      <Badge 
                        variant="outline" 
                        className={getDifficultyColor(quest.quest_definition.difficulty_tier)}
                      >
                        {getDifficultyIcon(quest.quest_definition.difficulty_tier)} {quest.quest_definition.difficulty_tier.toUpperCase()}
                      </Badge>
                      {quest.is_completed && (
                        <CheckCircle className="w-5 h-5 text-green-400" />
                      )}
                    </div>

                    <div>
                      <h4 className="font-semibold text-foreground">
                        {quest.quest_definition.title}
                      </h4>
                      <p className="text-sm text-muted-foreground">
                        {quest.quest_definition.description}
                      </p>
                    </div>

                    <div className="space-y-1">
                      <div className="flex justify-between text-xs">
                        <span>Progress</span>
                        <span>
                          {(progress || 0).toFixed((targetValue >= 100) ? 0 : 2)} / {targetValue}
                        </span>
                      </div>
                      <Progress value={progressPercentage} className="h-2" />
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Claim Rewards Section */}
        {totalQuests === 3 && (
          <>
            <Separator />
            <div className="text-center space-y-4">
              {hasClaimedToday ? (
                <div className="p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                  <Gift className="w-8 h-8 mx-auto mb-2 text-green-400" />
                  <p className="text-green-400 font-semibold">Rewards claimed for today!</p>
                  <p className="text-sm text-muted-foreground">Come back tomorrow for new quests</p>
                </div>
              ) : canClaimRewards ? (
                <div className="space-y-3">
                  <div className="p-4 bg-gradient-to-r from-purple-500/10 to-pink-500/10 border border-purple-500/30 rounded-lg">
                    <Trophy className="w-8 h-8 mx-auto mb-2 text-gold-400" />
                    <p className="text-foreground font-semibold">All quests completed!</p>
                    <p className="text-sm text-muted-foreground">Click below to claim your $ITLOG rewards</p>
                  </div>
                  <Button 
                    onClick={handleClaimRewards}
                    size="lg"
                    className="w-full h-14 modern-button bg-gradient-to-r from-purple-500 to-blue-500 hover:from-purple-600 hover:to-blue-600 text-white font-bold text-lg"
                  >
                    <Gift className="w-5 h-5 mr-2" />
                    Claim Rewards
                  </Button>
                </div>
              ) : (
                <div className="p-4 bg-card/30 border border-border/50 rounded-lg">
                  <Target className="w-8 h-8 mx-auto mb-2 text-muted-foreground" />
                  <p className="text-muted-foreground">Complete all 3 quests to claim rewards</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Progress: {completedQuests}/3 quests completed
                  </p>
                </div>
              )}
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
};

export default QuestSystem;
