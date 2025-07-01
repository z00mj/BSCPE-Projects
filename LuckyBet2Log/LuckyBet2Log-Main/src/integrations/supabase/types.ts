export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  public: {
    Tables: {
      appeals: {
        Row: {
          admin_response: string | null
          created_at: string | null
          email: string
          id: string
          message: string
          status: string | null
          updated_at: string | null
          user_id: string | null
          username: string
        }
        Insert: {
          admin_response?: string | null
          created_at?: string | null
          email: string
          id?: string
          message: string
          status?: string | null
          updated_at?: string | null
          user_id?: string | null
          username: string
        }
        Update: {
          admin_response?: string | null
          created_at?: string | null
          email?: string
          id?: string
          message?: string
          status?: string | null
          updated_at?: string | null
          user_id?: string | null
          username?: string
        }
        Relationships: []
      }
      daily_quests: {
        Row: {
          created_at: string | null
          date: string
          id: string
          is_completed: boolean | null
          progress: number | null
          quest_definition_id: number
          user_id: string
        }
        Insert: {
          created_at?: string | null
          date?: string
          id?: string
          is_completed?: boolean | null
          progress?: number | null
          quest_definition_id: number
          user_id: string
        }
        Update: {
          created_at?: string | null
          date?: string
          id?: string
          is_completed?: boolean | null
          progress?: number | null
          quest_definition_id?: number
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "daily_quests_quest_definition_id_fkey"
            columns: ["quest_definition_id"]
            isOneToOne: false
            referencedRelation: "quest_definitions"
            referencedColumns: ["id"]
          },
        ]
      }
      deposit_notifications: {
        Row: {
          created_at: string | null
          deposit_id: string
          id: string
          is_read: boolean | null
          message: string
          user_id: string
        }
        Insert: {
          created_at?: string | null
          deposit_id: string
          id?: string
          is_read?: boolean | null
          message: string
          user_id: string
        }
        Update: {
          created_at?: string | null
          deposit_id?: string
          id?: string
          is_read?: boolean | null
          message?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "deposit_notifications_deposit_id_fkey"
            columns: ["deposit_id"]
            isOneToOne: false
            referencedRelation: "deposits"
            referencedColumns: ["id"]
          },
        ]
      }
      deposits: {
        Row: {
          amount: number
          created_at: string
          id: string
          payment_method: string
          processed_at: string | null
          processed_by: string | null
          receipt_url: string | null
          status: string
          user_id: string
        }
        Insert: {
          amount: number
          created_at?: string
          id?: string
          payment_method: string
          processed_at?: string | null
          processed_by?: string | null
          receipt_url?: string | null
          status?: string
          user_id: string
        }
        Update: {
          amount?: number
          created_at?: string
          id?: string
          payment_method?: string
          processed_at?: string | null
          processed_by?: string | null
          receipt_url?: string | null
          status?: string
          user_id?: string
        }
        Relationships: []
      }
      earning_history: {
        Row: {
          created_at: string | null
          id: string
          session_type: string
          stake_amount: number | null
          tokens_earned: number
          user_id: string
        }
        Insert: {
          created_at?: string | null
          id?: string
          session_type: string
          stake_amount?: number | null
          tokens_earned?: number
          user_id: string
        }
        Update: {
          created_at?: string | null
          id?: string
          session_type?: string
          stake_amount?: number | null
          tokens_earned?: number
          user_id?: string
        }
        Relationships: []
      }
      egg_types: {
        Row: {
          created_at: string | null
          hatch_time: number
          id: number
          name: string
          price: number
          rarity: string
        }
        Insert: {
          created_at?: string | null
          hatch_time?: number
          id?: number
          name: string
          price: number
          rarity: string
        }
        Update: {
          created_at?: string | null
          hatch_time?: number
          id?: number
          name?: string
          price?: number
          rarity?: string
        }
        Relationships: []
      }
      farming_sessions: {
        Row: {
          id: string
          is_active: boolean
          last_reward_at: string | null
          session_type: string
          stake_amount: number | null
          started_at: string | null
          tokens_earned: number
          user_id: string
        }
        Insert: {
          id?: string
          is_active?: boolean
          last_reward_at?: string | null
          session_type: string
          stake_amount?: number | null
          started_at?: string | null
          tokens_earned?: number
          user_id: string
        }
        Update: {
          id?: string
          is_active?: boolean
          last_reward_at?: string | null
          session_type?: string
          stake_amount?: number | null
          started_at?: string | null
          tokens_earned?: number
          user_id?: string
        }
        Relationships: []
      }
      game_history: {
        Row: {
          bet_amount: number
          created_at: string | null
          game_details: Json | null
          game_type: string
          id: string
          loss_amount: number | null
          multiplier: number | null
          result_type: string
          user_id: string
          win_amount: number | null
        }
        Insert: {
          bet_amount: number
          created_at?: string | null
          game_details?: Json | null
          game_type: string
          id?: string
          loss_amount?: number | null
          multiplier?: number | null
          result_type: string
          user_id: string
          win_amount?: number | null
        }
        Update: {
          bet_amount?: number
          created_at?: string | null
          game_details?: Json | null
          game_type?: string
          id?: string
          loss_amount?: number | null
          multiplier?: number | null
          result_type?: string
          user_id?: string
          win_amount?: number | null
        }
        Relationships: []
      }
      game_sessions: {
        Row: {
          bet_amount: number
          client_seed: string
          created_at: string
          game_type: string
          id: string
          itlog_won: number
          nonce: number
          result_hash: string
          server_seed: string
          user_id: string
          win_amount: number
        }
        Insert: {
          bet_amount: number
          client_seed: string
          created_at?: string
          game_type: string
          id?: string
          itlog_won?: number
          nonce: number
          result_hash: string
          server_seed: string
          user_id: string
          win_amount?: number
        }
        Update: {
          bet_amount?: number
          client_seed?: string
          created_at?: string
          game_type?: string
          id?: string
          itlog_won?: number
          nonce?: number
          result_hash?: string
          server_seed?: string
          user_id?: string
          win_amount?: number
        }
        Relationships: []
      }
      password_reset_codes: {
        Row: {
          created_at: string | null
          email: string
          expires_at: string | null
          id: string
          is_used: boolean | null
          user_id: string | null
          verification_code: string
        }
        Insert: {
          created_at?: string | null
          email: string
          expires_at?: string | null
          id?: string
          is_used?: boolean | null
          user_id?: string | null
          verification_code: string
        }
        Update: {
          created_at?: string | null
          email?: string
          expires_at?: string | null
          id?: string
          is_used?: boolean | null
          user_id?: string | null
          verification_code?: string
        }
        Relationships: []
      }
      pet_types: {
        Row: {
          created_at: string | null
          drop_rate: number
          egg_type_id: number | null
          id: number
          name: string
          rarity: string
          sprite_emoji: string
          trait_type: string
          trait_value: number
        }
        Insert: {
          created_at?: string | null
          drop_rate?: number
          egg_type_id?: number | null
          id?: number
          name: string
          rarity: string
          sprite_emoji?: string
          trait_type: string
          trait_value?: number
        }
        Update: {
          created_at?: string | null
          drop_rate?: number
          egg_type_id?: number | null
          id?: number
          name?: string
          rarity?: string
          sprite_emoji?: string
          trait_type?: string
          trait_value?: number
        }
        Relationships: [
          {
            foreignKeyName: "pet_types_egg_type_id_fkey"
            columns: ["egg_type_id"]
            isOneToOne: false
            referencedRelation: "egg_types"
            referencedColumns: ["id"]
          },
        ]
      }
      profiles: {
        Row: {
          ban_reason: string | null
          coins: number
          created_at: string
          id: string
          is_admin: boolean
          is_banned: boolean
          is_suspended: boolean
          itlog_tokens: number
          php_balance: number
          updated_at: string
          user_id: string
          username: string
          wallet_id: string
        }
        Insert: {
          ban_reason?: string | null
          coins?: number
          created_at?: string
          id?: string
          is_admin?: boolean
          is_banned?: boolean
          is_suspended?: boolean
          itlog_tokens?: number
          php_balance?: number
          updated_at?: string
          user_id: string
          username: string
          wallet_id: string
        }
        Update: {
          ban_reason?: string | null
          coins?: number
          created_at?: string
          id?: string
          is_admin?: boolean
          is_banned?: boolean
          is_suspended?: boolean
          itlog_tokens?: number
          php_balance?: number
          updated_at?: string
          user_id?: string
          username?: string
          wallet_id?: string
        }
        Relationships: []
      }
      quest_definitions: {
        Row: {
          created_at: string | null
          description: string
          difficulty_tier: string
          id: number
          reward_max: number
          reward_min: number
          target_value: number | null
          task_type: string
          title: string
        }
        Insert: {
          created_at?: string | null
          description: string
          difficulty_tier: string
          id?: number
          reward_max: number
          reward_min: number
          target_value?: number | null
          task_type: string
          title: string
        }
        Update: {
          created_at?: string | null
          description?: string
          difficulty_tier?: string
          id?: number
          reward_max?: number
          reward_min?: number
          target_value?: number | null
          task_type?: string
          title?: string
        }
        Relationships: []
      }
      quest_rewards_claimed: {
        Row: {
          created_at: string | null
          date: string
          id: string
          quest_ids: string[]
          total_reward: number
          user_id: string
        }
        Insert: {
          created_at?: string | null
          date?: string
          id?: string
          quest_ids: string[]
          total_reward: number
          user_id: string
        }
        Update: {
          created_at?: string | null
          date?: string
          id?: string
          quest_ids?: string[]
          total_reward?: number
          user_id?: string
        }
        Relationships: []
      }
      receipt_validations: {
        Row: {
          confidence_score: number | null
          created_at: string | null
          deposit_id: string | null
          extracted_amount: number | null
          extracted_method: string | null
          extracted_text: string | null
          id: string
          is_valid: boolean | null
          validation_errors: string[] | null
        }
        Insert: {
          confidence_score?: number | null
          created_at?: string | null
          deposit_id?: string | null
          extracted_amount?: number | null
          extracted_method?: string | null
          extracted_text?: string | null
          id?: string
          is_valid?: boolean | null
          validation_errors?: string[] | null
        }
        Update: {
          confidence_score?: number | null
          created_at?: string | null
          deposit_id?: string | null
          extracted_amount?: number | null
          extracted_method?: string | null
          extracted_text?: string | null
          id?: string
          is_valid?: boolean | null
          validation_errors?: string[] | null
        }
        Relationships: [
          {
            foreignKeyName: "receipt_validations_deposit_id_fkey"
            columns: ["deposit_id"]
            isOneToOne: false
            referencedRelation: "deposits"
            referencedColumns: ["id"]
          },
        ]
      }
      user_activities: {
        Row: {
          activity_type: string
          activity_value: number | null
          created_at: string | null
          game_type: string | null
          id: string
          metadata: Json | null
          session_id: string | null
          user_id: string
        }
        Insert: {
          activity_type: string
          activity_value?: number | null
          created_at?: string | null
          game_type?: string | null
          id?: string
          metadata?: Json | null
          session_id?: string | null
          user_id: string
        }
        Update: {
          activity_type?: string
          activity_value?: number | null
          created_at?: string | null
          game_type?: string | null
          id?: string
          metadata?: Json | null
          session_id?: string | null
          user_id?: string
        }
        Relationships: []
      }
      user_eggs: {
        Row: {
          created_at: string | null
          egg_type_id: number | null
          hatch_time: string | null
          id: string
          incubation_start: string | null
          status: string
          user_id: string
        }
        Insert: {
          created_at?: string | null
          egg_type_id?: number | null
          hatch_time?: string | null
          id?: string
          incubation_start?: string | null
          status?: string
          user_id: string
        }
        Update: {
          created_at?: string | null
          egg_type_id?: number | null
          hatch_time?: string | null
          id?: string
          incubation_start?: string | null
          status?: string
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "user_eggs_egg_type_id_fkey"
            columns: ["egg_type_id"]
            isOneToOne: false
            referencedRelation: "egg_types"
            referencedColumns: ["id"]
          },
        ]
      }
      user_pets: {
        Row: {
          created_at: string | null
          garden_position: number | null
          id: string
          is_active: boolean | null
          name: string | null
          pet_type_id: number | null
          user_id: string
        }
        Insert: {
          created_at?: string | null
          garden_position?: number | null
          id?: string
          is_active?: boolean | null
          name?: string | null
          pet_type_id?: number | null
          user_id: string
        }
        Update: {
          created_at?: string | null
          garden_position?: number | null
          id?: string
          is_active?: boolean | null
          name?: string | null
          pet_type_id?: number | null
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "user_pets_pet_type_id_fkey"
            columns: ["pet_type_id"]
            isOneToOne: false
            referencedRelation: "pet_types"
            referencedColumns: ["id"]
          },
        ]
      }
      withdrawal_notifications: {
        Row: {
          created_at: string | null
          id: string
          is_read: boolean | null
          message: string
          user_id: string
          withdrawal_id: string
        }
        Insert: {
          created_at?: string | null
          id?: string
          is_read?: boolean | null
          message: string
          user_id: string
          withdrawal_id: string
        }
        Update: {
          created_at?: string | null
          id?: string
          is_read?: boolean | null
          message?: string
          user_id?: string
          withdrawal_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "withdrawal_notifications_withdrawal_id_fkey"
            columns: ["withdrawal_id"]
            isOneToOne: false
            referencedRelation: "withdrawals"
            referencedColumns: ["id"]
          },
        ]
      }
      withdrawals: {
        Row: {
          admin_response: string | null
          amount: number
          bank_account_name: string | null
          bank_account_number: string | null
          bank_name: string | null
          created_at: string
          id: string
          processed_at: string | null
          processed_by: string | null
          status: string
          user_id: string
          withdrawal_method: string | null
          withdrawal_type: string
        }
        Insert: {
          admin_response?: string | null
          amount: number
          bank_account_name?: string | null
          bank_account_number?: string | null
          bank_name?: string | null
          created_at?: string
          id?: string
          processed_at?: string | null
          processed_by?: string | null
          status?: string
          user_id: string
          withdrawal_method?: string | null
          withdrawal_type: string
        }
        Update: {
          admin_response?: string | null
          amount?: number
          bank_account_name?: string | null
          bank_account_number?: string | null
          bank_name?: string | null
          created_at?: string
          id?: string
          processed_at?: string | null
          processed_by?: string | null
          status?: string
          user_id?: string
          withdrawal_method?: string | null
          withdrawal_type?: string
        }
        Relationships: []
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      admin_delete_user: {
        Args: { target_user_id: string }
        Returns: Json
      }
      assign_daily_quests: {
        Args: { p_user_id: string }
        Returns: undefined
      }
      check_balance_quests: {
        Args: { p_user_id: string }
        Returns: undefined
      }
      check_quest_completion_dependencies: {
        Args: { p_user_id: string }
        Returns: undefined
      }
      claim_quest_rewards: {
        Args: { p_user_id: string }
        Returns: Json
      }
      clear_user_data: {
        Args: { p_user_id: string }
        Returns: boolean
      }
      create_password_reset_code: {
        Args: { p_email: string }
        Returns: Json
      }
      execute_sql: {
        Args: { query: string }
        Returns: undefined
      }
      fix_quest_progress_for_user: {
        Args: { p_user_id: string }
        Returns: undefined
      }
      generate_verification_code: {
        Args: Record<PropertyKey, never>
        Returns: string
      }
      get_user_pet_boosts: {
        Args: { p_user_id: string }
        Returns: {
          trait_type: string
          total_boost: number
        }[]
      }
      handle_deposit_approval: {
        Args: { p_user_id: string; p_amount: number; p_status?: string }
        Returns: undefined
      }
      handle_game_play: {
        Args: { p_user_id: string; p_game_type: string }
        Returns: undefined
      }
      handle_game_win: {
        Args: { p_user_id: string; p_win_amount: number; p_game_type: string }
        Returns: undefined
      }
      harvest_farming_rewards: {
        Args: { p_user_id: string; p_session_id: string }
        Returns: Json
      }
      hatch_egg: {
        Args: { p_user_id: string; p_egg_id: string }
        Returns: Json
      }
      place_pet_in_garden: {
        Args: { p_user_id: string; p_pet_id: string; p_position: number }
        Returns: Json
      }
      purchase_egg: {
        Args: { p_user_id: string; p_egg_type_id: number }
        Returns: Json
      }
      remove_pet_from_garden: {
        Args: { p_user_id: string; p_pet_id: string }
        Returns: Json
      }
      reset_all_balances: {
        Args: Record<PropertyKey, never>
        Returns: boolean
      }
      reset_all_coins: {
        Args: Record<PropertyKey, never>
        Returns: boolean
      }
      reset_all_itlog_tokens: {
        Args: Record<PropertyKey, never>
        Returns: boolean
      }
      reset_all_php_balances: {
        Args: Record<PropertyKey, never>
        Returns: boolean
      }
      reset_daily_quests: {
        Args: Record<PropertyKey, never>
        Returns: undefined
      }
      sell_pet: {
        Args: { p_user_id: string; p_pet_id: string }
        Returns: Json
      }
      skip_egg_hatching: {
        Args: { p_user_id: string; p_egg_id: string }
        Returns: Json
      }
      start_incubation: {
        Args: { p_user_id: string; p_egg_id: string }
        Returns: Json
      }
      update_quest_progress: {
        Args: {
          p_user_id: string
          p_activity_type: string
          p_activity_value?: number
          p_game_type?: string
          p_metadata?: Json
        }
        Returns: undefined
      }
      update_user_balance: {
        Args: {
          p_user_id: string
          p_php_change?: number
          p_coins_change?: number
          p_itlog_change?: number
        }
        Returns: boolean
      }
      verify_and_reset_password: {
        Args: {
          p_email: string
          p_verification_code: string
          p_new_password: string
        }
        Returns: Json
      }
    }
    Enums: {
      [_ in never]: never
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DefaultSchema = Database[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof Database },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof (Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        Database[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof Database }
  ? (Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      Database[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof Database },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof Database }
  ? Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof Database },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof Database }
  ? Database[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof Database },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends { schema: keyof Database }
  ? Database[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof Database },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof Database
  }
    ? keyof Database[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends { schema: keyof Database }
  ? Database[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {},
  },
} as const
