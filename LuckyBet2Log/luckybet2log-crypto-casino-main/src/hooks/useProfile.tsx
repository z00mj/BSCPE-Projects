import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "./useAuth";

interface Profile {
  id: string;
  user_id: string;
  username: string;
  wallet_id: string;
  php_balance: number;
  coins: number;
  itlog_tokens: number;
  is_admin: boolean;
  is_banned: boolean;
  is_suspended: boolean;
  created_at: string;
  updated_at: string;
}

export const useProfile = () => {
  const { user } = useAuth();
  const queryClient = useQueryClient();

  // Set up real-time subscription for profile changes
  useEffect(() => {
    if (!user?.id) return;

    const channel = supabase
      .channel('profile-changes')
      .on(
        'postgres_changes',
        {
          event: 'UPDATE',
          schema: 'public',
          table: 'profiles',
          filter: `user_id=eq.${user.id}`,
        },
        (payload) => {
          // Invalidate and refetch profile data immediately
          queryClient.invalidateQueries({ queryKey: ["profile", user.id] });
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [user?.id, queryClient]);

  const {
    data: profile,
    isLoading,
    error,
  } = useQuery({
    queryKey: ["profile", user?.id],
    queryFn: async () => {
      if (!user) return null;

      const { data, error } = await supabase
        .from("profiles")
        .select("*")
        .eq("user_id", user.id)
        .single();

      if (error) {
        // If user profile not found, sign out the user
        if (error.code === 'PGRST116') {
          console.log('User profile not found, signing out...');
          // Clear local state first
          try {
            // Clear all local storage immediately
            localStorage.clear();
            sessionStorage.clear();
            
            // Sign out from Supabase
            await supabase.auth.signOut();
            
            // Force redirect to auth page
            window.location.href = '/auth';
          } catch (signOutError) {
            console.error('Error during sign out:', signOutError);
            // Force redirect anyway
            window.location.href = '/auth';
          }
          return null;
        }
        console.error('Profile fetch error:', error);
        throw error;
      }
      return data as Profile;
    },
    enabled: !!user,
    retry: false, // Don't retry on profile fetch failure
  });

  const updateBalance = useMutation({
    mutationFn: async ({
      phpChange = 0,
      coinsChange = 0,
      itlogChange = 0,
    }: {
      phpChange?: number;
      coinsChange?: number;
      itlogChange?: number;
    }) => {
      if (!user) throw new Error("User not authenticated");

      const { data, error } = await supabase.rpc("update_user_balance", {
        p_user_id: user.id,
        p_php_change: phpChange,
        p_coins_change: coinsChange,
        p_itlog_change: itlogChange,
      });

      if (error) throw error;
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["profile", user?.id] });
    },
  });

  return {
    profile,
    isLoading,
    error,
    updateBalance,
  };
};
