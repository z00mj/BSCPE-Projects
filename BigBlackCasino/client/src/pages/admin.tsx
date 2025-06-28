import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useAuth } from "@/hooks/use-auth";
import { apiRequest } from "@/lib/queryClient";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/hooks/use-toast";
import { formatNumber, formatCurrency } from "@/lib/utils";
import { 
  Users, 
  Clock, 
  Banknote, 
  Gamepad2, 
  Shield,
  LogOut,
  Eye,
  Check,
  X,
  AlertTriangle
} from "lucide-react";

interface AdminUser {
  id: number;
  username: string;
  email: string;
  balance: string;
  bbcTokens: string;
  status: string;
  createdAt: string;
}

interface AdminDeposit {
  id: number;
  userId: number;
  amount: string;
  paymentMethod: string;
  receiptImage: string;
  status: string;
  createdAt: string;
}

interface AdminWithdrawal {
  id: number;
  userId: number;
  amount: string;
  currency: string;
  withdrawalMethod: string;
  accountDetails: string;
  status: string;
  createdAt: string;
}

interface AdminLog {
  id: number;
  adminId: number;
  action: string;
  targetUserId?: number;
  details?: string;
  createdAt: string;
}

export default function Admin() {
  const { adminLogout } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState("users");

  const { data: users, isLoading: usersLoading } = useQuery({
    queryKey: ['/api/admin/users'],
  });

  const { data: deposits, isLoading: depositsLoading } = useQuery({
    queryKey: ['/api/admin/deposits'],
  });

  const { data: withdrawals, isLoading: withdrawalsLoading } = useQuery({
    queryKey: ['/api/admin/withdrawals'],
  });

  const { data: logs, isLoading: logsLoading } = useQuery({
    queryKey: ['/api/admin/logs'],
  });

  const updateUserStatusMutation = useMutation({
    mutationFn: async ({ userId, status }: { userId: number; status: string }) => {
      const response = await apiRequest('PUT', `/api/admin/users/${userId}/status`, { status });
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/admin/users'] });
      queryClient.invalidateQueries({ queryKey: ['/api/admin/logs'] });
      toast({
        title: "User status updated",
        description: "User status has been successfully updated",
      });
    },
    onError: (error: any) => {
      toast({
        title: "Update failed",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const approveDepositMutation = useMutation({
    mutationFn: async (depositId: number) => {
      const response = await apiRequest('POST', `/api/admin/deposits/${depositId}/approve`);
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/admin/deposits'] });
      queryClient.invalidateQueries({ queryKey: ['/api/admin/logs'] });
      toast({
        title: "Deposit approved",
        description: "Deposit has been successfully approved",
      });
    },
  });

  const rejectDepositMutation = useMutation({
    mutationFn: async (depositId: number) => {
      const response = await apiRequest('POST', `/api/admin/deposits/${depositId}/reject`);
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/admin/deposits'] });
      queryClient.invalidateQueries({ queryKey: ['/api/admin/logs'] });
      toast({
        title: "Deposit rejected",
        description: "Deposit has been rejected",
      });
    },
  });

  const approveWithdrawalMutation = useMutation({
    mutationFn: async (withdrawalId: number) => {
      const response = await apiRequest('POST', `/api/admin/withdrawals/${withdrawalId}/approve`);
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/admin/withdrawals'] });
      queryClient.invalidateQueries({ queryKey: ['/api/admin/logs'] });
      toast({
        title: "Withdrawal approved",
        description: "Withdrawal has been successfully approved",
      });
    },
  });

  const rejectWithdrawalMutation = useMutation({
    mutationFn: async (withdrawalId: number) => {
      const response = await apiRequest('POST', `/api/admin/withdrawals/${withdrawalId}/reject`);
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/admin/withdrawals'] });
      queryClient.invalidateQueries({ queryKey: ['/api/admin/logs'] });
      toast({
        title: "Withdrawal rejected",
        description: "Withdrawal has been rejected",
      });
    },
  });

  const getStatusBadge = (status: string) => {
    const variants: Record<string, string> = {
      active: "status-active",
      suspended: "status-suspended",
      banned: "status-banned",
      pending: "status-pending",
      approved: "status-approved",
      rejected: "status-rejected",
    };
    
    return (
      <Badge className={`status-badge ${variants[status] || ""}`}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    );
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div className="flex items-center justify-between mb-8">
        <h2 className="text-3xl font-bold text-white">Admin Dashboard</h2>
        <div className="flex items-center space-x-4">
          <span className="text-gray-400">Logged in as:</span>
          <span className="text-casino-orange font-bold">admin</span>
          <Button 
            onClick={() => adminLogout()}
            variant="destructive"
            size="sm"
          >
            <LogOut className="w-4 h-4 mr-2" />
            Logout
          </Button>
        </div>
      </div>

      {/* Admin Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Total Users</p>
                <p className="text-2xl font-bold text-white">
                  {users?.users?.length || 0}
                </p>
              </div>
              <Users className="text-casino-orange text-2xl w-6 h-6" />
            </div>
          </CardContent>
        </Card>
        
        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Pending Deposits</p>
                <p className="text-2xl font-bold text-yellow-400">
                  {deposits?.deposits?.filter((d: AdminDeposit) => d.status === 'pending').length || 0}
                </p>
              </div>
              <Clock className="text-yellow-400 text-2xl w-6 h-6" />
            </div>
          </CardContent>
        </Card>
        
        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Total Deposits</p>
                <p className="text-2xl font-bold text-casino-gold">
                  {deposits?.deposits?.length || 0}
                </p>
              </div>
              <Banknote className="text-casino-gold text-2xl w-6 h-6" />
            </div>
          </CardContent>
        </Card>
        
        <Card className="casino-card">
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-sm">Active Users</p>
                <p className="text-2xl font-bold text-green-400">
                  {users?.users?.filter((u: AdminUser) => u.status === 'active').length || 0}
                </p>
              </div>
              <Gamepad2 className="text-green-400 text-2xl w-6 h-6" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Admin Tabs */}
      <Card className="casino-card">
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <div className="border-b border-casino-orange/30">
            <TabsList className="bg-transparent p-0 h-auto">
              <TabsTrigger 
                value="users" 
                className="data-[state=active]:border-b-2 data-[state=active]:border-casino-orange data-[state=active]:text-casino-orange rounded-none py-4 px-6"
              >
                User Management
              </TabsTrigger>
              <TabsTrigger 
                value="deposits"
                className="data-[state=active]:border-b-2 data-[state=active]:border-casino-orange data-[state=active]:text-casino-orange rounded-none py-4 px-6"
              >
                Deposit Requests
              </TabsTrigger>
              <TabsTrigger 
                value="withdrawals"
                className="data-[state=active]:border-b-2 data-[state=active]:border-casino-orange data-[state=active]:text-casino-orange rounded-none py-4 px-6"
              >
                Withdrawals
              </TabsTrigger>
              <TabsTrigger 
                value="logs"
                className="data-[state=active]:border-b-2 data-[state=active]:border-casino-orange data-[state=active]:text-casino-orange rounded-none py-4 px-6"
              >
                Audit Logs
              </TabsTrigger>
            </TabsList>
          </div>

          <TabsContent value="users" className="p-6">
            <div className="bg-casino-black rounded-lg overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-casino-dark">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Balance</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">$BBC Tokens</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-casino-dark">
                    {users?.users?.map((user: AdminUser) => (
                      <tr key={user.id}>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-white font-medium">{user.username}</div>
                          <div className="text-gray-400 text-sm">{user.email}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-casino-gold font-medium">
                          {formatCurrency(user.balance)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-casino-orange font-medium">
                          {formatNumber(user.bbcTokens, 6)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          {getStatusBadge(user.status)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                          {user.status === 'active' && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => updateUserStatusMutation.mutate({ userId: user.id, status: 'suspended' })}
                              className="text-yellow-400 border-yellow-400 hover:bg-yellow-400 hover:text-black"
                            >
                              Suspend
                            </Button>
                          )}
                          {user.status === 'suspended' && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => updateUserStatusMutation.mutate({ userId: user.id, status: 'active' })}
                              className="text-green-400 border-green-400 hover:bg-green-400 hover:text-black"
                            >
                              Activate
                            </Button>
                          )}
                          {user.status !== 'banned' && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => updateUserStatusMutation.mutate({ userId: user.id, status: 'banned' })}
                              className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
                            >
                              Ban
                            </Button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="deposits" className="p-6">
            <div className="bg-casino-black rounded-lg overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-casino-dark">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User ID</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Method</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-casino-dark">
                    {deposits?.deposits?.map((deposit: AdminDeposit) => (
                      <tr key={deposit.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-white font-medium">
                          {deposit.userId}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-casino-gold font-medium">
                          {formatCurrency(deposit.amount)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-white">
                          {deposit.paymentMethod}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-gray-400">
                          {new Date(deposit.createdAt).toLocaleDateString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          {getStatusBadge(deposit.status)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                          {deposit.receiptImage && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => window.open(deposit.receiptImage, '_blank')}
                              className="text-blue-400 border-blue-400"
                            >
                              <Eye className="w-4 h-4" />
                            </Button>
                          )}
                          {deposit.status === 'pending' && (
                            <>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => approveDepositMutation.mutate(deposit.id)}
                                className="text-green-400 border-green-400 hover:bg-green-400 hover:text-black"
                              >
                                <Check className="w-4 h-4" />
                              </Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => rejectDepositMutation.mutate(deposit.id)}
                                className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
                              >
                                <X className="w-4 h-4" />
                              </Button>
                            </>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="withdrawals" className="p-6">
            <div className="bg-casino-black rounded-lg overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-casino-dark">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User ID</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Method</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-casino-dark">
                    {withdrawals?.withdrawals?.map((withdrawal: AdminWithdrawal) => (
                      <tr key={withdrawal.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-white font-medium">
                          {withdrawal.userId}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-casino-gold font-medium">
                          {withdrawal.currency === 'coins' 
                            ? formatCurrency(withdrawal.amount)
                            : `${formatNumber(withdrawal.amount, 6)} $BBC`
                          }
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-white">
                          {withdrawal.currency.toUpperCase()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-white">
                          {withdrawal.withdrawalMethod}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          {getStatusBadge(withdrawal.status)}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                          {withdrawal.status === 'pending' && (
                            <>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => approveWithdrawalMutation.mutate(withdrawal.id)}
                                className="text-green-400 border-green-400 hover:bg-green-400 hover:text-black"
                              >
                                <Check className="w-4 h-4" />
                              </Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => rejectWithdrawalMutation.mutate(withdrawal.id)}
                                className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
                              >
                                <X className="w-4 h-4" />
                              </Button>
                            </>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="logs" className="p-6">
            <div className="bg-casino-black rounded-lg overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-casino-dark">
                    <tr>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Timestamp</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Admin</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Target User</th>
                      <th className="px-6 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Details</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-casino-dark">
                    {logs?.logs?.map((log: AdminLog) => (
                      <tr key={log.id}>
                        <td className="px-6 py-4 whitespace-nowrap text-gray-400">
                          {new Date(log.createdAt).toLocaleString()}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-white font-medium">
                          admin
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`
                            ${log.action.includes('Approved') ? 'text-green-400' : 
                              log.action.includes('Rejected') ? 'text-red-400' : 
                              log.action.includes('Suspended') || log.action.includes('Ban') ? 'text-yellow-400' :
                              'text-white'}
                          `}>
                            {log.action}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-white">
                          {log.targetUserId || '-'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-gray-400">
                          {log.details || '-'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </Card>
    </div>
  );
}
