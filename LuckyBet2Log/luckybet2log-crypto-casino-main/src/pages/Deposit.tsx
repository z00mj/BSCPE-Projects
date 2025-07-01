
import { useState } from "react";
import Layout from "@/components/Layout";
import { useBannedCheck } from "@/hooks/useBannedCheck";
import BannedOverlay from "@/components/BannedOverlay";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { CreditCard, Upload, CheckCircle, AlertTriangle, Loader2, Sparkles, Shield, Info } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { validateReceipt, ReceiptValidationResult } from "@/lib/receiptValidator";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/hooks/useAuth";

const Deposit = () => {
  const [amount, setAmount] = useState("");
  const [paymentMethod, setPaymentMethod] = useState("");
  const [receipt, setReceipt] = useState<File | null>(null);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isValidating, setIsValidating] = useState(false);
  const [validationResult, setValidationResult] = useState<ReceiptValidationResult | null>(null);
  const { isBanned, reason } = useBannedCheck();
  const { toast } = useToast();
  const { user } = useAuth();

  const predefinedAmounts = [100, 250, 500, 1000, 2500, 5000];

  const paymentMethods = [
    { value: "gcash", label: "GCash" },
    { value: "paymaya", label: "PayMaya" },
    { value: "bpi", label: "BPI Bank" },
    { value: "bdo", label: "BDO Bank" },
    { value: "unionbank", label: "Union Bank" },
    { value: "metrobank", label: "Metrobank" }
  ];

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) { // 5MB limit
        toast({
          title: "File too large",
          description: "Please upload a file smaller than 5MB.",
          variant: "destructive"
        });
        return;
      }

      setReceipt(file);
      setValidationResult(null);

      // Only validate if we have amount and payment method
      if (amount && paymentMethod) {
        await validateReceiptFile(file);
      } else {
        toast({
          title: "Receipt uploaded",
          description: "Please enter amount and select payment method to validate receipt."
        });
      }
    }
  };

  const validateReceiptFile = async (file: File) => {
    if (!amount || !paymentMethod) {
      toast({
        title: "Missing information",
        description: "Please enter amount and select payment method first.",
        variant: "destructive"
      });
      return;
    }

    setIsValidating(true);

    try {
      const result = await validateReceipt(file, parseFloat(amount), paymentMethod);
      setValidationResult(result);

      if (result.isValid) {
        toast({
          title: "Receipt validated successfully!",
          description: `Amount: ₱${result.extractedAmount}, Method: ${result.extractedMethod}`,
        });
      } else {
        toast({
          title: "Receipt validation failed",
          description: result.errors.join(', '),
          variant: "destructive"
        });
      }
    } catch (error) {
      console.error('Validation error:', error);
      toast({
        title: "Validation error",
        description: "Failed to validate receipt. Please try again.",
        variant: "destructive"
      });
    } finally {
      setIsValidating(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!amount || parseFloat(amount) <= 0) {
      toast({
        title: "Invalid amount",
        description: "Please enter a valid deposit amount.",
        variant: "destructive"
      });
      return;
    }

    if (!paymentMethod) {
      toast({
        title: "Payment method required",
        description: "Please select a payment method.",
        variant: "destructive"
      });
      return;
    }

    if (!receipt) {
      toast({
        title: "Receipt required",
        description: "Please upload your payment receipt.",
        variant: "destructive"
      });
      return;
    }

    if (!user) {
      toast({
        title: "Authentication required",
        description: "Please log in to make a deposit.",
        variant: "destructive"
      });
      return;
    }

    try {
      // Create deposit record
      const { data: depositData, error: depositError } = await supabase
        .from('deposits')
        .insert({
          user_id: user.id,
          amount: parseFloat(amount),
          payment_method: paymentMethod,
          status: 'pending'
        })
        .select()
        .single();

      if (depositError) throw depositError;

      // Save validation result if available
      if (validationResult && depositData) {
        const { error: validationError } = await supabase
          .from('receipt_validations')
          .insert({
            deposit_id: depositData.id,
            extracted_amount: validationResult.extractedAmount,
            extracted_method: validationResult.extractedMethod,
            confidence_score: validationResult.confidence,
            validation_errors: validationResult.errors,
            is_valid: validationResult.isValid
          });

        if (validationError) {
          console.error('Failed to save validation result:', validationError);
        }
      }

      setIsSubmitted(true);
      toast({
        title: "Deposit request submitted!",
        description: validationResult?.isValid 
          ? "Receipt validated successfully! Processing will be faster."
          : "Your deposit will be processed within 24 hours after verification."
      });
    } catch (error) {
      console.error('Submission error:', error);
      toast({
        title: "Submission failed",
        description: "Failed to submit deposit request. Please try again.",
        variant: "destructive"
      });
    }
  };

  if (isSubmitted) {
    return (
      <Layout>
        {isBanned && <BannedOverlay reason={reason} />}
        <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5 flex items-center justify-center">
          {/* Animated Background Elements */}
          <div className="absolute inset-0 overflow-hidden pointer-events-none">
            <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-green-500/5 rounded-full blur-3xl animate-float"></div>
            <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-emerald-500/5 rounded-full blur-3xl animate-bounce-gentle"></div>
          </div>

          <div className="relative z-10 max-w-md mx-auto px-4">
            <Card className="modern-card bg-gradient-to-br from-green-500/10 to-emerald-500/10 border-green-500/30 glow-green hover-lift">
              <CardContent className="p-8 text-center">
                <div className="w-20 h-20 bg-gradient-to-r from-green-500 to-emerald-500 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-2xl">
                  <CheckCircle className="w-10 h-10 text-white" />
                </div>
                <h2 className="text-3xl font-black mb-4 bg-gradient-to-r from-green-400 to-emerald-400 bg-clip-text text-transparent">
                  Deposit Submitted!
                </h2>
                <p className="text-muted-foreground mb-8 leading-relaxed">
                  Your deposit request of <span className="font-bold text-green-400">₱{parseFloat(amount).toFixed(2)}</span> has been submitted successfully. 
                  Our admin team will verify your receipt and process the deposit within 24 hours.
                </p>
                <Button 
                  onClick={() => {
                    setIsSubmitted(false);
                    setAmount("");
                    setPaymentMethod("");
                    setReceipt(null);
                  }}
                  className="modern-button button-primary group w-full"
                >
                  <CreditCard className="w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-300" />
                  Make Another Deposit
                  <Sparkles className="w-4 h-4 ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      {isBanned && <BannedOverlay reason={reason} />}
      <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5">
        {/* Animated Background Elements */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/5 rounded-full blur-3xl animate-float"></div>
          <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl animate-bounce-gentle"></div>
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-purple-500/3 to-blue-500/3 rounded-full blur-3xl"></div>
        </div>

        <div className="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
          {/* Hero Section */}
          <div className="text-center mb-8 sm:mb-12">
            <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
              <CreditCard className="w-5 h-5 text-purple-400" />
              <span className="text-sm font-medium text-gradient">Secure Deposits</span>
            </div>
            
            <h1 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black mb-4">
              <span className="bg-gradient-to-r from-purple-400 via-pink-400 to-gold-400 bg-clip-text text-transparent">
                Top-up / Deposit
              </span>
            </h1>
            
            <p className="text-lg sm:text-xl text-muted-foreground max-w-2xl mx-auto">
              Add funds to your account to start your winning journey with instant processing and secure transactions.
            </p>
          </div>

          {/* Main Deposit Card */}
          <Card className="modern-card hover-lift mb-8">
            <CardHeader className="pb-6">
              <CardTitle className="flex items-center text-2xl">
                <div className="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center mr-4">
                  <CreditCard className="w-6 h-6 text-white" />
                </div>
                Deposit Funds
              </CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-8">
                {/* Amount Selection */}
                <div className="space-y-6">
                  <Label htmlFor="amount" className="text-lg font-semibold">Deposit Amount (PHP)</Label>
                  <div className="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    {predefinedAmounts.map((preset) => (
                      <Button
                        key={preset}
                        type="button"
                        variant={amount === preset.toString() ? "default" : "outline"}
                        onClick={() => setAmount(preset.toString())}
                        className={`h-16 text-lg font-semibold ${
                          amount === preset.toString() 
                            ? "modern-button button-primary glow-purple" 
                            : "border-primary/30 hover:border-primary/60 hover:bg-primary/10"
                        }`}
                      >
                        ₱{preset.toLocaleString()}
                      </Button>
                    ))}
                  </div>
                  <Input
                    id="amount"
                    type="number"
                    step="0.01"
                    placeholder="Enter custom amount"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    className="h-14 text-lg"
                  />
                </div>

                {/* Payment Method */}
                <div className="space-y-4">
                  <Label htmlFor="payment-method" className="text-lg font-semibold">Payment Method</Label>
                  <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                    <SelectTrigger className="h-14 text-lg">
                      <SelectValue placeholder="Select payment method" />
                    </SelectTrigger>
                    <SelectContent>
                      {paymentMethods.map((method) => (
                        <SelectItem key={method.value} value={method.value} className="text-lg p-4">
                          {method.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Payment Instructions */}
                {paymentMethod && (
                  <Card className="modern-card bg-gradient-to-r from-blue-500/10 to-cyan-500/10 border-blue-500/30">
                    <CardContent className="p-6">
                      <div className="flex items-start space-x-3">
                        <div className="w-10 h-10 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center flex-shrink-0">
                          <Info className="w-5 h-5 text-white" />
                        </div>
                        <div>
                          <h4 className="font-bold text-blue-400 mb-3">Payment Instructions</h4>
                          <div className="space-y-2 text-sm text-muted-foreground">
                            <p>1. Send <span className="font-bold text-blue-400">₱{amount || "XX.XX"}</span> to our {paymentMethods.find(m => m.value === paymentMethod)?.label} account</p>
                            <p>2. Account Details: <span className="font-mono font-bold">09XX-XXX-XXXX</span> (LuckyBet2Log)</p>
                            <p>3. Include your username in the transaction message</p>
                            <p>4. Upload the receipt below for instant verification</p>
                          </div>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                )}

                {/* Receipt Upload */}
                <div className="space-y-4">
                  <Label htmlFor="receipt" className="text-lg font-semibold">Upload Payment Receipt</Label>
                  <div className="border-2 border-dashed border-primary/20 rounded-2xl p-8 text-center hover:border-primary/40 transition-colors duration-300">
                    <input
                      type="file"
                      id="receipt"
                      accept="image/*,.pdf"
                      onChange={handleFileUpload}
                      className="hidden"
                    />
                    <label htmlFor="receipt" className="cursor-pointer">
                      <div className="w-16 h-16 bg-gradient-to-r from-purple-500/20 to-pink-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <Upload className="w-8 h-8 text-purple-400" />
                      </div>
                      {receipt ? (
                        <div>
                          <p className="text-green-400 font-bold text-lg">{receipt.name}</p>
                          <p className="text-sm text-muted-foreground mt-1">Click to change file</p>
                        </div>
                      ) : (
                        <div>
                          <p className="text-lg font-semibold text-muted-foreground">Click to upload receipt</p>
                          <p className="text-sm text-muted-foreground mt-2">
                            Supports: JPG, PNG, PDF (Max 5MB)
                          </p>
                        </div>
                      )}
                    </label>
                  </div>

                  {/* Validation Button */}
                  {receipt && amount && paymentMethod && !validationResult && (
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => validateReceiptFile(receipt)}
                      disabled={isValidating}
                      className="w-full h-14 text-lg border-primary/30"
                    >
                      {isValidating ? (
                        <>
                          <Loader2 className="w-5 h-5 mr-2 animate-spin" />
                          Validating Receipt...
                        </>
                      ) : (
                        <>
                          <CheckCircle className="w-5 h-5 mr-2" />
                          Validate Receipt
                        </>
                      )}
                    </Button>
                  )}

                  {/* Validation Results */}
                  {validationResult && (
                    <Card className={`modern-card ${
                      validationResult.isValid 
                        ? 'bg-gradient-to-r from-green-500/10 to-emerald-500/10 border-green-500/30' 
                        : 'bg-gradient-to-r from-red-500/10 to-pink-500/10 border-red-500/30'
                    }`}>
                      <CardContent className="p-6">
                        <div className="flex items-center mb-4">
                          <div className={`w-10 h-10 rounded-xl flex items-center justify-center mr-3 ${
                            validationResult.isValid 
                              ? 'bg-gradient-to-r from-green-500 to-emerald-500' 
                              : 'bg-gradient-to-r from-red-500 to-pink-500'
                          }`}>
                            {validationResult.isValid ? (
                              <CheckCircle className="w-5 h-5 text-white" />
                            ) : (
                              <AlertTriangle className="w-5 h-5 text-white" />
                            )}
                          </div>
                          <h4 className="font-bold text-lg">
                            {validationResult.isValid ? 'Receipt Validated' : 'Validation Failed'}
                          </h4>
                        </div>
                        {validationResult.extractedAmount && (
                          <p className="text-sm mb-2">
                            Extracted Amount: <span className="font-bold">₱{validationResult.extractedAmount.toFixed(2)}</span>
                          </p>
                        )}
                        <p className="text-sm mb-2">
                          Confidence: <span className="font-bold">{validationResult.confidence.toFixed(1)}%</span>
                        </p>
                        {validationResult.errors.length > 0 && (
                          <div className="text-sm text-muted-foreground">
                            Issues: {validationResult.errors.join(', ')}
                          </div>
                        )}
                      </CardContent>
                    </Card>
                  )}
                </div>

                {/* Submit Button */}
                <Button 
                  type="submit" 
                  className={`w-full h-16 text-lg font-bold ${
                    validationResult?.isValid 
                      ? 'modern-button bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white glow-green' 
                      : 'modern-button button-primary glow-purple'
                  }`}
                  disabled={!amount || !paymentMethod || !receipt || isValidating}
                >
                  {validationResult?.isValid ? (
                    <>
                      <CheckCircle className="w-5 h-5 mr-2" />
                      Submit Validated Deposit
                      <Sparkles className="w-4 h-4 ml-2" />
                    </>
                  ) : (
                    <>
                      <CreditCard className="w-5 h-5 mr-2" />
                      Submit Deposit Request
                    </>
                  )}
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* Security & Info Cards */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Security Notice */}
            <Card className="modern-card bg-gradient-to-br from-blue-500/10 to-cyan-500/10 border-blue-500/30">
              <CardContent className="p-6">
                <div className="flex items-start space-x-3">
                  <div className="w-10 h-10 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center flex-shrink-0">
                    <Shield className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <h4 className="font-bold text-blue-400 mb-2">Security & Privacy</h4>
                    <p className="text-sm text-muted-foreground leading-relaxed">
                      Your wallet is protected with bank-level security. All transactions are encrypted and monitored for suspicious activity. 
                      We never store your sensitive banking information.
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Important Notes */}
            <Card className="modern-card">
              <CardContent className="p-6">
                <h3 className="font-bold text-lg mb-4 flex items-center">
                  <Info className="w-5 h-5 mr-2 text-yellow-400" />
                  Important Notes
                </h3>
                <ul className="text-sm text-muted-foreground space-y-2">
                  <li className="flex items-start">
                    <span className="w-1.5 h-1.5 bg-purple-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                    Minimum deposit: ₱100
                  </li>
                  <li className="flex items-start">
                    <span className="w-1.5 h-1.5 bg-purple-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                    Deposits are usually processed within 24 hours
                  </li>
                  <li className="flex items-start">
                    <span className="w-1.5 h-1.5 bg-purple-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                    Upload clear receipts for faster processing
                  </li>
                  <li className="flex items-start">
                    <span className="w-1.5 h-1.5 bg-purple-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                    Include your username in payment reference
                  </li>
                </ul>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Deposit;
