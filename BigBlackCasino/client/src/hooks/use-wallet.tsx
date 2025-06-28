import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiRequest } from '@/lib/queryClient';

export function useWallet() {
  const queryClient = useQueryClient();

  const convertMutation = useMutation({
    mutationFn: async (data: { 
      amount: string; 
      fromCurrency: string; 
      toCurrency: string; 
    }) => {
      const response = await apiRequest('POST', '/api/wallet/convert', data);
      return await response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['/api/auth/me'] });
    },
  });

  const depositMutation = useMutation({
    mutationFn: async (data: { 
      amount: string; 
      paymentMethod: string; 
      receipt?: File; 
    }) => {
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
        const error = await response.text();
        throw new Error(error);
      }

      return await response.json();
    },
  });

  const withdrawMutation = useMutation({
    mutationFn: async (data: { 
      amount: string; 
      currency: string; 
      withdrawalMethod: string; 
      accountDetails: string; 
    }) => {
      const response = await apiRequest('POST', '/api/wallet/withdraw', data);
      return await response.json();
    },
  });

  return {
    convert: convertMutation.mutateAsync,
    deposit: depositMutation.mutateAsync,
    withdraw: withdrawMutation.mutateAsync,
    isConverting: convertMutation.isPending,
    isDepositing: depositMutation.isPending,
    isWithdrawing: withdrawMutation.isPending,
  };
}
