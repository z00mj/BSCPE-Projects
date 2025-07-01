import { useCallback } from 'react';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from './useAuth';
import type { Database, Json } from '@/integrations/supabase/types';

export interface ActivityData {
  activityType: string;
  activityValue?: number;
  gameType?: string;
  metadata?: Json;
  sessionId?: string;
}

export const useActivityTracker = () => {
  const { user } = useAuth();

  const updateQuestProgress = useCallback(async (data: ActivityData): Promise<void> => {
    if (!user) {
      console.warn('Cannot update quest progress: user not authenticated');
      return;
    }

    try {
      const { error } = await supabase.rpc('update_quest_progress', {
        p_user_id: user.id,
        p_activity_type: data.activityType,
        p_activity_value: data.activityValue || 1,
        p_game_type: data.gameType || null,
        p_metadata: (data.metadata || {}) as Json
      });

      if (error) {
        console.error('Quest progress update error:', error);
        throw error;
      }
    } catch (error) {
      console.error('Error updating quest progress:', error);
      throw error;
    }
  }, [user]);

  const trackActivity = useCallback(async (data: ActivityData): Promise<void> => {
    if (!user) {
      console.warn('Cannot track activity: user not authenticated');
      return;
    }

    try {
      // Insert activity record matching the database schema
      const { error: insertError } = await supabase
        .from('user_activities')
        .insert({
          user_id: user.id,
          activity_type: data.activityType,
          activity_value: data.activityValue || 1,
          game_type: data.gameType || null,
          metadata: data.metadata || {},
          session_id: data.sessionId || `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
        });

      if (insertError) {
        console.error('Activity insertion error:', insertError);
        throw insertError;
      }

      // Update quest progress
      await updateQuestProgress(data);
    } catch (error) {
      console.error('Error tracking activity:', error);
      throw error;
    }
  }, [user, updateQuestProgress]);

  // Game-specific tracking functions
  const trackGamePlay = useCallback((gameType: string, sessionId?: string): Promise<void> => {
    return trackActivity({
      activityType: 'play_game',
      gameType,
      sessionId: sessionId || `game_${gameType}_${Date.now()}`,
      metadata: {
        timestamp: new Date().toISOString(),
        game: gameType
      }
    });
  }, [trackActivity]);

  const trackGameWin = useCallback((gameType: string, winAmount: number, sessionId?: string): Promise<void> => {
    return trackActivity({
      activityType: 'win_game',
      activityValue: winAmount,
      gameType,
      sessionId: sessionId || `win_${gameType}_${Date.now()}`,
      metadata: {
        winAmount,
        timestamp: new Date().toISOString(),
        game: gameType
      }
    });
  }, [trackActivity]);

  const trackGameLoss = useCallback((gameType: string, lossAmount: number, sessionId?: string): Promise<void> => {
    return trackActivity({
      activityType: 'lose_game',
      activityValue: lossAmount,
      gameType,
      sessionId: sessionId || `loss_${gameType}_${Date.now()}`,
      metadata: {
        lossAmount,
        timestamp: new Date().toISOString(),
        game: gameType
      }
    });
  }, [trackActivity]);

  const trackBet = useCallback((gameType: string, betAmount: number, sessionId?: string): Promise<void> => {
    return trackActivity({
      activityType: 'place_bet',
      activityValue: betAmount,
      gameType,
      sessionId: sessionId || `bet_${gameType}_${Date.now()}`,
      metadata: {
        betAmount,
        timestamp: new Date().toISOString(),
        game: gameType
      }
    });
  }, [trackActivity]);

  // Financial tracking functions
  const trackDeposit = useCallback((amount: number, status: string = 'completed'): Promise<void> => {
    return trackActivity({
      activityType: 'deposit',
      activityValue: amount,
      metadata: {
        amount,
        status,
        timestamp: new Date().toISOString()
      }
    });
  }, [trackActivity]);

  const trackWithdrawal = useCallback((amount: number, status: string = 'completed'): Promise<void> => {
    return trackActivity({
      activityType: 'withdraw',
      activityValue: amount,
      metadata: {
        amount,
        status,
        timestamp: new Date().toISOString()
      }
    });
  }, [trackActivity]);

  // Earning and farming functions
  const trackFarmingClaim = useCallback((amount: number): Promise<void> => {
    return trackActivity({
      activityType: 'claim_farming',
      activityValue: amount,
      metadata: {
        amount,
        timestamp: new Date().toISOString(),
        source: 'farming'
      }
    });
  }, [trackActivity]);

  const trackStaking = useCallback((amount: number): Promise<void> => {
    return trackActivity({
      activityType: 'stake_php',
      activityValue: amount,
      metadata: {
        amount,
        timestamp: new Date().toISOString(),
        action: 'stake'
      }
    });
  }, [trackActivity]);

  // Currency and exchange functions
  const trackCurrencyConversion = useCallback((amount: number, fromCurrency: string, toCurrency: string): Promise<void> => {
    return trackActivity({
      activityType: 'convert_currency',
      activityValue: amount,
      metadata: {
        amount,
        fromCurrency,
        toCurrency,
        timestamp: new Date().toISOString()
      }
    });
  }, [trackActivity]);

  const trackItlogExchange = useCallback((amount: number): Promise<void> => {
    return trackActivity({
      activityType: 'exchange_itlog',
      activityValue: amount,
      metadata: {
        amount,
        timestamp: new Date().toISOString(),
        currency: 'ITLOG'
      }
    });
  }, [trackActivity]);

  // Session tracking
  const trackGameSession = useCallback((gameType: string, duration: number, sessionId?: string): Promise<void> => {
    return trackActivity({
      activityType: 'game_session',
      activityValue: duration,
      gameType,
      sessionId: sessionId || `session_${gameType}_${Date.now()}`,
      metadata: {
        duration,
        durationMinutes: Math.round(duration / 60),
        timestamp: new Date().toISOString(),
        game: gameType
      }
    });
  }, [trackActivity]);

  // Balance quest checker
  const checkBalanceQuests = useCallback(async (): Promise<void> => {
    if (!user) {
      console.warn('Cannot check balance quests: user not authenticated');
      return;
    }

    try {
      // Add more specific error handling and logging
      console.log('Checking balance quests for user:', user.id);
    } catch (error) {
      console.error('Detailed error in checkBalanceQuests:', error);
      // Don't throw the error to prevent app crashes
      return;
    }

    try {
      const { error } = await supabase.rpc('check_balance_quests', {
        p_user_id: user.id
      });

      if (error) {
        console.error('Balance quest check error:', error);
        throw error;
      }
    } catch (error) {
      console.error('Error checking balance quests:', error);
      throw error;
    }
  }, [user]);

  // Batch activity tracking for multiple activities
  const trackMultipleActivities = useCallback(async (activities: ActivityData[]): Promise<void> => {
    if (!user) {
      console.warn('Cannot track multiple activities: user not authenticated');
      return;
    }

    try {
      const promises = activities.map(activity => trackActivity(activity));
      await Promise.all(promises);
    } catch (error) {
      console.error('Error tracking multiple activities:', error);
      throw error;
    }
  }, [user, trackActivity]);

  return {
    // Core functions
    trackActivity,
    updateQuestProgress,
    
    // Game tracking
    trackGamePlay,
    trackGameWin,
    trackGameLoss,
    trackBet,
    trackGameSession,
    
    // Financial tracking
    trackDeposit,
    trackWithdrawal,
    
    // Earning functions
    trackFarmingClaim,
    trackStaking,
    
    // Exchange functions
    trackCurrencyConversion,
    trackItlogExchange,
    
    // Quest functions
    checkBalanceQuests,
    
    // Utility functions
    trackMultipleActivities
  };
};
