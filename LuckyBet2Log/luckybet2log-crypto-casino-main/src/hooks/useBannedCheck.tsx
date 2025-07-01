import { useState, useEffect } from "react";
import { useAuth } from "./useAuth";
import { supabase } from "@/integrations/supabase/client";

export const useBannedCheck = () => {
  const [isBanned, setIsBanned] = useState(false);
  const [reason, setReason] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const { user } = useAuth();

  useEffect(() => {
    const checkBanStatus = async () => {
      if (!user) {
        setLoading(false);
        return;
      }

      try {
        const { data: profile } = await supabase
          .from('profiles')
          .select('is_banned, ban_reason')
          .eq('user_id', user.id)
          .single();

        setIsBanned(profile?.is_banned || false);
        setReason(profile?.ban_reason || "Your account has been banned. You can appeal to the admin.");
      } catch (error) {
        console.error('Error checking ban status:', error);
      } finally {
        setLoading(false);
      }
    };

    checkBanStatus();
  }, [user]);

  return { isBanned, reason, loading };
};