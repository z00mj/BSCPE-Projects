import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { getCurrentUser, login, register, logout, adminLogin, adminLogout, type User, type Admin } from "@/lib/auth";
import { useToast } from "@/hooks/use-toast";

export function useAuth() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: user, isLoading } = useQuery({
    queryKey: ['/api/auth/me'],
    queryFn: getCurrentUser,
    retry: false,
  });

  const loginMutation = useMutation({
    mutationFn: ({ username, password }: { username: string; password: string }) =>
      login(username, password),
    onSuccess: (user) => {
      queryClient.setQueryData(['/api/auth/me'], user);
      toast({
        title: "Welcome back!",
        description: `Logged in as ${user.username}`,
      });
    },
    onError: (error: any) => {
      toast({
        title: "Login failed",
        description: error.message || "Invalid credentials",
        variant: "destructive",
      });
    },
  });

  const registerMutation = useMutation({
    mutationFn: ({ username, email, password }: { username: string; email: string; password: string }) =>
      register(username, email, password),
    onSuccess: (user) => {
      queryClient.setQueryData(['/api/auth/me'], user);
      toast({
        title: "Registration successful!",
        description: `Welcome to BigBlackCoin, ${user.username}!`,
      });
    },
    onError: (error: any) => {
      toast({
        title: "Registration failed",
        description: error.message || "Registration failed",
        variant: "destructive",
      });
    },
  });

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSuccess: () => {
      queryClient.setQueryData(['/api/auth/me'], null);
      queryClient.clear();
      toast({
        title: "Logged out",
        description: "You have been logged out successfully",
      });
    },
  });

  const adminLoginMutation = useMutation({
    mutationFn: ({ username, password }: { username: string; password: string }) =>
      adminLogin(username, password),
    onSuccess: (admin) => {
      queryClient.setQueryData(['/api/admin/me'], admin);
      toast({
        title: "Admin access granted",
        description: `Welcome, ${admin.username}`,
      });
    },
    onError: (error: any) => {
      toast({
        title: "Admin login failed",
        description: error.message || "Invalid admin credentials",
        variant: "destructive",
      });
    },
  });

  const adminLogoutMutation = useMutation({
    mutationFn: adminLogout,
    onSuccess: () => {
      queryClient.setQueryData(['/api/admin/me'], null);
      queryClient.clear();
      toast({
        title: "Admin logged out",
        description: "Admin session ended",
      });
    },
  });

  return {
    user,
    isLoading,
    isAuthenticated: !!user,
    login: loginMutation.mutate,
    register: registerMutation.mutate,
    logout: logoutMutation.mutate,
    adminLogin: adminLoginMutation.mutate,
    adminLogout: adminLogoutMutation.mutate,
    isLoginLoading: loginMutation.isPending,
    isRegisterLoading: registerMutation.isPending,
    isAdminLoginLoading: adminLoginMutation.isPending,
  };
}
