import { useState, useEffect, useCallback } from 'react';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from './useAuth';
import { useToast } from './use-toast';

export interface QuestDefinition {
  id: number;
  title: string;
  description: string;
  difficulty_tier: 'easy' | 'medium' | 'hard';
  task_type: string;
  target_value: number;
  reward_min: number;
  reward_max: number;
}

export interface DailyQuest {
  id: string;
  quest_definition_id: number;
  progress: number;
  is_completed: boolean;
  quest_definition: QuestDefinition;
}

export const useQuests = () => {
  const { user } = useAuth();
  const { toast } = useToast();
  const [dailyQuests, setDailyQuests] = useState<DailyQuest[]>([]);
  const [canClaimRewards, setCanClaimRewards] = useState(false);
  const [hasClaimedToday, setHasClaimedToday] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchDailyQuests = useCallback(async () => {
    if (!user) {
      setDailyQuests([]);
      setLoading(false);
      return;
    }

    try {
      setLoading(true);

      // First, ensure user has daily quests assigned
      const { error: assignError } = await supabase.rpc('assign_daily_quests', { 
        p_user_id: user.id 
      });
      if (assignError) console.error('Error assigning daily quests:', assignError);

      // Check balance-based quests to ensure they're up to date
      const { error: balanceError } = await supabase.rpc('check_balance_quests', { 
        p_user_id: user.id 
      });
      if (balanceError) console.error('Error checking balance quests:', balanceError);

      // Fetch today's quests
      const { data: quests, error } = await supabase
        .from('daily_quests')
        .select(`
          id,
          quest_definition_id,
          progress,
          is_completed,
          quest_definitions!inner(
            id,
            title,
            description,
            difficulty_tier,
            task_type,
            target_value,
            reward_min,
            reward_max
          )
        `)
        .eq('user_id', user.id)
        .eq('date', new Date().toISOString().split('T')[0]);

      if (error) throw error;

      // Transform the data to match our interface
      const transformedQuests = (quests || []).map(quest => ({
        id: quest.id,
        quest_definition_id: quest.quest_definition_id,
        progress: quest.progress,
        is_completed: quest.is_completed,
        quest_definition: Array.isArray(quest.quest_definitions) 
          ? quest.quest_definitions[0] 
          : quest.quest_definitions
      }));

      setDailyQuests(transformedQuests);

      // Check if all quests are completed
      const allCompleted = transformedQuests?.every(quest => quest.is_completed) || false;
      setCanClaimRewards(allCompleted && transformedQuests?.length === 3);

      // Check if rewards already claimed today
      const { data: claimed } = await supabase
        .from('quest_rewards_claimed')
        .select('id')
        .eq('user_id', user.id)
        .eq('date', new Date().toISOString().split('T')[0])
        .single();

      setHasClaimedToday(!!claimed);
    } catch (error) {
      console.error('Error fetching daily quests:', error);
      // Set safe defaults on error
      setDailyQuests([]);
      setCanClaimRewards(false);
      setHasClaimedToday(false);
    } finally {
      setLoading(false);
    }
  }, [user]);

  const claimRewards = useCallback(async () => {
    if (!user || !canClaimRewards || hasClaimedToday) return;

    try {
      const { data, error } = await supabase.rpc('claim_quest_rewards', {
        p_user_id: user.id
      });

      if (error) throw error;

      // Handle the response data - it might be a number or an object
      const totalReward = typeof data === 'number' ? data : 
        (typeof data === 'object' && data !== null && 'total_reward' in data) 
          ? (data as { total_reward: number }).total_reward 
          : 0;

      toast({
        title: "Rewards Claimed!",
        description: `You earned ${totalReward} $ITLOG tokens!`,
      });

      setHasClaimedToday(true);
      setCanClaimRewards(false);

      // Refresh quests and trigger a page reload to update all balances
      await fetchDailyQuests();

      // Force a page refresh to ensure all components update their state
      window.location.reload();
    } catch (error) {
      console.error('Error claiming rewards:', error);
      toast({
        title: "Error",
        description: "Failed to claim rewards. Please try again.",
        variant: "destructive",
      });
    }
  }, [user, canClaimRewards, hasClaimedToday, toast, fetchDailyQuests]);

  useEffect(() => {
    fetchDailyQuests();
  }, [fetchDailyQuests]);

  // Set up real-time subscription for quest progress updates
  useEffect(() => {
    if (!user) return;

    const channel = supabase
      .channel('quest_progress')
      .on(
        'postgres_changes',
        {
          event: 'UPDATE',
          schema: 'public',
          table: 'daily_quests',
          filter: `user_id=eq.${user.id}`,
        },
        () => {
          fetchDailyQuests();
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [user, fetchDailyQuests]);

  const fixQuestProgress = useCallback(async () => {
    if (!user) return;

    try {
      const { error } = await supabase.rpc('fix_quest_progress_for_user', {
        p_user_id: user.id
      });

      if (error) {
        console.error('Error fixing quest progress:', error);
        return;
      }

      // Refresh quests after fixing
      await fetchDailyQuests();
      
      toast({
        title: "Quest Progress Updated",
        description: "Your quest progress has been recalculated based on your activities.",
      });
    } catch (error) {
      console.error('Error fixing quest progress:', error);
    }
  }, [user, fetchDailyQuests, toast]);

  return {
    dailyQuests,
    canClaimRewards: canClaimRewards && !hasClaimedToday,
    hasClaimedToday,
    loading,
    claimRewards,
    refetch: fetchDailyQuests,
    fixQuestProgress
  };
};