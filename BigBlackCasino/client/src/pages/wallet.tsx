import { useState } from "react";
import { useAuth } from "@/hooks/use-auth";
import { useWallet } from "@/hooks/use-wallet";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import FileUpload from "@/components/ui/file-upload";
import { formatNumber, formatCurrency, convertBbcToCoins, convertCoinsToBbc } from "@/lib/utils";
import { 
  Coins, 
  Gem, 
  ArrowUpDown, 
  Plus, 
  Minus,
  Upload
} from "lucide-react";

export default function Wallet() {
  const { user } = useAuth();
  const { 
    deposit, 
    withdrawal, 
    convert, 
    isDepositLoading, 
    isWithdrawalLoading, 
    isConversionLoading 
  } = useWallet();

  // Deposit form state
  const [depositAmount, setDepositAmount] = useState("");
  const [depositMethod, setDepositMethod] = useState("");
  const [receiptFile, setReceiptFile] = useState<File | null>(null);

  // Withdrawal form state
  const [withdrawAmount, setWithdrawAmount] = useState("");
  const [withdrawCurrency, setWithdrawCurrency] = useState("coins");
  const [withdrawMethod, setWithdrawMethod] = useState("");
  const [accountDetails, setAccountDetails] = useState("");

  // Conversion form state
  const [convertAmount, setConvertAmount] = useState("");
  const [fromCurrency, setFromCurrency] = useState("coins");
  const [toCurrency, setToCurrency] = useState("bbc");

  const handleDeposit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!depositAmount || !depositMethod) return;
    
    deposit({
      amount: depositAmount,
      paymentMethod: depositMethod,
      receipt: receiptFile
    });

    // Reset form
    setDepositAmount("");
    setDepositMethod("");
    setReceiptFile(null);
  };

  const handleWithdrawal = (e: React.FormEvent) => {
    e.preventDefault();
    if (!withdrawAmount || !withdrawMethod || !accountDetails) return;
    
    withdrawal({
      amount: withdrawAmount,
      currency: withdrawCurrency,
      withdrawalMethod: withdrawMethod,
      accountDetails
    });

    // Reset form
    setWithdrawAmount("");
    setWithdrawMethod("");
    setAccountDetails("");
  };

  const handleConversion = (e: React.FormEvent) => {
    e.preventDefault();
    if (!convertAmount) return;
    
    convert({
      amount: convertAmount,
      fromCurrency,
      toCurrency
    });

    setConvertAmount("");
  };

  const getConvertedAmount = () => {
    if (!convertAmount) return "0.00";
    const amount = parseFloat(convertAmount);
    
    if (fromCurrency === "coins" && toCurrency === "bbc") {
      return convertCoinsToBbc(amount).toFixed(6);
    } else if (fromCurrency === "bbc" && toCurrency === "coins") {
      return convertBbcToCoins(amount).toFixed(2);
    }
    return "0.00";
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h2 className="text-3xl font-bold text-white mb-8">Wallet Management</h2>
      
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Balance Overview */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">Current Balance</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="bg-casino-black rounded-lg p-4">
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-gray-400 text-sm">Coin Balance</div>
                  <div className="text-2xl font-bold text-casino-gold">
                    {user && !isNaN(Number(user.balance)) ? formatNumber(user.balance) : "0.00"}
                  </div>
                  <div className="text-xs text-gray-400">
                    ≈ {user ? formatCurrency(user.balance) : "₱0.00"}
                  </div>
                </div>
                <Coins className="text-casino-gold text-3xl w-8 h-8" />
              </div>
            </div>
            
            <div className="bg-casino-black rounded-lg p-4">
              <div className="flex items-center justify-between">
                <div>
                  <div className="text-gray-400 text-sm">$BBC Tokens</div>
                  <div className="text-2xl font-bold text-casino-orange">
                    {user && !isNaN(Number(user.bbcTokens)) ? formatNumber(user.bbcTokens, 6) : "0.000000"}
                  </div>
                  <div className="text-xs text-gray-400">
                    ≈ {user ? formatNumber(convertBbcToCoins(parseFloat(user.bbcTokens))) : "0"} coins
                  </div>
                </div>
                <Gem className="text-casino-orange text-3xl w-8 h-8" />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Currency Conversion */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">Currency Exchange</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleConversion} className="space-y-4">
              <div>
                <Label className="text-gray-400 mb-2 block">Convert</Label>
                <div className="flex items-center gap-2">
                  <Input
                    type="number"
                    placeholder="Amount"
                    value={convertAmount}
                    onChange={(e) => setConvertAmount(e.target.value)}
                    className="flex-1 bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                    step="0.000001"
                    min="0"
                  />
                  <Select value={fromCurrency} onValueChange={setFromCurrency}>
                    <SelectTrigger className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="coins">Coins</SelectItem>
                      <SelectItem value="bbc">$BBC</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              
              <div className="text-center">
                <ArrowUpDown className="text-casino-orange text-2xl w-6 h-6 mx-auto" />
              </div>
              
              <div>
                <Label className="text-gray-400 mb-2 block">To</Label>
                <div className="flex items-center gap-2">
                  <Input
                    type="number"
                    placeholder="0.00"
                    value={getConvertedAmount()}
                    readOnly
                    className="flex-1 bg-casino-black border-gray-600 text-gray-400"
                  />
                  <Select value={toCurrency} onValueChange={setToCurrency}>
                    <SelectTrigger className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="bbc">$BBC</SelectItem>
                      <SelectItem value="coins">Coins</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              
              <Button 
                type="submit" 
                className="w-full casino-button"
                disabled={isConversionLoading || !convertAmount}
              >
                {isConversionLoading ? "Converting..." : "Convert Now"}
              </Button>
              
              <div className="text-xs text-gray-400 text-center">
                Exchange Rate: 1 $BBC = 5,000 Coins
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      {/* Transaction Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        {/* Deposit */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">
              <Plus className="inline-block w-5 h-5 text-green-400 mr-2" />
              Deposit Funds
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleDeposit} className="space-y-4">
              <div>
                <Label className="text-gray-400 mb-2 block">Amount (PHP)</Label>
                <Input
                  type="number"
                  placeholder="0.00"
                  value={depositAmount}
                  onChange={(e) => setDepositAmount(e.target.value)}
                  className="w-full bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                  step="0.01"
                  min="0"
                  required
                />
              </div>
              
              <div>
                <Label className="text-gray-400 mb-2 block">Payment Method</Label>
                <Select value={depositMethod} onValueChange={setDepositMethod}>
                  <SelectTrigger className="w-full bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange">
                    <SelectValue placeholder="Select payment method" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="gcash">GCash</SelectItem>
                    <SelectItem value="paymaya">PayMaya</SelectItem>
                    <SelectItem value="bank">Bank Transfer</SelectItem>
                    <SelectItem value="bitcoin">Bitcoin</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <div>
                <Label className="text-gray-400 mb-2 block">Upload Receipt</Label>
                <FileUpload
                  onFileSelect={setReceiptFile}
                  accept="image/*"
                  maxSize={5}
                />
              </div>
              
              <Button 
                type="submit" 
                className="w-full bg-green-500 hover:bg-green-600 text-white"
                disabled={isDepositLoading || !depositAmount || !depositMethod}
              >
                {isDepositLoading ? "Submitting..." : "Submit Deposit Request"}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Withdraw */}
        <Card className="casino-card">
          <CardHeader>
            <CardTitle className="text-xl text-white">
              <Minus className="inline-block w-5 h-5 text-red-400 mr-2" />
              Withdraw Funds
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleWithdrawal} className="space-y-4">
              <div>
                <Label className="text-gray-400 mb-2 block">Amount</Label>
                <div className="flex items-center gap-2">
                  <Input
                    type="number"
                    placeholder="0.00"
                    value={withdrawAmount}
                    onChange={(e) => setWithdrawAmount(e.target.value)}
                    className="flex-1 bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                    step="0.000001"
                    min="0"
                    required
                  />
                  <Select value={withdrawCurrency} onValueChange={setWithdrawCurrency}>
                    <SelectTrigger className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="coins">Coins</SelectItem>
                      <SelectItem value="bbc">$BBC</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              
              <div>
                <Label className="text-gray-400 mb-2 block">Withdrawal Method</Label>
                <Select value={withdrawMethod} onValueChange={setWithdrawMethod}>
                  <SelectTrigger className="w-full bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange">
                    <SelectValue placeholder="Select withdrawal method" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="gcash">GCash</SelectItem>
                    <SelectItem value="paymaya">PayMaya</SelectItem>
                    <SelectItem value="bank">Bank Transfer</SelectItem>
                    <SelectItem value="bitcoin">Bitcoin Wallet</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <div>
                <Label className="text-gray-400 mb-2 block">Account Details</Label>
                <Textarea
                  placeholder="Enter account number/wallet address"
                  value={accountDetails}
                  onChange={(e) => setAccountDetails(e.target.value)}
                  className="w-full bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange h-20 resize-none"
                  required
                />
              </div>
              
              <Button 
                type="submit" 
                className="w-full bg-red-500 hover:bg-red-600 text-white"
                disabled={isWithdrawalLoading || !withdrawAmount || !withdrawMethod || !accountDetails}
              >
                {isWithdrawalLoading ? "Requesting..." : "Request Withdrawal"}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
