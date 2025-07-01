import { useState, useEffect, useCallback } from "react";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "./useAuth";
import { useToast } from "./use-toast";
import { useProfile } from "./useProfile";
import { Tables } from "@/integrations/supabase/types";
import { usePetSystem } from "./usePetSystem";

type FarmingSession = Tables<"farming_sessions">;
type EarningHistory = Tables<"earning_history">;

export const useFarmingSessions = () => {
  const { user } = useAuth();
  const { toast } = useToast();
  const { profile, updateBalance } = useProfile();
  const { activePetBoosts } = usePetSystem();
  const [farmingSession, setFarmingSession] = useState<FarmingSession | null>(
    null,
  );
  const [stakingSession, setStakingSession] = useState<FarmingSession | null>(
    null,
  );
  const [farmingProgress, setFarmingProgress] = useState(0);
  const [stakingProgress, setStakingProgress] = useState(0);
  const [earningHistory, setEarningHistory] = useState<EarningHistory[]>([]);
  const [loading, setLoading] = useState(true);

  const loadSessions = useCallback(async () => {
    if (!user) return;

    try {
      const { data: sessions, error } = await supabase
        .from("farming_sessions")
        .select("*")
        .eq("user_id", user.id)
        .eq("is_active", true);

      if (error) throw error;

      sessions?.forEach((session) => {
        if (session.session_type === "farming") {
          setFarmingSession(session);
        } else if (session.session_type === "staking") {
          setStakingSession(session);
        }
      });
    } catch (error) {
      console.error("Error loading sessions:", error);
    } finally {
      setLoading(false);
    }
  }, [user]);

  const loadEarningHistory = useCallback(async () => {
    if (!user) return;

    try {
      const { data: history, error } = await supabase
        .from("earning_history")
        .select("*")
        .eq("user_id", user.id)
        .order("created_at", { ascending: false })
        .limit(20);

      if (error) throw error;
      setEarningHistory(history || []);
    } catch (error) {
      console.error("Error loading earning history:", error);
    }
  }, [user]);

  // Load existing sessions on component mount
  useEffect(() => {
    if (user) {
      loadSessions();
      loadEarningHistory();
    }
  }, [user, loadSessions, loadEarningHistory]);

  // Calculate progress based on session start time
  const calculateProgress = useCallback((startTime: string) => {
    const start = new Date(startTime).getTime();
    const now = new Date().getTime();
    const elapsed = (now - start) / 1000; // seconds
    const progress = Math.min((elapsed / 300) * 100, 100); // 300 seconds = 5 minutes
    return progress;
  }, []);

  // Update progress for active sessions
  useEffect(() => {
    const interval = setInterval(() => {
      if (farmingSession?.started_at) {
        const progress = calculateProgress(farmingSession.started_at);
        setFarmingProgress(progress);
      }

      if (stakingSession?.started_at) {
        const progress = calculateProgress(stakingSession.started_at);
        setStakingProgress(progress);
      }
    }, 1000);

    return () => clearInterval(interval);
  }, [farmingSession, stakingSession, calculateProgress]);

  const startFarming = async () => {
    if (!user) return;

    try {
      const { data: session, error } = await supabase
        .from("farming_sessions")
        .insert({
          user_id: user.id,
          session_type: "farming",
          is_active: true,
          started_at: new Date().toISOString(),
          tokens_earned: 0,
        })
        .select()
        .single();

      if (error) throw error;

      setFarmingSession(session);
      setFarmingProgress(0);

      toast({
        title: "Token farming activated!",
        description: "You will earn 0.002 $ITLOG every 5 minutes.",
      });
    } catch (error) {
      console.error("Error starting farming:", error);
      toast({
        title: "Error",
        description: "Failed to start farming. Please try again.",
        variant: "destructive",
      });
    }
  };

  const stopFarming = async () => {
    if (!farmingSession) return;

    try {
      const { error } = await supabase
        .from("farming_sessions")
        .update({ is_active: false })
        .eq("id", farmingSession.id);

      if (error) throw error;

      setFarmingSession(null);
      setFarmingProgress(0);

      toast({
        title: "Token farming stopped",
        description: "Farming has been deactivated.",
      });
    } catch (error) {
      console.error("Error stopping farming:", error);
    }
  };

  const harvestFarming = async () => {
    if (!farmingSession || farmingProgress < 100 || !user) return;

    try {
      // Use the database function to handle the reward calculation with proper boosts
      const { data: result, error: updateError } = await supabase.rpc("harvest_farming_rewards", {
        p_user_id: user.id,
        p_session_id: farmingSession.id,
      });

      if (updateError) throw updateError;

      // Type the result properly as the JSON response from the database function
      const harvestResult = result as {
        success: boolean;
        error?: string;
        reward: number;
        base_reward: number;
        farming_boost: number;
        token_multiplier: number;
        total_earned: number;
      };

      if (!harvestResult.success) {
        toast({
          title: "Error",
          description: harvestResult.error || "Failed to harvest reward.",
          variant: "destructive",
        });
        return;
      }

      const tokensEarned = harvestResult.reward;

      // Update local state to restart progress
      setFarmingSession({
        ...farmingSession,
        started_at: new Date().toISOString(),
        tokens_earned: tokensEarned,
      });
      setFarmingProgress(0);
      loadEarningHistory();

      // Refresh profile to update balance in UI
      await updateBalance.mutateAsync({ itlogChange: 0 });

      toast({
        title: "Farming reward harvested!",
        description: `You earned ${tokensEarned.toFixed(6)} $ITLOG tokens! Farming continues...`,
      });
    } catch (error) {
      console.error("Error harvesting farming reward:", error);
      toast({
        title: "Error",
        description: "Failed to harvest reward. Please try again.",
        variant: "destructive",
      });
    }
  };

  const startStaking = async (amount: number) => {
    if (!user || amount <= 0) return;

    try {
      // Check if user has enough balance
      const { data: profile, error: profileError } = await supabase
        .from("profiles")
        .select("php_balance")
        .eq("user_id", user.id)
        .single();

      if (profileError) throw profileError;

      if ((profile.php_balance || 0) < amount) {
        toast({
          title: "Insufficient balance",
          description:
            "You don't have enough PHP balance to stake this amount.",
          variant: "destructive",
        });
        return;
      }

      // Deduct staking amount from balance using the database function
      const { error: updateError } = await supabase.rpc("update_user_balance", {
        p_user_id: user.id,
        p_php_change: -amount,
      });

      if (updateError) throw updateError;

      const { data: session, error } = await supabase
        .from("farming_sessions")
        .insert({
          user_id: user.id,
          session_type: "staking",
          is_active: true,
          started_at: new Date().toISOString(),
          stake_amount: amount,
          tokens_earned: 0,
        })
        .select()
        .single();

      if (error) throw error;

      setStakingSession(session);
      setStakingProgress(0);

      // Refresh profile to update balance in UI
      await updateBalance.mutateAsync({ phpChange: 0 });

      toast({
        title: "Staking activated!",
        description: `Staking ₱${amount.toFixed(2)} - earning rewards every 5 minutes.`,
      });
    } catch (error) {
      console.error("Error starting staking:", error);
      toast({
        title: "Error",
        description: "Failed to start staking. Please try again.",
        variant: "destructive",
      });
    }
  };

  const stopStaking = async () => {
    if (!stakingSession || !user) return;

    try {
      // Return staked amount to user balance using the database function
      const { error: updateError } = await supabase.rpc("update_user_balance", {
        p_user_id: user.id,
        p_php_change: stakingSession.stake_amount || 0,
      });

      if (updateError) throw updateError;

      const { error } = await supabase
        .from("farming_sessions")
        .update({ is_active: false })
        .eq("id", stakingSession.id);

      if (error) throw error;

      setStakingSession(null);
      setStakingProgress(0);

      // Refresh profile to update balance in UI
      await updateBalance.mutateAsync({ phpChange: 0 });

      toast({
        title: "Staking stopped",
        description: "Staking has been deactivated and your stake returned.",
      });
    } catch (error) {
      console.error("Error stopping staking:", error);
    }
  };

  const claimStaking = async () => {
    if (!stakingSession || stakingProgress < 100 || !user) return;

    try {
      const stakeAmount = stakingSession.stake_amount || 0;
      let tokensEarned = stakeAmount * 0.000005; // 0.000005 of staked amount as ITLOG tokens

      // Apply pet boosts
      const stakingBoost = activePetBoosts.find(boost => boost.trait_type === 'staking_boost');
      const tokenMultiplier = activePetBoosts.find(boost => boost.trait_type === 'token_multiplier');

      if (stakingBoost) {
        tokensEarned *= stakingBoost.total_boost;
      }
      if (tokenMultiplier) {
        tokensEarned *= tokenMultiplier.total_boost;
      }

      // Update user's itlog tokens (but don't return stake since we continue staking)
      const { error: updateError } = await supabase.rpc("update_user_balance", {
        p_user_id: user.id,
        p_php_change: 0, // Don't return stake since we continue staking
        p_itlog_change: tokensEarned,
      });

      if (updateError) throw updateError;

      // Add to earning history
      const { error: historyError } = await supabase
        .from("earning_history")
        .insert({
          user_id: user.id,
          session_type: "staking",
          tokens_earned: tokensEarned,
          stake_amount: stakeAmount,
        });

      if (historyError) throw historyError;

      // Update session with tokens earned and restart with new timestamp
      const { error: sessionError } = await supabase
        .from("farming_sessions")
        .update({
          tokens_earned: tokensEarned,
          started_at: new Date().toISOString(), // Restart with new timestamp
        })
        .eq("id", stakingSession.id);

      if (sessionError) throw sessionError;

      // Update local state to restart progress
      setStakingSession({
        ...stakingSession,
        started_at: new Date().toISOString(),
        tokens_earned: tokensEarned,
      });
      setStakingProgress(0);
      loadEarningHistory();

      // Refresh profile to update balance in UI
      await updateBalance.mutateAsync({ itlogChange: 0 });

      toast({
        title: "Staking reward claimed!",
        description: `You earned ${tokensEarned.toFixed(4)} $ITLOG tokens! Staking continues...`,
      });
    } catch (error) {
      console.error("Error claiming staking reward:", error);
      toast({
        title: "Error",
        description: "Failed to claim reward. Please try again.",
        variant: "destructive",
      });
    }
  };

  return {
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
    claimStaking,
  };
};