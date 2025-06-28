import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { apiRequest } from "@/lib/queryClient";
import { useToast } from "@/hooks/use-toast";

interface DepositRequest {
  amount: string;
  paymentMethod: string;
  receipt: File | null;
}

interface WithdrawalRequest {
  amount: string;
  currency: string;
  withdrawalMethod: string;
  accountDetails: string;
}

interface ConversionRequest {
  amount: string;
  fromCurrency: string;
  toCurrency: string;
}

export function useWallet() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const depositMutation = useMutation({
    mutationFn: async (data: DepositRequest) => {
      const formData = new FormData();
      formData.append('amount', data.amount);
      formData.append('paymentMethod', data.paymentMethod);
      if (data.receipt) {
        formData.append('receipt', data.receipt);
      }

      const response = await fetch('/api/wallet/deposit', {
        method: 'POST',
        body: formData,
        credentials: 'include',
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Deposit failed');
      }

      return response.json();
    },
    onSuccess: () => {
      toast({
        title: "Deposit request submitted",
        description: "Your deposit request has been submitted for review",
      });
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
    onError: (error: any) => {
      toast({
        title: "Deposit failed",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const withdrawalMutation = useMutation({
    mutationFn: async (data: WithdrawalRequest) => {
      const response = await apiRequest('POST', '/api/wallet/withdrawal', data);
      return response.json();
    },
    onSuccess: () => {
      toast({
        title: "Withdrawal request submitted",
        description: "Your withdrawal request has been submitted for review",
      });
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
    onError: (error: any) => {
      toast({
        title: "Withdrawal failed",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const conversionMutation = useMutation({
    mutationFn: async (data: ConversionRequest) => {
      const response = await apiRequest('POST', '/api/wallet/convert', data);
      return response.json();
    },
    onSuccess: (data) => {
      toast({
        title: "Currency converted",
        description: "Your currency conversion was successful",
      });
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
    onError: (error: any) => {
      toast({
        title: "Conversion failed",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const miningClaimMutation = useMutation({
    mutationFn: async () => {
      const response = await apiRequest('POST', '/api/mining/claim');
      return response.json();
    },
    onSuccess: (data) => {
      toast({
        title: "Mining reward claimed!",
        description: `You earned ${data.bbcMined} $BBC tokens`,
      });
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
    onError: (error: any) => {
      toast({
        title: "Mining claim failed",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  return {
    deposit: depositMutation.mutate,
    withdrawal: withdrawalMutation.mutate,
    convert: conversionMutation.mutate,
    claimMining: miningClaimMutation.mutate,
    isDepositLoading: depositMutation.isPending,
    isWithdrawalLoading: withdrawalMutation.isPending,
    isConversionLoading: conversionMutation.isPending,
    isMiningClaimLoading: miningClaimMutation.isPending,
  };
}
