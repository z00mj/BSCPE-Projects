import { useState } from "react";
import Layout from "@/components/Layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useToast } from "@/hooks/use-toast";
import { Shield, Users, CreditCard, TrendingUp, MessageSquare, Trash2, DollarSign, AlertTriangle, UserX, Zap, BarChart3, Settings } from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

type DepositWithProfile = {
  id: string;
  user_id: string;
  amount: number;
  payment_method: string;
  status: string;
  created_at: string;
  username: string;
};

type Appeal = {
  id: string;
  user_id: string;
  username: string;
  email: string;
  message: string;
  status: string;
  admin_response: string | null;
  created_at: string;
  updated_at: string;
};

type WithdrawalWithProfile = {
  id: string;
  user_id: string;
  amount: number;
  withdrawal_type: string;
  withdrawal_method: string | null;
  bank_name: string | null;
  bank_account_name: string | null;
  bank_account_number: string | null;
  status: string;
  created_at: string;
  admin_response: string | null;
  username: string;
};

type Profile = {
  id: string;
  user_id: string;
  username: string;
  wallet_id: string;
  php_balance: number;
  itlog_tokens: number;
  coins: number;
  is_admin: boolean;
  is_banned: boolean;
  is_suspended: boolean;
  ban_reason: string | null;
  created_at: string;
  updated_at: string;
};

const Admin = () => {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [selectedUsers, setSelectedUsers] = useState<string[]>([]);
  const [customPhpAmount, setCustomPhpAmount] = useState("");
  const [customCoinsAmount, setCustomCoinsAmount] = useState("");
  const [customItlogAmount, setCustomItlogAmount] = useState("");

  // Fetch all users
  const { data: users } = useQuery<Profile[]>({
    queryKey: ['admin', 'users'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('profiles')
        .select('*')
        .order('created_at', { ascending: false });

      if (error) throw error;
      return data as Profile[];
    },
  });

  // Fetch appeals
  const { data: appeals } = useQuery<Appeal[]>({
    queryKey: ['admin', 'appeals'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('appeals')
        .select('*')
        .order('created_at', { ascending: false });

      if (error) throw error;
      return data as Appeal[];
    },
  });

  // Fetch pending withdrawals and join with profiles manually
  const { data: withdrawals } = useQuery<WithdrawalWithProfile[]>({
    queryKey: ['admin', 'withdrawals'],
    queryFn: async () => {
      // First get pending withdrawals
      const { data: withdrawalsData, error: withdrawalsError } = await supabase
        .from('withdrawals')
        .select('*')
        .eq('status', 'pending')
        .order('created_at', { ascending: false });

      if (withdrawalsError) throw withdrawalsError;
      if (!withdrawalsData || withdrawalsData.length === 0) return [];

      // Get user IDs from withdrawals
      const userIds = withdrawalsData.map(withdrawal => withdrawal.user_id);

      // Get profiles for those users
      const { data: profilesData, error: profilesError } = await supabase
        .from('profiles')
        .select('user_id, username')
        .in('user_id', userIds);

      if (profilesError) throw profilesError;

      // Create a map of user_id to username for quick lookup
      const profilesMap = (profilesData || []).reduce((acc, profile) => {
        acc[profile.user_id] = profile.username;
        return acc;
      }, {} as Record<string, string>);

      // Join the data manually
      const withdrawalsWithProfiles: WithdrawalWithProfile[] = withdrawalsData.map(withdrawal => ({
        ...withdrawal,
        username: profilesMap[withdrawal.user_id] || 'Unknown User'
      }));

      return withdrawalsWithProfiles;
    },
  });

  // Fetch pending deposits and join with profiles manually
  const { data: deposits } = useQuery<DepositWithProfile[]>({
    queryKey: ['admin', 'deposits'],
    queryFn: async () => {
      // First get pending deposits
      const { data: depositsData, error: depositsError } = await supabase
        .from('deposits')
        .select('*')
        .eq('status', 'pending')
        .order('created_at', { ascending: false });

      if (depositsError) throw depositsError;
      if (!depositsData || depositsData.length === 0) return [];

      // Get user IDs from deposits
      const userIds = depositsData.map(deposit => deposit.user_id);

      // Get profiles for those users
      const { data: profilesData, error: profilesError } = await supabase
        .from('profiles')
        .select('user_id, username')
        .in('user_id', userIds);

      if (profilesError) throw profilesError;

      // Create a map of user_id to username for quick lookup
      const profilesMap = (profilesData || []).reduce((acc, profile) => {
        acc[profile.user_id] = profile.username;
        return acc;
      }, {} as Record<string, string>);

      // Join the data manually
      const depositsWithProfiles: DepositWithProfile[] = depositsData.map(deposit => ({
        ...deposit,
        username: profilesMap[deposit.user_id] || 'Unknown User'
      }));

      return depositsWithProfiles;
    },
  });

  // Process deposit mutation
  const processDeposit = useMutation({
    mutationFn: async ({ depositId, approve }: { depositId: string; approve: boolean }) => {
      const { error } = await supabase
        .from('deposits')
        .update({
          status: approve ? 'approved' : 'rejected',
          processed_at: new Date().toISOString(),
        })
        .eq('id', depositId);

      if (error) throw error;

      // Get deposit details
      const { data: deposit } = await supabase
        .from('deposits')
        .select('user_id, amount')
        .eq('id', depositId)
        .single();

      if (deposit) {
        if (approve) {
          // Update user's PHP balance
          const { error: balanceError } = await supabase.rpc('update_user_balance', {
            p_user_id: deposit.user_id,
            p_php_change: deposit.amount,
          });

          if (balanceError) throw balanceError;

          // Track quest progress for deposit
          const { error: questError } = await supabase.rpc('update_quest_progress', {
            p_user_id: deposit.user_id,
            p_activity_type: 'deposit',
            p_activity_value: deposit.amount,
            p_game_type: null,
            p_metadata: { status: 'approved' }
          });

          if (questError) {
            console.error('Error tracking deposit quest:', questError);
          }
        }

        // Create notification
        await supabase
          .from('deposit_notifications')
          .insert({
            user_id: deposit.user_id,
            deposit_id: depositId,
            message: approve 
              ? `Your deposit of ₱${deposit.amount.toFixed(2)} has been approved and added to your account.`
              : `Your deposit of ₱${deposit.amount.toFixed(2)} has been rejected. Please contact support for assistance.`
          });
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'deposits'] });
      toast({
        title: "Deposit processed",
        description: "The deposit has been processed successfully.",
      });
    },
  });

  // Process withdrawal mutation
  const processWithdrawal = useMutation({
    mutationFn: async ({ withdrawalId, approve, response }: { withdrawalId: string; approve: boolean; response?: string }) => {
      const { error } = await supabase
        .from('withdrawals')
        .update({
          status: approve ? 'approved' : 'rejected',
          admin_response: response || null,
          processed_at: new Date().toISOString(),
        })
        .eq('id', withdrawalId);

      if (error) throw error;

      // Get withdrawal details
      const { data: withdrawal } = await supabase
        .from('withdrawals')
        .select('user_id, amount')
        .eq('id', withdrawalId)
        .single();

      if (withdrawal) {
        if (approve) {
          // Deduct balance from user
          await supabase.rpc('update_user_balance', {
            p_user_id: withdrawal.user_id,
            p_php_change: -withdrawal.amount,
          });

          // Track withdrawal quest progress
          const { error: questError } = await supabase.rpc('update_quest_progress', {
            p_user_id: withdrawal.user_id,
            p_activity_type: 'withdraw',
            p_activity_value: withdrawal.amount,
            p_game_type: null,
            p_metadata: { status: 'approved', admin_processed: true }
          });

          if (questError) {
            console.error('Error tracking withdrawal quest progress:', questError);
          }
        }

        // Create notification
        await supabase
          .from('withdrawal_notifications')
          .insert({
            user_id: withdrawal.user_id,
            withdrawal_id: withdrawalId,
            message: approve 
              ? `Your withdrawal of ₱${withdrawal.amount.toFixed(2)} has been approved and processed.`
              : `Your withdrawal of ₱${withdrawal.amount.toFixed(2)} has been rejected. ${response || 'No reason provided.'}`
          });
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'withdrawals'] });
      queryClient.invalidateQueries({ queryKey: ['profile'] });
      toast({
        title: "Withdrawal processed",
        description: "The withdrawal has been processed successfully.",
      });
    },
  });

  // Process appeal mutation
  const processAppeal = useMutation({
    mutationFn: async ({ appealId, approve, response }: { appealId: string; approve: boolean; response?: string }) => {
      const { error } = await supabase
        .from('appeals')
        .update({
          status: approve ? 'approved' : 'rejected',
          admin_response: response || null,
          updated_at: new Date().toISOString(),
        })
        .eq('id', appealId);

      if (error) throw error;

      if (approve) {
        // Get appeal details to unban user
        const { data: appeal } = await supabase
          .from('appeals')
          .select('user_id')
          .eq('id', appealId)
          .single();

        if (appeal) {
          // Unban the user
          await supabase
            .from('profiles')
            .update({ 
              is_banned: false, 
              ban_reason: null 
            })
            .eq('user_id', appeal.user_id);
        }
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'appeals'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
      toast({
        title: "Appeal processed",
        description: "The appeal has been processed successfully.",
      });
    },
  });

  // Ban/unban user mutation
  const toggleUserBan = useMutation({
    mutationFn: async ({ userId, banned }: { userId: string; banned: boolean }) => {
      const { error } = await supabase
        .from('profiles')
        .update({ 
          is_banned: banned,
          ban_reason: banned ? "Banned by admin" : null
        })
        .eq('user_id', userId);

      if (error) throw error;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
      toast({
        title: "User status updated",
        description: "The user's ban status has been updated.",
      });
    },
  });

  // Delete user mutation
  const deleteUser = useMutation({
    mutationFn: async ({ userId }: { userId: string }) => {
      try {
        const { data, error } = await supabase.rpc('admin_delete_user', {
          target_user_id: userId
        });

        if (error) {
          console.error('Supabase RPC error:', error);
          throw new Error(`Database error: ${error.message}`);
        }

        console.log('Delete user response:', data);
        return data as { success: boolean; error?: string; error_detail?: string; message?: string; user_id?: string };
      } catch (error) {
        console.error('Delete user error:', error);
        throw error;
      }
    },
    onSuccess: (data) => {
      console.log('Delete user success:', data);

      if (data?.success) {
        queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
        queryClient.invalidateQueries({ queryKey: ['admin', 'deposits'] });
        queryClient.invalidateQueries({ queryKey: ['admin', 'withdrawals'] });
        queryClient.invalidateQueries({ queryKey: ['admin', 'appeals'] });

        toast({
          title: "User deleted successfully",
          description: `User and all associated data have been permanently deleted.`,
        });
      } else {
        console.error('Delete user failed:', data);
        toast({
          title: "Error deleting user",
          description: data?.error || data?.error_detail || "An error occurred while deleting the user.",
          variant: "destructive",
        });
      }
    },
    onError: (error: Error) => {
      console.error('Delete user mutation error:', error);
      toast({
        title: "Error deleting user",
        description: error?.message || "Failed to delete user. Please try again.",
        variant: "destructive",
      });
    },
  });

  // Give custom amounts mutation
  const giveCustomAmounts = useMutation({
    mutationFn: async ({ 
      userIds, 
      phpAmount, 
      coinsAmount, 
      itlogAmount 
    }: { 
      userIds: string[]; 
      phpAmount: number; 
      coinsAmount: number; 
      itlogAmount: number; 
    }) => {
      const results = [];

      for (const userId of userIds) {
        const { error } = await supabase.rpc('update_user_balance', {
          p_user_id: userId,
          p_php_change: phpAmount,
          p_coins_change: coinsAmount,
          p_itlog_change: itlogAmount,
        });

        if (error) {
          results.push({ userId, success: false, error: error.message });
        } else {
          results.push({ userId, success: true });
        }
      }

      return results;
    },
    onSuccess: (results) => {
      const successCount = results.filter(r => r.success).length;
      const failureCount = results.filter(r => !r.success).length;

      // Invalidate all relevant queries to update UI immediately
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });

      // Invalidate profile queries for all affected users to update navbar and other components
      results.forEach(result => {
        if (result.success) {
          queryClient.invalidateQueries({ queryKey: ['profile', result.userId] });
        }
      });

      // Also invalidate the general profile query to ensure current user's data is fresh
      queryClient.invalidateQueries({ queryKey: ['profile'] });

      // Invalidate any wallet-related queries
      queryClient.invalidateQueries({ queryKey: ['wallet'] });

      toast({
        title: "Custom amounts distributed",
        description: `Successfully updated ${successCount} users${failureCount > 0 ? `, ${failureCount} failed` : ''}. Balances have been updated.`,
      });
    },
  });

  // Reset all balances mutation
  const resetAllBalances = useMutation({
    mutationFn: async ({ balanceType }: { balanceType: 'php' | 'coins' | 'itlog' | 'all' }) => {
      console.log('Attempting to reset balances for type:', balanceType);

      let result;

      try {
        switch (balanceType) {
          case 'php':
            result = await supabase.rpc('reset_all_php_balances');
            break;
          case 'coins':
            result = await supabase.rpc('reset_all_coins');
            break;
          case 'itlog':
            result = await supabase.rpc('reset_all_itlog_tokens');
            break;
          case 'all':
            result = await supabase.rpc('reset_all_balances');
            break;
          default:
            throw new Error('Invalid balance type');
        }

        console.log('Reset result details:', {
          data: result.data,
          error: result.error,
          status: result.status,
          statusText: result.statusText
        });

        const { data, error } = result;

        if (error) {
          console.error('Supabase RPC error details:', {
            message: error.message,
            details: error.details,
            hint: error.hint,
            code: error.code
          });
          throw new Error(`Database error: ${error.message}${error.details ? ` - ${error.details}` : ''}`);
        }

        if (data === false) {
          throw new Error('Reset operation failed - the database function returned false. Check server logs for details.');
        }

        if (data !== true) {
          console.warn('Unexpected response from reset function:', data);
        }

        return { success: true, balanceType, result: data };
      } catch (error) {
        console.error('Reset balances error:', error);
        throw error;
      }
    },
    onSuccess: (result, variables) => {
      console.log('Reset balances success:', result);

      // Invalidate queries to refresh data
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
      queryClient.invalidateQueries({ queryKey: ['profile'] });

      const balanceTypeMap = {
        'php': 'PHP balances',
        'coins': 'Coins',
        'itlog': 'ITLOG tokens',
        'all': 'all balances'
      };

      toast({
        title: "Balances reset successfully",
        description: `All user ${balanceTypeMap[variables.balanceType]} have been reset to zero.`,
      });
    },
    onError: (error: Error) => {
      console.error('Reset balances mutation error:', error);

      let errorMessage = "Failed to reset balances. Please try again.";

      if (error.message.includes('function') && error.message.includes('does not exist')) {
        errorMessage = "Reset function not found. Please ensure database migrations are applied.";
      } else if (error.message.includes('Database error')) {
        errorMessage = "Database error occurred. Please check server logs.";
      } else if (error.message) {
        errorMessage = error.message;
      }

      toast({
        title: "Error resetting balances",
        description: errorMessage,
        variant: "destructive",
      });
    },
  });

  // Clear user data mutation
  const clearUserData = useMutation({
    mutationFn: async ({ userId }: { userId: string }) => {
      console.log('Clearing user data for:', userId);

      try {
        const { data, error } = await supabase.rpc('clear_user_data', { 
          p_user_id: userId 
        });

        console.log('Clear user data response:', { data, error });

        if (error) {
          console.error('Supabase RPC error:', error);
          throw new Error(`Database error: ${error.message || error.details || 'Unknown database error'}`);
        }

        // Check if the operation was successful
        if (data === true) {
          return { success: true, message: 'User data cleared successfully' };
        } else if (data === false) {
          throw new Error('Failed to clear user data - user may not exist or operation failed');
        } else {
          throw new Error('Unexpected response from clear user data operation');
        }
      } catch (error) {
        console.error('Clear user data error:', error);
        throw error;
      }
    },
    onSuccess: (data) => {
      console.log('Clear user data success:', data);

      // Invalidate all relevant queries to refresh the data
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'deposits'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'withdrawals'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'appeals'] });

      toast({
        title: "User data cleared successfully",
        description: data?.message || "All user progress and data have been cleared successfully.",
      });
    },
    onError: (error: Error) => {
      console.error('Clear user data mutation error:', error);

      // Provide more specific error messages based on the error
      let errorMessage = "Failed to clear user data. Please try again.";

      if (error.message.includes('user may not exist')) {
        errorMessage = "User not found or may have already been deleted.";
      } else if (error.message.includes('Database error')) {
        errorMessage = "Database error occurred. Please check server logs.";
      } else if (error.message) {
        errorMessage = error.message;
      }

      toast({
        title: "Error clearing user data",
        description: errorMessage,
        variant: "destructive",
      });
    },
  });

  return (
    <Layout>
      <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5">
        {/* Animated Background Elements */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-red-500/5 rounded-full blur-3xl animate-float"></div>
          <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-orange-500/5 rounded-full blur-3xl animate-bounce-gentle"></div>
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-red-500/3 to-orange-500/3 rounded-full blur-3xl"></div>
        </div>

        <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
          {/* Hero Section */}
          <div className="text-center mb-8 sm:mb-12">
            <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
              <Shield className="w-5 h-5 text-red-400" />
              <span className="text-sm font-medium text-gradient">Administrative Control</span>
            </div>

            <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black mb-4">
              <span className="bg-gradient-to-r from-red-400 via-orange-400 to-yellow-400 bg-clip-text text-transparent">
                Admin Dashboard
              </span>
            </h1>

            <p className="text-lg sm:text-xl text-muted-foreground max-w-2xl mx-auto">
              Comprehensive platform management with advanced user controls and system monitoring.
            </p>
          </div>

          {/* Main Content Tabs */}
          <Tabs defaultValue="users" className="space-y-8">
            <TabsList className="grid w-full grid-cols-2 lg:grid-cols-7 h-auto gap-2 p-2 bg-card/50 backdrop-blur-sm border border-primary/20 rounded-2xl">
              <TabsTrigger 
                value="users" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-blue-500 data-[state=active]:to-cyan-500 data-[state=active]:text-white transition-all duration-300"
              >
                <Users className="w-4 h-4 mr-1 sm:mr-2" />
                <span className="hidden sm:inline">User Management</span>
                <span className="sm:hidden">Users</span>
              </TabsTrigger>
              <TabsTrigger 
                value="deposits" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-green-500 data-[state=active]:to-emerald-500 data-[state=active]:text-white transition-all duration-300"
              >
                <CreditCard className="w-4 h-4 mr-1 sm:mr-2" />
                <span className="hidden sm:inline">Deposits</span>
                <span className="sm:hidden">Deposits</span>
              </TabsTrigger>
              <TabsTrigger 
                value="withdrawals" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-purple-500 data-[state=active]:to-pink-500 data-[state=active]:text-white transition-all duration-300"
              >
                <TrendingUp className="w-4 h-4 mr-1 sm:mr-2" />
                <span className="hidden sm:inline">Withdrawals</span>
                <span className="sm:hidden">Withdrawals</span>
              </TabsTrigger>
              <TabsTrigger 
                value="appeals" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-yellow-500 data-[state=active]:to-orange-500 data-[state=active]:text-white transition-all duration-300"
              >
                <MessageSquare className="w-4 h-4 mr-1 sm:mr-2" />
                Appeals
              </TabsTrigger>
              <TabsTrigger 
                value="analytics" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-indigo-500 data-[state=active]:to-purple-500 data-[state=active]:text-white transition-all duration-300"
              >
                <BarChart3 className="w-4 h-4 mr-1 sm:mr-2" />
                Analytics
              </TabsTrigger>
              <TabsTrigger 
                value="giveaway" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-pink-500 data-[state=active]:to-rose-500 data-[state=active]:text-white transition-all duration-300"
              >
                <DollarSign className="w-4 h-4 mr-1 sm:mr-2" />
                <span className="hidden sm:inline">Custom</span>
                <span className="sm:hidden">Custom</span>
              </TabsTrigger>
              <TabsTrigger 
                value="danger" 
                className="flex items-center justify-center px-3 py-4 rounded-xl text-xs sm:text-sm font-semibold data-[state=active]:bg-gradient-to-r data-[state=active]:from-red-500 data-[state=active]:to-pink-500 data-[state=active]:text-white transition-all duration-300"
              >
                <AlertTriangle className="w-4 h-4 mr-1 sm:mr-2" />
                <span className="hidden sm:inline">Danger</span>
                <span className="sm:hidden">Danger</span>
              </TabsTrigger>
            </TabsList>

            <TabsContent value="users" className="space-y-6">
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center">
                      <div className="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center mr-4">
                        <Users className="w-6 h-6 text-white" />
                      </div>
                      <div>
                        <h2 className="text-2xl font-bold">User Management</h2>
                        <p className="text-sm text-muted-foreground">Manage user accounts and permissions</p>
                      </div>
                    </div>
                    <div className="flex items-center space-x-4">
                      <span className="text-sm text-muted-foreground">
                        {selectedUsers.length} selected
                      </span>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          if (selectedUsers.length === users?.length) {
                            setSelectedUsers([]);
                          } else {
                            setSelectedUsers(users?.map(user => user.user_id) || []);
                          }
                        }}
                        className="border-blue-500/30 text-blue-400 hover:bg-blue-500/10"
                      >
                        {selectedUsers.length === users?.length ? 'Deselect All' : 'Select All'}
                      </Button>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {users?.map((user) => (
                      <div key={user.id} className="glass rounded-2xl p-6 border border-primary/20 hover:border-primary/40 transition-all duration-300">
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-4 lg:space-y-0">
                          <div className="flex items-start lg:items-center space-x-4 flex-1">
                            <input
                              type="checkbox"
                              checked={selectedUsers.includes(user.user_id)}
                              onChange={(e) => {
                                if (e.target.checked) {
                                  setSelectedUsers([...selectedUsers, user.user_id]);
                                } else {
                                  setSelectedUsers(selectedUsers.filter(id => id !== user.user_id));
                                }
                              }}
                              className="w-5 h-5 mt-1 lg:mt-0 rounded"
                            />
                            <div className="flex-1 min-w-0">
                              <div className="flex flex-col lg:flex-row lg:items-center lg:space-x-6">
                                <div className="mb-3 lg:mb-0">
                                  <p className="font-bold text-lg">{user.username}</p>
                                  <p className="text-sm text-muted-foreground font-mono">{user.wallet_id}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                  {user.is_admin && <Badge className="bg-gradient-to-r from-purple-500 to-pink-500 text-white">Admin</Badge>}
                                  {user.is_banned && <Badge variant="destructive">Banned</Badge>}
                                  {user.is_suspended && <Badge variant="outline" className="border-yellow-500 text-yellow-400">Suspended</Badge>}
                                  <Badge variant="secondary" className="bg-blue-500/20 text-blue-300">
                                    ₱{Number(user.php_balance).toFixed(2)}
                                  </Badge>
                                </div>
</div>
                            </div>
                          </div>
                          <div className="flex space-x-2 lg:space-x-3">
                            <Button
                              variant={user.is_banned ? "outline" : "destructive"}
                              size="sm"
                              className={user.is_banned ? "border-green-500 text-green-400 hover:bg-green-500/10" : ""}
                              onClick={() => toggleUserBan.mutate({ 
                                userId: user.user_id, 
                                banned: !user.is_banned 
                              })}
                            >
                              {user.is_banned ? 'Unban' : 'Ban'}
                            </Button>
                            <AlertDialog>
                              <AlertDialogTrigger asChild>
                                <Button
                                  variant="outline"
                                  size="sm"
                                  className="border-orange-500 text-orange-400 hover:bg-orange-500/10"
                                >
                                  <UserX className="w-4 h-4 mr-1" />
                                  Clear
                                </Button>
                              </AlertDialogTrigger>
                              <AlertDialogContent className="mx-4 max-w-lg">
                                <AlertDialogHeader>
                                  <AlertDialogTitle>Clear User Data</AlertDialogTitle>
                                  <AlertDialogDescription>
                                    Are you sure you want to clear all data and progress for user "{user.username}"? 
                                    This will remove all balances, game history, quest progress, pet collection, and transaction history.
                                    <br /><br />
                                    <strong>This action cannot be undone.</strong>
                                  </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                                  <AlertDialogAction
                                    onClick={() => clearUserData.mutate({ userId: user.user_id })}
                                    className="bg-orange-600 hover:bg-orange-700"
                                  >
                                    Clear Data
                                  </AlertDialogAction>
                                </AlertDialogFooter>
                              </AlertDialogContent>
                            </AlertDialog>
                            <AlertDialog>
                              <AlertDialogTrigger asChild>
                                <Button
                                  variant="destructive"
                                  size="sm"
                                  className="bg-red-600 hover:bg-red-700"
                                >
                                  <Trash2 className="w-4 h-4 mr-1" />
                                  Delete
                                </Button>
                              </AlertDialogTrigger>
                              <AlertDialogContent className="mx-4 max-w-lg">
                                <AlertDialogHeader>
                                  <AlertDialogTitle>Delete User Account</AlertDialogTitle>
                                  <AlertDialogDescription>
                                    Are you sure you want to permanently delete user "{user.username}"? 
                                    This action will remove all user data including profile, wallet balance, deposits, withdrawals, and game activity.
                                    <br /><br />
                                    <strong>This action cannot be undone.</strong>
                                  </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                                  <AlertDialogAction
                                    onClick={() => deleteUser.mutate({ userId: user.user_id })}
                                    className="bg-red-600 hover:bg-red-700"
                                  >
                                    Delete Permanently
                                  </AlertDialogAction>
                                </AlertDialogFooter>
                              </AlertDialogContent>
                            </AlertDialog>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="deposits" className="space-y-6">
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center">
                    <div className="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center mr-4">
                      <CreditCard className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Pending Deposits</h2>
                      <p className="text-sm text-muted-foreground">Review and approve deposit requests</p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {deposits?.map((deposit) => (
                      <div key={deposit.id} className="glass rounded-2xl p-6 border border-primary/20 hover:border-primary/40 transition-all duration-300">
                        <div className="flex flex-col lg:flex-row lg:items-center justify-between space-y-4 lg:space-y-0">
                          <div className="flex-1">
                            <div className="flex items-center space-x-3 mb-2">
                              <p className="font-bold text-lg">{deposit.username}</p>
                              <Badge className="bg-gradient-to-r from-green-500 to-emerald-500 text-white">
                                ₱{Number(deposit.amount).toFixed(2)}
                              </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground mb-1">
                              Payment Method: <span className="font-medium">{deposit.payment_method}</span>
                            </p>
                            <p className="text-xs text-muted-foreground">
                              {new Date(deposit.created_at).toLocaleString()}
                            </p>
                          </div>
                          <div className="flex space-x-3">
                            <Button
                              variant="outline"
                              size="sm"
                              className="border-red-500 text-red-400 hover:bg-red-500/10"
                              onClick={() => processDeposit.mutate({ 
                                depositId: deposit.id, 
                                approve: false 
                              })}
                            >
                              Reject
                            </Button>
                            <Button
                              size="sm"
                              className="modern-button bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white"
                              onClick={() => processDeposit.mutate({ 
                                depositId: deposit.id, 
                                approve: true 
                              })}
                            >
                              Approve
                            </Button>
                          </div>
                        </div>
                      </div>
                    ))}
                    {!deposits?.length && (
                      <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gradient-to-r from-green-500/20 to-emerald-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                          <CreditCard className="w-8 h-8 text-green-400" />
                        </div>
                        <p className="text-lg text-muted-foreground">No pending deposits</p>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="withdrawals" className="space-y-6">
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center">
                    <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mr-4">
                      <TrendingUp className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Pending Withdrawals</h2>
                      <p className="text-sm text-muted-foreground">Process withdrawal requests</p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-6">
                    {withdrawals?.map((withdrawal) => (
                      <div key={withdrawal.id} className="glass rounded-2xl p-6 border border-primary/20 hover:border-primary/40 transition-all duration-300 space-y-4">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center space-x-3">
                            <p className="font-bold text-lg">{withdrawal.username}</p>
                            <Badge className="bg-gradient-to-r from-purple-500 to-pink-500 text-white">
                              ₱{Number(withdrawal.amount).toFixed(2)}
                            </Badge>
                          </div>
                          <p className="text-xs text-muted-foreground">
                            {new Date(withdrawal.created_at).toLocaleString()}
                          </p>
                        </div>

                        <div className="glass rounded-xl p-4 bg-gradient-to-r from-blue-500/5 to-purple-500/5">
                          <p className="text-sm font-medium mb-2 text-blue-400">Bank Details:</p>
                          <div className="space-y-1 text-sm text-muted-foreground">
                            <p>Bank: <span className="font-medium">{withdrawal.bank_name}</span></p>
                            <p>Account Name: <span className="font-medium">{withdrawal.bank_account_name}</span></p>
                            <p>Account Number: <span className="font-mono">{withdrawal.bank_account_number}</span></p>
                          </div>
                        </div>

                        {withdrawal.status === 'pending' && (
                          <div className="flex space-x-3">
                            <Button
                              variant="outline"
                              size="sm"
                              className="border-red-500 text-red-400 hover:bg-red-500/10 flex-1"
                              onClick={() => processWithdrawal.mutate({ 
                                withdrawalId: withdrawal.id, 
                                approve: false,
                                response: "Withdrawal request rejected by admin."
                              })}
                            >
                              Reject
                            </Button>
                            <Button
                              size="sm"
                              className="modern-button bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white flex-1"
                              onClick={() => processWithdrawal.mutate({ 
                                withdrawalId: withdrawal.id, 
                                approve: true,
                                response: "Withdrawal approved and processed."
                              })}
                            >
                              Approve & Process
                            </Button>
                          </div>
                        )}
                      </div>
                    ))}
                    {!withdrawals?.length && (
                      <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gradient-to-r from-purple-500/20 to-pink-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                          <TrendingUp className="w-8 h-8 text-purple-400" />
                        </div>
                        <p className="text-lg text-muted-foreground">No pending withdrawals</p>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="appeals" className="space-y-6">
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center">
                    <div className="w-12 h-12 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-2xl flex items-center justify-center mr-4">
                      <MessageSquare className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Ban Appeals</h2>
                      <p className="text-sm text-muted-foreground">Review user appeals and ban requests</p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-6">
                    {appeals?.map((appeal) => (
                      <div key={appeal.id} className="glass rounded-2xl p-6 border border-primary/20 hover:border-primary/40 transition-all duration-300 space-y-4">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center space-x-3">
                            <div>
                              <p className="font-bold text-lg">{appeal.username}</p>
                              <p className="text-sm text-muted-foreground">{appeal.email}</p>
                            </div>
                          </div>
                          <div className="flex items-center space-x-3">
                            <Badge 
                              className={
                                appeal.status === 'pending' ? 'bg-gradient-to-r from-yellow-500 to-orange-500 text-white' :
                                appeal.status === 'approved' ? 'bg-gradient-to-r from-green-500 to-emerald-500 text-white' : 
                                'bg-gradient-to-r from-red-500 to-pink-500 text-white'
                              }
                            >
                              {appeal.status}
                            </Badge>
                            <p className="text-xs text-muted-foreground">
                              {new Date(appeal.created_at).toLocaleString()}
                            </p>
                          </div>
                        </div>

                        <div className="glass rounded-xl p-4 bg-gradient-to-r from-yellow-500/5 to-orange-500/5">
                          <p className="text-sm font-medium mb-2 text-yellow-400">Appeal Message:</p>
                          <p className="text-sm text-muted-foreground leading-relaxed">{appeal.message}</p>
                        </div>

                        {appeal.admin_response && (
                          <div className="glass rounded-xl p-4 bg-gradient-to-r from-blue-500/5 to-purple-500/5">
                            <p className="text-sm font-medium mb-2 text-blue-400">Admin Response:</p>
                            <p className="text-sm text-muted-foreground leading-relaxed">{appeal.admin_response}</p>
                          </div>
                        )}

                        {appeal.status === 'pending' && (
                          <div className="flex space-x-3">
                            <Button
                              variant="outline"
                              size="sm"
                              className="border-red-500 text-red-400 hover:bg-red-500/10 flex-1"
                              onClick={() => processAppeal.mutate({ 
                                appealId: appeal.id, 
                                approve: false,
                                response: "Appeal rejected by admin."
                              })}
                            >
                              Reject
                            </Button>
                            <Button
                              size="sm"
                              className="modern-button bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white flex-1"
                              onClick={() => processAppeal.mutate({ 
                                appealId: appeal.id, 
                                approve: true,
                                response: "Appeal approved. Account has been unbanned."
                              })}
                            >
                              Approve & Unban
                            </Button>
                          </div>
                        )}
                      </div>
                    ))}
                    {!appeals?.length && (
                      <div className="text-center py-12">
                        <div className="w-16 h-16 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                          <MessageSquare className="w-8 h-8 text-yellow-400" />
                        </div>
                        <p className="text-lg text-muted-foreground">No appeals submitted</p>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="giveaway" className="space-y-6">
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center">
                    <div className="w-12 h-12 bg-gradient-to-r from-pink-500 to-rose-500 rounded-2xl flex items-center justify-center mr-4">
                      <DollarSign className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Give Custom Amounts to Users</h2>
                      <p className="text-sm text-muted-foreground">Distribute custom balances to selected users</p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-8">
                  <div className="glass rounded-xl p-6 bg-gradient-to-r from-blue-500/10 to-cyan-500/10 border border-blue-500/20">
                    <p className="text-lg font-bold text-blue-400 mb-2">Selected Users: {selectedUsers.length}</p>
                    <p className="text-sm text-muted-foreground">
                      Select users from the "User Management" tab to distribute custom amounts.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="space-y-3">
                      <label className="text-lg font-semibold flex items-center">
                        <div className="w-6 h-6 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg mr-2"></div>
                        PHP Amount
                      </label>
                      <Input
                        type="number"
                        value={customPhpAmount}
                        onChange={(e) => setCustomPhpAmount(e.target.value)}
                        placeholder="0.00"
                        className="h-14 text-lg"
                      />
                    </div>
                    <div className="space-y-3">
                      <label className="text-lg font-semibold flex items-center">
                        <div className="w-6 h-6 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-lg mr-2"></div>
                        Coins Amount
                      </label>
                      <Input
                        type="number"
                        value={customCoinsAmount}
                        onChange={(e) => setCustomCoinsAmount(e.target.value)}
                        placeholder="0"
                        className="h-14 text-lg"
                      />
                    </div>
                    <div className="space-y-3">
                      <label className="text-lg font-semibold flex items-center">
                        <div className="w-6 h-6 itlog-token rounded-lg mr-2"></div>
                        $ITLOG Tokens
                      </label>
                      <Input
                        type="number"
                        value={customItlogAmount}
                        onChange={(e) => setCustomItlogAmount(e.target.value)}
                        placeholder="0.00000000"
                        step="0.00000001"
                        className="h-14 text-lg"
                      />
                    </div>
                  </div>

                  <Button
                    onClick={() => {
                      if (selectedUsers.length === 0) {
                        toast({
                          title: "No users selected",
                          description: "Please select at least one user from the User Management tab.",
                          variant: "destructive"
                        });
                        return;
                      }

                      giveCustomAmounts.mutate({
                        userIds: selectedUsers,
                        phpAmount: parseFloat(customPhpAmount) || 0,
                        coinsAmount: parseFloat(customCoinsAmount) || 0,
                        itlogAmount: parseFloat(customItlogAmount) || 0,
                      });
                    }}
                    disabled={selectedUsers.length === 0 || giveCustomAmounts.isPending}
                    className="w-full h-16 text-lg font-bold modern-button bg-gradient-to-r from-pink-500 to-rose-500 hover:from-pink-600 hover:to-rose-600 text-white glow-pink"
                  >
                    <Zap className="w-5 h-5 mr-2" />
                    {giveCustomAmounts.isPending ? 'Distributing...' : `Distribute to ${selectedUsers.length} Users`}
                  </Button>

                  <div className="glass rounded-xl p-4 bg-gradient-to-r from-yellow-500/5 to-orange-500/5 border border-yellow-500/20">
                    <div className="text-sm text-muted-foreground space-y-1">
                      <p>• Positive amounts will be added to user balances</p>
                      <p>• Negative amounts will be deducted from user balances</p>
                      <p>• Leave fields empty or enter 0 to skip that currency</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="danger" className="space-y-6">
              <Card className="modern-card bg-gradient-to-br from-red-500/10 to-pink-500/10 border-red-500/30">
                <CardHeader>
                  <CardTitle className="flex items-center text-red-400">
                    <div className="w-12 h-12 bg-gradient-to-r from-red-500 to-pink-500 rounded-2xl flex items-center justify-center mr-4">
                      <AlertTriangle className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Danger Zone</h2>
                      <p className="text-sm text-red-300">Irreversible system-wide operations</p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-8">
                  <div className="glass rounded-xl p-6 bg-gradient-to-r from-red-500/10 to-pink-500/10 border border-red-500/20">
                    <p className="text-red-400 font-bold text-lg mb-2">⚠️ WARNING</p>
                    <p className="text-red-300 text-sm">
                      These actions are irreversible and will affect ALL users! Proceed with extreme caution.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div className="space-y-6">
                      <h3 className="text-xl font-bold">Reset User Balances</h3>

                      <div className="space-y-4">
                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button variant="destructive" className="w-full h-14 text-lg">
                              Reset All PHP Balances
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Reset All PHP Balances</AlertDialogTitle>
                              <AlertDialogDescription>
                                This will set ALL users' PHP balance to ₱0.00. This action cannot be undone.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancel</AlertDialogCancel>
                              <AlertDialogAction
                                onClick={() => resetAllBalances.mutate({ balanceType: 'php' })}
                                className="bg-red-600 hover:bg-red-700"
                              >
                                Reset All PHP
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>

                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button variant="destructive" className="w-full h-14 text-lg">
                              Reset All Coins
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Reset All Coins</AlertDialogTitle>
                              <AlertDialogDescription>
                                This will set ALL users' coins balance to 0. This action cannot be undone.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancel</AlertDialogCancel>
                              <AlertDialogAction
                                onClick={() => resetAllBalances.mutate({ balanceType: 'coins' })}
                                className="bg-red-600 hover:bg-red-700"
                              >
                                Reset All Coins
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>

                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button variant="destructive" className="w-full h-14 text-lg">
                              Reset All $ITLOG Tokens
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Reset All $ITLOG Tokens</AlertDialogTitle>
                              <AlertDialogDescription>
                                This will set ALL users' $ITLOG token balance to 0. This action cannot be undone.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Cancel</AlertDialogCancel>
                              <AlertDialogAction
                                onClick={() => resetAllBalances.mutate({ balanceType: 'itlog' })}
                                className="bg-red-600 hover:bg-red-700"
                              >
                                Reset All $ITLOG
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      </div>
                    </div>

                    <div className="space-y-6">
                      <h3 className="text-xl font-bold text-red-400">Nuclear Options</h3>

                      <AlertDialog>
                        <AlertDialogTrigger asChild>
                          <Button variant="destructive" className="w-full h-16 text-lg bg-red-700 hover:bg-red-800 font-bold">
                            RESET ALL BALANCES
                          </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>Reset ALL User Balances</AlertDialogTitle>
                            <AlertDialogDescription>
                              This will set ALL users' PHP, Coins, and $ITLOG balances to 0. 
                              <br /><br />
                              <strong className="text-red-500">This is a nuclear option and cannot be undone!</strong>
                            </AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                              onClick={() => resetAllBalances.mutate({ balanceType: 'all' })}
                              className="bg-red-700 hover:bg-red-800"
                            >
                              RESET EVERYTHING
                            </AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>

                      <div className="glass rounded-xl p-4 bg-gradient-to-r from-yellow-500/5 to-orange-500/5 border border-yellow-500/20">
                        <p className="text-yellow-400 text-sm">
                          Individual user data clearing is available in the User Management tab.
                        </p>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="analytics" className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">
                <Card className="modern-card bg-gradient-to-br from-blue-500/10 to-cyan-500/10 border-blue-500/30 glow-blue hover-lift">
                  <CardContent className="p-6 text-center">
                    <div className="w-16 h-16 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                      <Users className="w-8 h-8 text-white" />
                    </div>
                    <p className="text-sm text-muted-foreground mb-2">Total Users</p>
                    <p className="text-3xl font-black text-blue-400">{users?.length || 0}</p>
                  </CardContent>
                </Card>

                <Card className="modern-card bg-gradient-to-br from-green-500/10 to-emerald-500/10 border-green-500/30 glow-green hover-lift">
                  <CardContent className="p-6 text-center">
                    <div className="w-16 h-16 bg-gradient-to-r from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                      <CreditCard className="w-8 h-8 text-white" />
                    </div>
                    <p className="text-sm text-muted-foreground mb-2">Pending Deposits</p>
                    <p className="text-3xl font-black text-green-400">{deposits?.length || 0}</p>
                  </CardContent>
                </Card>

                <Card className="modern-card bg-gradient-to-br from-purple-500/10 to-pink-500/10 border-purple-500/30 glow-purple hover-lift">
                  <CardContent className="p-6 text-center">
                    <div className="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                      <TrendingUp className="w-8 h-8 text-white" />
                    </div>
                    <p className="text-sm text-muted-foreground mb-2">Pending Withdrawals</p>
                    <p className="text-3xl font-black text-purple-400">{withdrawals?.length || 0}</p>
                  </CardContent>
                </Card>

                <Card className="modern-card bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border-yellow-500/30 glow-gold hover-lift">
                  <CardContent className="p-6 text-center">
                    <div className="w-16 h-16 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                      <MessageSquare className="w-8 h-8 text-white" />
                    </div>
                    <p className="text-sm text-muted-foreground mb-2">Pending Appeals</p>
                    <p className="text-3xl font-black text-yellow-400">
                      {appeals?.filter(appeal => appeal.status === 'pending').length || 0}
                    </p>
                  </CardContent>
                </Card>
              </div>

              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center">
                    <div className="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-2xl flex items-center justify-center mr-4">
                      <BarChart3 className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-bold">Platform Overview</h2>
                      <p className="text-sm text-muted-foreground">Key metrics and statistics</p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div className="space-y-4">
                      <h4 className="font-bold text-lg">Total Platform Balance</h4>
                      <div className="glass rounded-xl p-6 bg-gradient-to-r from-green-500/5 to-emerald-500/5">
                        <p className="text-3xl font-black text-green-400">
                          ₱{users?.reduce((sum, user) => sum + Number(user.php_balance), 0).toFixed(2) || '0.00'}
                        </p>
                        <p className="text-sm text-muted-foreground mt-1">Combined user balances</p>
                      </div>
                    </div>
                    <div className="space-y-4">
                      <h4 className="font-bold text-lg">Platform Health</h4>
                      <div className="space-y-3">
                        <div className="flex justify-between items-center">
                          <span className="text-sm">Active Users</span>
                          <span className="font-bold text-green-400">
                            {users?.filter(user => !user.is_banned).length || 0}
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-sm">Banned Users</span>
                          <span className="font-bold text-red-400">
                            {users?.filter(user => user.is_banned).length || 0}
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-sm">Admin Users</span>
                          <span className="font-bold text-purple-400">
                            {users?.filter(user => user.is_admin).length || 0}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </Layout>
  );
};

export default Admin;
