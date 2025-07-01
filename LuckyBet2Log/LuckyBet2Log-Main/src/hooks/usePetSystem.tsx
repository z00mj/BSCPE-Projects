import { useState, useEffect, useCallback } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "./useAuth";
import { useToast } from "./use-toast";
import { Tables } from "@/integrations/supabase/types";

type EggType = Tables<"egg_types">;
type PetType = Tables<"pet_types">;
type UserEgg = Tables<"user_eggs">;
type UserPet = Tables<"user_pets">;

// Define types for Supabase joined query responses
type UserEggQueryResult = UserEgg & {
  egg_type: EggType;
};

type UserPetQueryResult = UserPet & {
  pet_type: PetType;
};

interface PetBoost {
  trait_type: string;
  total_boost: number;
}

// RPC Response types - these need to match the actual database function returns
type PurchaseEggResponse = {
  success: boolean;
  error?: string;
  tokens_spent?: number;
} | boolean;

type StartIncubationResponse = {
  success: boolean;
  error?: string;
} | boolean;

type HatchEggResponse = {
  success: boolean;
  error?: string;
  rarity?: string;
  pet_name?: string;
  pet_emoji?: string;
} | boolean;

type PlacePetResponse = {
  success: boolean;
  error?: string;
} | boolean;

export const usePetSystem = () => {
  const { user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const [eggTypes, setEggTypes] = useState<EggType[]>([]);
  const [userEggs, setUserEggs] = useState<UserEggQueryResult[]>([]);
  const [userPets, setUserPets] = useState<UserPetQueryResult[]>([]);
  const [activePetBoosts, setActivePetBoosts] = useState<PetBoost[]>([]);
  const [loading, setLoading] = useState(true);

  // Load egg types
  const loadEggTypes = useCallback(async () => {
    try {
      const { data, error } = await supabase
        .from("egg_types")
        .select("*")
        .order("price");

      if (error) throw error;
      setEggTypes(data || []);
    } catch (error) {
      console.error("Error loading egg types:", error);
    }
  }, []);

  // Load user eggs
  const loadUserEggs = useCallback(async () => {
    if (!user) return;

    try {
      const { data, error } = await supabase
        .from("user_eggs")
        .select(`
          *,
          egg_type:egg_types(*)
        `)
        .eq("user_id", user.id)
        .order("created_at", { ascending: false });

      if (error) throw error;
      setUserEggs((data || []) as unknown as UserEggQueryResult[]);
    } catch (error) {
      console.error("Error loading user eggs:", error);
    }
  }, [user]);

  // Load user pets
  const loadUserPets = useCallback(async () => {
    if (!user) return;

    try {
      const { data, error } = await supabase
        .from("user_pets")
        .select(`
          *,
          pet_type:pet_types(*)
        `)
        .eq("user_id", user.id)
        .order("created_at", { ascending: false });

      if (error) throw error;
      setUserPets((data || []) as unknown as UserPetQueryResult[]);
    } catch (error) {
      console.error("Error loading user pets:", error);
    }
  }, [user]);

  // Load active pet boosts
  const loadActivePetBoosts = useCallback(async () => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("get_user_pet_boosts", {
        p_user_id: user.id
      });

      if (error) throw error;
      setActivePetBoosts(data || []);
    } catch (error) {
      console.error("Error loading pet boosts:", error);
    }
  }, [user]);

  // Load all data
  useEffect(() => {
    if (user) {
      Promise.all([
        loadEggTypes(),
        loadUserEggs(),
        loadUserPets(),
        loadActivePetBoosts()
      ]).finally(() => setLoading(false));
    }
  }, [user, loadEggTypes, loadUserEggs, loadUserPets, loadActivePetBoosts]);

  // Purchase egg
  const purchaseEgg = async (eggTypeId: number) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("purchase_egg", {
        p_user_id: user.id,
        p_egg_type_id: eggTypeId
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "Egg purchased!",
            description: "You successfully purchased an egg!"
          });
          loadUserEggs();
          queryClient.invalidateQueries({ queryKey: ['profile'] }); // Invalidate profile query
        } else {
          toast({
            title: "Purchase failed",
            description: "Not enough tokens or other error occurred",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { success: boolean; error?: string; tokens_spent?: number };
        if (response.success) {
          toast({
            title: "Egg purchased!",
            description: `You spent ${response.tokens_spent || 0} $ITLOG tokens.`
          });
          loadUserEggs();
          queryClient.invalidateQueries({ queryKey: ['profile'] }); // Invalidate profile query
        } else {
          toast({
            title: "Purchase failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error purchasing egg:", error);
      toast({
        title: "Error",
        description: "Failed to purchase egg. Please try again.",
        variant: "destructive"
      });
    }
  };

  // Start incubation
  const startIncubation = async (eggId: string) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("start_incubation", {
        p_user_id: user.id,
        p_egg_id: eggId
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "Incubation started!",
            description: "Your egg is now incubating."
          });
          loadUserEggs();
        } else {
          toast({
            title: "Incubation failed",
            description: "Unable to start incubation",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { success: boolean; error?: string };
        if (response.success) {
          toast({
            title: "Incubation started!",
            description: "Your egg is now incubating."
          });
          loadUserEggs();
        } else {
          toast({
            title: "Incubation failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error starting incubation:", error);
      toast({
        title: "Error",
        description: "Failed to start incubation. Please try again.",
        variant: "destructive"
      });
    }
  };

  // Hatch egg
  const hatchEgg = async (eggId: string) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("hatch_egg", {
        p_user_id: user.id,
        p_egg_id: eggId
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "🎉 Egg Hatched!",
            description: "Congratulations! You got a new pet!"
          });
          loadUserEggs();
          loadUserPets();
        } else {
          toast({
            title: "Hatching failed",
            description: "Unable to hatch egg",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { success: boolean; error?: string; rarity?: string; pet_name?: string; pet_emoji?: string };
        if (response.success) {
          toast({
            title: "🎉 Egg Hatched!",
            description: `You got a ${response.rarity || 'new'} ${response.pet_name || 'pet'} ${response.pet_emoji || '🐾'}!`
          });
          loadUserEggs();
          loadUserPets();
        } else {
          toast({
            title: "Hatching failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error hatching egg:", error);
      toast({
        title: "Error",
        description: "Failed to hatch egg. Please try again.",
        variant: "destructive"
      });
    }
  };

  // Place pet in garden
  const placePetInGarden = async (petId: string, position: number) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("place_pet_in_garden", {
        p_user_id: user.id,
        p_pet_id: petId,
        p_position: position
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "Pet placed in garden!",
            description: "Your pet is now providing its boost."
          });
          loadUserPets();
          loadActivePetBoosts();
        } else {
          toast({
            title: "Placement failed",
            description: "Unable to place pet in garden",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { success: boolean; error?: string };
        if (response.success) {
          toast({
            title: "Pet placed in garden!",
            description: "Your pet is now providing its boost."
          });
          loadUserPets();
          loadActivePetBoosts();
        } else {
          toast({
            title: "Placement failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error placing pet:", error);
      toast({
        title: "Error",
        description: "Failed to place pet. Please try again.",
        variant: "destructive"
      });
    }
  };

  // Remove pet from garden
  const removePetFromGarden = async (petId: string) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("remove_pet_from_garden", {
        p_user_id: user.id,
        p_pet_id: petId
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "Pet removed from garden",
            description: "Your pet is back in inventory."
          });
          loadUserPets();
          loadActivePetBoosts();
        } else {
          toast({
            title: "Removal failed",
            description: "Unable to remove pet from garden",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { success: boolean; error?: string };
        if (response.success) {
          toast({
            title: "Pet removed from garden",
            description: "Your pet is back in inventory."
          });
          loadUserPets();
          loadActivePetBoosts();
        } else {
          toast({
            title: "Removal failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error removing pet:", error);
      toast({
        title: "Error",
        description: "Failed to remove pet. Please try again.",
        variant: "destructive"
      });
    }
  };

  // Sell pet for ITLOG tokens
  const sellPet = async (petId: string) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("sell_pet", {
        p_user_id: user.id,
        p_pet_id: petId
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "Pet sold!",
            description: "You received ITLOG tokens for your pet."
          });
          loadUserPets();
          queryClient.invalidateQueries({ queryKey: ['profile'] }); // Invalidate profile query
        } else {
          toast({
            title: "Sale failed",
            description: "Unable to sell pet",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { 
          success: boolean; 
          error?: string; 
          pet_name?: string; 
          pet_emoji?: string; 
          rarity?: string; 
          tokens_earned?: number;
          drop_rate?: number;
        };
        if (response.success) {
          toast({
            title: "🏦 Pet Sold!",
            description: `You sold your ${response.rarity} ${response.pet_name} ${response.pet_emoji} for ${response.tokens_earned} $ITLOG tokens!`
          });
          loadUserPets();
          queryClient.invalidateQueries({ queryKey: ['profile'] }); // Invalidate profile query
        } else {
          toast({
            title: "Sale failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error selling pet:", error);
      toast({
        title: "Error",
        description: "Failed to sell pet. Please try again.",
        variant: "destructive"
      });
    }
  };

  // Skip egg hatching time for 50 ITLOG tokens
  const skipEggHatching = async (eggId: string) => {
    if (!user) return;

    try {
      const { data, error } = await supabase.rpc("skip_egg_hatching", {
        p_user_id: user.id,
        p_egg_id: eggId
      });

      if (error) throw error;

      // Handle both boolean and object responses
      if (typeof data === 'boolean') {
        if (data) {
          toast({
            title: "⏰ Hatching Skipped!",
            description: "Your egg is now ready to hatch!"
          });
          loadUserEggs();
          queryClient.invalidateQueries({ queryKey: ['profile'] }); // Invalidate profile query
        } else {
          toast({
            title: "Skip failed",
            description: "Unable to skip egg hatching",
            variant: "destructive"
          });
        }
      } else if (data && typeof data === 'object') {
        const response = data as { 
          success: boolean; 
          error?: string; 
          tokens_spent?: number;
          message?: string;
        };
        if (response.success) {
          toast({
            title: "⏰ Hatching Skipped!",
            description: `Spent ${response.tokens_spent} $ITLOG tokens. ${response.message || 'Your egg is ready to hatch!'}`
          });
          loadUserEggs();
          queryClient.invalidateQueries({ queryKey: ['profile'] }); // Invalidate profile query
        } else {
          toast({
            title: "Skip failed",
            description: response.error || "Unknown error occurred",
            variant: "destructive"
          });
        }
      }
    } catch (error) {
      console.error("Error skipping egg hatching:", error);
      toast({
        title: "Error",
        description: "Failed to skip egg hatching. Please try again.",
        variant: "destructive"
      });
    }
  };

  return {
    eggTypes,
    userEggs,
    userPets,
    activePetBoosts,
    loading,
    purchaseEgg,
    startIncubation,
    hatchEgg,
    placePetInGarden,
    removePetFromGarden,
    sellPet,
    skipEggHatching,
    loadUserEggs,
    loadUserPets,
    loadActivePetBoosts
  };
};