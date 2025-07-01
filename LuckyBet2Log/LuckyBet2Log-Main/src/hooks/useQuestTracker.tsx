
import { useCallback } from 'react';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from './useAuth';
import type { Json } from '@/integrations/supabase/types';

export const useQuestTracker = () => {
  const { user } = useAuth();

  const trackQuestProgress = useCallback(async (
    activityType: string,
    activityValue: number = 1,
    gameType?: string,
    metadata?: Json
  ) => {
    if (!user) return;

    try {
      // Insert the activity into user_activities table
      const { error: activityError } = await supabase
        .from('user_activities')
        .insert({
          user_id: user.id,
          activity_type: activityType,
          activity_value: activityValue,
          game_type: gameType,
          metadata: metadata || {}
        });

      if (activityError) {
        console.error('Error inserting activity:', activityError);
        return;
      }

      // Update quest progress using the existing function
      const { error: questError } = await supabase.rpc('update_quest_progress', {
        p_user_id: user.id,
        p_activity_type: activityType,
        p_activity_value: activityValue,
        p_game_type: gameType,
        p_metadata: metadata || {}
      });

      if (questError) {
        console.error('Error updating quest progress:', questError);
      }

      // Check balance-based quests after any activity
      const { error: balanceError } = await supabase.rpc('check_balance_quests', {
        p_user_id: user.id
      });

      if (balanceError) {
        console.error('Error checking balance quests:', balanceError);
      }

    } catch (error) {
      console.error('Error tracking quest progress:', error);
    }
  }, [user]);

  // Specific tracking functions for common activities
  const trackGameWin = useCallback(async (winAmount: number, gameType: string) => {
    await trackQuestProgress('win_game', winAmount, gameType);
  }, [trackQuestProgress]);

  const trackGameLoss = useCallback(async (gameType: string) => {
    await trackQuestProgress('lose_game', 1, gameType);
  }, [trackQuestProgress]);

  const trackGamePlay = useCallback(async (gameType: string) => {
    await trackQuestProgress('play_game', 1, gameType);
  }, [trackQuestProgress]);

  const trackBet = useCallback(async (betAmount: number, gameType: string) => {
    await trackQuestProgress('place_bet', betAmount, gameType);
  }, [trackQuestProgress]);

  const trackDeposit = useCallback(async (amount: number, status: string = 'approved') => {
    await trackQuestProgress('deposit', amount, undefined, { status });
  }, [trackQuestProgress]);

  const trackWithdraw = useCallback(async (amount: number, status: string = 'approved') => {
    await trackQuestProgress('withdraw', amount, undefined, { status });
  }, [trackQuestProgress]);

  const trackFarmingClaim = useCallback(async () => {
    await trackQuestProgress('claim_farming', 1);
  }, [trackQuestProgress]);

  return {
    trackQuestProgress,
    trackGameWin,
    trackGameLoss,
    trackGamePlay,
    trackBet,
    trackDeposit,
    trackWithdraw,
    trackFarmingClaim
  };
};
