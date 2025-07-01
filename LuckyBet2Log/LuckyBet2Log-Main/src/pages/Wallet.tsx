
import { useState, useEffect } from "react";
import Layout from "@/components/Layout";
import { useBannedCheck } from "@/hooks/useBannedCheck";
import BannedOverlay from "@/components/BannedOverlay";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { 
  ArrowUpDown, 
  Wallet as WalletIcon, 
  Coins, 
  TrendingUp, 
  Send,
  Repeat,
  CreditCard,
  Shield,
  Sparkles,
  Zap,
  Target,
  DollarSign,
  RefreshCw
} from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { useProfile } from "@/hooks/useProfile";
import { useAuth } from "@/hooks/useAuth";
import { supabase } from "@/integrations/supabase/client";
import { useActivityTracker } from "@/hooks/useActivityTracker";

const Wallet = () => {
  const { isBanned } = useBannedCheck();
  const [convertAmount, setConvertAmount] = useState("");
  const [withdrawAmount, setWithdrawAmount] = useState("");
  const [bankName, setBankName] = useState("");
  const [accountName, setAccountName] = useState("");
  const [accountNumber, setAccountNumber] = useState("");
  const [withdrawalMethod, setWithdrawalMethod] = useState("bank_transfer");
  const [isSubmittingWithdrawal, setIsSubmittingWithdrawal] = useState(false);
  const { toast } = useToast();
  const { profile, updateBalance } = useProfile();
  const { user } = useAuth();
  const { trackCurrencyConversion, trackItlogExchange } = useActivityTracker();

  // Set up real-time subscription for balance updates
  useEffect(() => {
    if (!user?.id) return;

    const channelName = `wallet_balance_updates_${user.id}_${Date.now()}`;
    const channel = supabase
      .channel(channelName)
      .on(
        'postgres_changes',
        {
          event: 'UPDATE',
          schema: 'public',
          table: 'profiles',
          filter: `user_id=eq.${user.id}`,
        },
        (payload) => {
          const newProfile = payload.new;
          if (newProfile && profile) {
            const phpDiff = newProfile.php_balance - profile.php_balance;
            const coinsDiff = newProfile.coins - profile.coins;
            const itlogDiff = newProfile.itlog_tokens - profile.itlog_tokens;
            
            if (phpDiff !== 0 || coinsDiff !== 0 || itlogDiff !== 0) {
              toast({
                title: "Balance Updated",
                description: `Your balance has been updated by an administrator.${phpDiff !== 0 ? ` PHP: ${phpDiff > 0 ? '+' : ''}₱${phpDiff.toFixed(2)}` : ''}${coinsDiff !== 0 ? ` Coins: ${coinsDiff > 0 ? '+' : ''}${coinsDiff.toFixed(2)}` : ''}${itlogDiff !== 0 ? ` $ITLOG: ${itlogDiff > 0 ? '+' : ''}${itlogDiff.toFixed(4)}` : ''}`,
              });
            }
          }
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [user?.id, profile, toast]);

  if (!profile) return null;

  const handleConversion = async (from: string, to: string) => {
    const amount = parseFloat(convertAmount);
    if (!amount || amount <= 0) {
      toast({
        title: "Invalid amount",
        description: "Please enter a valid amount to convert.",
        variant: "destructive"
      });
      return;
    }

    try {
      if (from === "php" && to === "coins") {
        if (amount > profile.php_balance) {
          toast({
            title: "Insufficient balance",
            description: "You don't have enough PHP balance.",
            variant: "destructive"
          });
          return;
        }
        if (user?.id) {
          await updateBalance.mutateAsync({
            coinsChange: amount,
            phpChange: -amount
          });
        }
        trackCurrencyConversion(amount, "php", "coins");
        toast({
          title: "Conversion successful",
          description: `Converted ₱${amount.toFixed(2)} to ${amount.toFixed(2)} coins.`
        });
      } else if (from === "coins" && to === "php") {
        if (amount > profile.coins) {
          toast({
            title: "Insufficient balance",
            description: "You don't have enough coins.",
            variant: "destructive"
          });
          return;
        }
         if (user?.id) {
          await updateBalance.mutateAsync({
            coinsChange: -amount,
            phpChange: amount
          });
        }
        trackCurrencyConversion(amount, "coins", "php");
        toast({
          title: "Conversion successful",
          description: `Converted ${amount.toFixed(2)} coins to ₱${amount.toFixed(2)}.`
        });
      } else if (from === "itlog" && to === "coins") {
        if (amount > profile.itlog_tokens) {
          toast({
            title: "Insufficient balance",
            description: "You don't have enough $ITLOG tokens.",
            variant: "destructive"
          });
          return;
        }
        const coinsAmount = amount * 5000;
        if (user?.id) {
          await updateBalance.mutateAsync({
            coinsChange: coinsAmount,
            itlogChange: -amount
          });
        }
        trackItlogExchange(amount);
        toast({
          title: "Conversion successful",
          description: `Converted ${amount.toFixed(2)} $ITLOG to ${coinsAmount.toFixed(2)} coins.`
        });
      }
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to process conversion. Please try again.",
        variant: "destructive"
      });
    }

    setConvertAmount("");
  };

  const handleWithdrawalSubmit = async () => {
    const amount = parseFloat(withdrawAmount);

    if (!amount || amount <= 0) {
      toast({
        title: "Invalid amount",
        description: "Please enter a valid withdrawal amount.",
        variant: "destructive"
      });
      return;
    }

    if (amount > profile.php_balance) {
      toast({
        title: "Insufficient balance",
        description: "You don't have enough PHP balance for this withdrawal.",
        variant: "destructive"
      });
      return;
    }

    if (amount < 100) {
      toast({
        title: "Minimum withdrawal",
        description: "Minimum withdrawal amount is ₱100.",
        variant: "destructive"
      });
      return;
    }

    if (!bankName || !accountName || !accountNumber) {
      toast({
        title: "Missing information",
        description: "Please fill in all bank account details.",
        variant: "destructive"
      });
      return;
    }

    if (!user) {
      toast({
        title: "Authentication required",
        description: "Please log in to make a withdrawal.",
        variant: "destructive"
      });
      return;
    }

    setIsSubmittingWithdrawal(true);

    try {
      const { error } = await supabase
        .from('withdrawals')
        .insert({
          user_id: user.id,
          amount: amount,
          withdrawal_type: 'php',
          withdrawal_method: withdrawalMethod,
          bank_name: bankName,
          bank_account_name: accountName,
          bank_account_number: accountNumber,
          status: 'pending'
        });

      if (error) throw error;

      toast({
        title: "Withdrawal request submitted",
        description: "Your withdrawal request has been submitted for admin approval. You will be notified once processed."
      });

      // Reset form
      setWithdrawAmount("");
      setBankName("");
      setAccountName("");
      setAccountNumber("");
      setWithdrawalMethod("bank_transfer");

    } catch (error) {
      console.error('Withdrawal submission error:', error);
      toast({
        title: "Error",
        description: "Failed to submit withdrawal request. Please try again.",
        variant: "destructive"
      });
    } finally {
      setIsSubmittingWithdrawal(false);
    }
  };

  const totalPortfolioValue = Number(profile.php_balance) + Number(profile.coins) + (Number(profile.itlog_tokens) * 5000);

  const exchangeRates = [
    {
      from: "PHP",
      to: "Coins",
      rate: "1:1",
      description: "Perfect 1:1 exchange rate",
      icon: RefreshCw,
      color: "from-green-500 to-emerald-500"
    },
    {
      from: "$ITLOG",
      to: "Coins", 
      rate: "1:5,000",
      description: "Premium token exchange",
      icon: Sparkles,
      color: "from-yellow-500 to-orange-500"
    }
  ];

  return (
    <Layout>
      {isBanned && <BannedOverlay />}
      <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5">
        {/* Animated Background */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/5 rounded-full blur-3xl animate-float"></div>
          <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl animate-bounce-gentle"></div>
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-green-500/3 to-blue-500/3 rounded-full blur-3xl"></div>
        </div>

        <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          {/* Hero Section */}
          <div className="text-center mb-12">
            <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
              <WalletIcon className="w-5 h-5 text-green-400" />
              <span className="text-sm font-medium text-gradient">Digital Wallet</span>
            </div>
            
            <h1 className="text-4xl sm:text-5xl md:text-6xl font-black mb-6">
              <span className="bg-gradient-to-r from-green-400 via-blue-400 to-purple-400 bg-clip-text text-transparent">
                Your Wallet
              </span>
            </h1>
            
            <p className="text-xl text-muted-foreground max-w-3xl mx-auto mb-8">
              Manage your digital assets, convert between currencies, and withdraw your winnings with ease
            </p>

            {/* Portfolio Overview */}
            <div className="inline-flex items-center space-x-4 glass rounded-2xl px-8 py-4 border border-white/20">
              <TrendingUp className="w-6 h-6 text-blue-400" />
              <div>
                <p className="text-sm text-muted-foreground">Total Portfolio Value</p>
                <p className="text-2xl font-bold text-blue-400">₱{totalPortfolioValue.toFixed(2)}</p>
              </div>
            </div>
          </div>

          {/* Balance Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8 mb-12">
            <Card className="modern-card bg-gradient-to-br from-green-500/10 to-emerald-500/10 border-green-500/30 glow-green hover-lift">
              <CardContent className="p-4 sm:p-6 lg:p-8 text-center">
                <div className="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-r from-green-500 to-emerald-500 rounded-3xl flex items-center justify-center mx-auto mb-4 sm:mb-6 shadow-2xl">
                  <WalletIcon className="w-8 h-8 sm:w-10 sm:h-10 text-white" />
                </div>
                <h3 className="text-base sm:text-lg font-semibold text-muted-foreground mb-2">PHP Balance</h3>
                <p className="text-2xl sm:text-3xl lg:text-4xl font-black text-green-400 mb-2 break-words">₱{profile.php_balance.toFixed(2)}</p>
                <Badge variant="secondary" className="bg-green-500/20 text-green-300 text-xs sm:text-sm">Ready to withdraw</Badge>
              </CardContent>
            </Card>

            <Card className="modern-card bg-gradient-to-br from-blue-500/10 to-cyan-500/10 border-blue-500/30 glow-blue hover-lift">
              <CardContent className="p-4 sm:p-6 lg:p-8 text-center">
                <div className="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-3xl flex items-center justify-center mx-auto mb-4 sm:mb-6 shadow-2xl">
                  <Coins className="w-8 h-8 sm:w-10 sm:h-10 text-white" />
                </div>
                <h3 className="text-base sm:text-lg font-semibold text-muted-foreground mb-2">Game Coins</h3>
                <p className="text-2xl sm:text-3xl lg:text-4xl font-black text-blue-400 mb-2 break-words">{profile.coins.toFixed(2)}</p>
                <Badge variant="secondary" className="bg-blue-500/20 text-blue-300 text-xs sm:text-sm">For gaming</Badge>
              </CardContent>
            </Card>

            <Card className="modern-card bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border-yellow-500/30 glow-gold hover-lift sm:col-span-2 lg:col-span-1">
              <CardContent className="p-4 sm:p-6 lg:p-8 text-center">
                <div className="w-16 h-16 sm:w-20 sm:h-20 itlog-token rounded-3xl flex items-center justify-center mx-auto mb-4 sm:mb-6 shadow-2xl">
                  <span className="text-black font-bold text-2xl sm:text-3xl">₿</span>
                </div>
                <h3 className="text-base sm:text-lg font-semibold text-muted-foreground mb-2">$ITLOG Tokens</h3>
                <p className="text-2xl sm:text-3xl lg:text-4xl font-black text-yellow-400 mb-2 break-words">{profile.itlog_tokens.toFixed(2)}</p>
                <Badge variant="secondary" className="bg-yellow-500/20 text-yellow-300 text-xs sm:text-sm">Premium rewards</Badge>
              </CardContent>
            </Card>
          </div>

          {/* Exchange Rates Info */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-12">
            {exchangeRates.map((rate, index) => {
              const Icon = rate.icon;
              return (
                <Card key={index} className="modern-card hover-lift">
                  <CardContent className="p-4 sm:p-6">
                    <div className="flex items-center space-x-3 sm:space-x-4">
                      <div className={`w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-r ${rate.color} rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0`}>
                        <Icon className="w-6 h-6 sm:w-8 sm:h-8 text-white" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <h3 className="text-base sm:text-lg font-bold mb-1 break-words">{rate.from} → {rate.to}</h3>
                        <p className="text-xl sm:text-2xl font-black text-primary mb-1">{rate.rate}</p>
                        <p className="text-xs sm:text-sm text-muted-foreground break-words">{rate.description}</p>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>

          {/* Main Wallet Functions */}
          <Card className="modern-card hover-lift">
            <CardHeader>
              <CardTitle className="flex items-center text-2xl">
                <ArrowUpDown className="w-6 h-6 mr-3 text-purple-400" />
                Wallet Operations
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Tabs defaultValue="convert" className="space-y-6">
                <TabsList className="grid w-full grid-cols-3 bg-muted/50 p-1 rounded-xl">
                  <TabsTrigger 
                    value="convert" 
                    className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-purple-500 data-[state=active]:to-pink-500 data-[state=active]:text-white font-semibold"
                  >
                    <Repeat className="w-4 h-4 mr-2" />
                    Convert
                  </TabsTrigger>
                  <TabsTrigger 
                    value="exchange"
                    className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-yellow-500 data-[state=active]:to-orange-500 data-[state=active]:text-black font-semibold"
                  >
                    <Sparkles className="w-4 h-4 mr-2" />
                    Exchange
                  </TabsTrigger>
                  <TabsTrigger 
                    value="withdraw"
                    className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-green-500 data-[state=active]:to-emerald-500 data-[state=active]:text-white font-semibold"
                  >
                    <Send className="w-4 h-4 mr-2" />
                    Withdraw
                  </TabsTrigger>
                </TabsList>

                <TabsContent value="convert" className="space-y-6">
                  <div className="text-center p-6 bg-gradient-to-r from-purple-500/10 to-pink-500/10 rounded-2xl border border-purple-500/20">
                    <Target className="w-12 h-12 text-purple-400 mx-auto mb-4" />
                    <h3 className="text-xl font-bold mb-2">PHP ↔ Coins Exchange</h3>
                    <p className="text-muted-foreground">Perfect 1:1 exchange rate between PHP and Coins</p>
                  </div>
                  
                  <div className="space-y-6">
                    <div>
                      <Label htmlFor="convert-amount" className="text-base font-semibold">Amount to Convert</Label>
                      <Input
                        id="convert-amount"
                        type="number"
                        step="0.01"
                        placeholder="Enter amount"
                        value={convertAmount}
                        onChange={(e) => setConvertAmount(e.target.value)}
                        className="mt-2 h-12 text-lg"
                      />
                    </div>
                    
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <Button 
                        onClick={() => handleConversion("php", "coins")}
                        className="h-14 text-lg bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold glow-green"
                      >
                        <DollarSign className="w-5 h-5 mr-2" />
                        PHP → Coins
                      </Button>
                      <Button 
                        onClick={() => handleConversion("coins", "php")}
                        className="h-14 text-lg bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white font-semibold glow-blue"
                      >
                        <Coins className="w-5 h-5 mr-2" />
                        Coins → PHP
                      </Button>
                    </div>
                  </div>
                </TabsContent>

                <TabsContent value="exchange" className="space-y-6">
                  <div className="text-center p-6 bg-gradient-to-r from-yellow-500/10 to-orange-500/10 rounded-2xl border border-yellow-500/20">
                    <Sparkles className="w-12 h-12 text-yellow-400 mx-auto mb-4" />
                    <h3 className="text-xl font-bold mb-2">$ITLOG Token Exchange</h3>
                    <p className="text-muted-foreground">Convert your premium $ITLOG tokens to gaming coins</p>
                    <Badge className="mt-2 bg-gradient-to-r from-yellow-500 to-orange-500 text-black font-semibold">
                      1 $ITLOG = 5,000 Coins
                    </Badge>
                  </div>
                  
                  <div className="space-y-6">
                    <div>
                      <Label htmlFor="itlog-amount" className="text-base font-semibold">$ITLOG Amount</Label>
                      <Input
                        id="itlog-amount"
                        type="number"
                        step="0.01"
                        placeholder="Enter $ITLOG amount"
                        value={convertAmount}
                        onChange={(e) => setConvertAmount(e.target.value)}
                        className="mt-2 h-12 text-lg"
                      />
                      {convertAmount && (
                        <p className="text-sm text-muted-foreground mt-2">
                          You will receive: <span className="font-semibold text-blue-400">{(parseFloat(convertAmount) * 5000).toFixed(2)} Coins</span>
                        </p>
                      )}
                    </div>
                    
                    <Button 
                      onClick={() => handleConversion("itlog", "coins")}
                      className="w-full h-14 text-lg bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-black font-semibold glow-gold"
                    >
                      <Sparkles className="w-5 h-5 mr-2" />
                      Convert $ITLOG to Coins
                    </Button>
                  </div>
                </TabsContent>

                <TabsContent value="withdraw" className="space-y-6">
                  <div className="text-center p-6 bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-2xl border border-green-500/20">
                    <Shield className="w-12 h-12 text-green-400 mx-auto mb-4" />
                    <h3 className="text-xl font-bold mb-2">Secure Withdrawal</h3>
                    <p className="text-muted-foreground">Withdraw your PHP balance to your bank account</p>
                    <Badge className="mt-2 bg-green-500/20 text-green-300">
                      Available: ₱{profile.php_balance.toFixed(2)}
                    </Badge>
                  </div>
                  
                  <div className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <Label htmlFor="withdraw-amount" className="text-base font-semibold">Withdrawal Amount (PHP)</Label>
                        <Input
                          id="withdraw-amount"
                          type="number"
                          step="0.01"
                          min="100"
                          max={profile.php_balance}
                          placeholder="Minimum ₱100"
                          value={withdrawAmount}
                          onChange={(e) => setWithdrawAmount(e.target.value)}
                          className="mt-2 h-12 text-lg"
                        />
                      </div>

                      <div>
                        <Label htmlFor="withdrawal-method" className="text-base font-semibold">Withdrawal Method</Label>
                        <Select value={withdrawalMethod} onValueChange={setWithdrawalMethod}>
                          <SelectTrigger className="mt-2 h-12">
                            <SelectValue placeholder="Select method" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                            <SelectItem value="gcash">GCash</SelectItem>
                            <SelectItem value="paymaya">PayMaya</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                      <div>
                        <Label htmlFor="bank-name" className="text-base font-semibold">Bank/Service Name</Label>
                        <Input
                          id="bank-name"
                          placeholder="e.g., BPI, BDO, GCash"
                          value={bankName}
                          onChange={(e) => setBankName(e.target.value)}
                          className="mt-2 h-12"
                        />
                      </div>

                      <div>
                        <Label htmlFor="account-name" className="text-base font-semibold">Account Holder Name</Label>
                        <Input
                          id="account-name"
                          placeholder="Full registered name"
                          value={accountName}
                          onChange={(e) => setAccountName(e.target.value)}
                          className="mt-2 h-12"
                        />
                      </div>

                      <div>
                        <Label htmlFor="account-number" className="text-base font-semibold">Account Number</Label>
                        <Input
                          id="account-number"
                          placeholder="Account/Mobile number"
                          value={accountNumber}
                          onChange={(e) => setAccountNumber(e.target.value)}
                          className="mt-2 h-12"
                        />
                      </div>
                    </div>

                    <Button 
                      onClick={handleWithdrawalSubmit}
                      disabled={isSubmittingWithdrawal || !withdrawAmount || !bankName || !accountName || !accountNumber}
                      className="w-full h-14 text-lg bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold glow-green disabled:opacity-50"
                    >
                      {isSubmittingWithdrawal ? (
                        <>
                          <RefreshCw className="w-5 h-5 mr-2 animate-spin" />
                          Processing...
                        </>
                      ) : (
                        <>
                          <Send className="w-5 h-5 mr-2" />
                          Submit Withdrawal Request
                        </>
                      )}
                    </Button>
                  </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        </div>
      </div>
    </Layout>
  );
};

export default Wallet;
