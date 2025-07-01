
import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/hooks/use-toast";
import { useAuth } from "@/hooks/useAuth";
import { supabase } from "@/integrations/supabase/client";
import { useNavigate } from "react-router-dom";
import { useEffect } from "react";
import { Eye, EyeOff, Mail, Lock, User, Sparkles, TrendingUp } from "lucide-react";

const Auth = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [loginEmail, setLoginEmail] = useState("");
  const [loginPassword, setLoginPassword] = useState("");
  const [registerEmail, setRegisterEmail] = useState("");
  const [registerPassword, setRegisterPassword] = useState("");
  const [registerUsername, setRegisterUsername] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showLoginPassword, setShowLoginPassword] = useState(false);
  const [showRegisterPassword, setShowRegisterPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [activeTab, setActiveTab] = useState("login");

  const { toast } = useToast();
  const { signIn, signUp, user } = useAuth();
  const navigate = useNavigate();

  // Redirect to home if already logged in
  useEffect(() => {
    if (user) {
      navigate("/");
    }
  }, [user, navigate]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    try {
      const { data, error } = await signIn(loginEmail, loginPassword);

      if (error) {
        toast({
          title: "Login failed",
          description: error.message,
          variant: "destructive",
        });
      } else {
        toast({
          title: "Welcome back!",
          description: "You have successfully logged in.",
        });

        // Check if user is admin and redirect accordingly
        if (data.user) {
          const { data: profile } = await supabase
            .from('profiles')
            .select('is_admin')
            .eq('user_id', data.user.id)
            .single();

          if (profile?.is_admin) {
            navigate("/admin");
          } else {
            navigate("/");
          }
        } else {
          navigate("/");
        }
      }
    } catch (error) {
      toast({
        title: "Login failed",
        description: "An unexpected error occurred.",
        variant: "destructive",
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();

    if (registerPassword !== confirmPassword) {
      toast({
        title: "Registration failed",
        description: "Passwords do not match.",
        variant: "destructive",
      });
      return;
    }

    setIsLoading(true);

    try {
      const { data, error } = await signUp(registerEmail, registerPassword, registerUsername);

      if (error) {
        toast({
          title: "Registration failed",
          description: error.message,
          variant: "destructive",
        });
      } else {
        toast({
          title: "Account created!",
          description: "Please check your email to verify your account.",
        });
        // Clear form
        setRegisterEmail("");
        setRegisterPassword("");
        setRegisterUsername("");
        setConfirmPassword("");
      }
    } catch (error) {
      toast({
        title: "Registration failed",
        description: "An unexpected error occurred.",
        variant: "destructive",
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen relative overflow-hidden bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
      {/* Animated Background */}
      <div className="absolute inset-0 overflow-hidden">
        {/* Floating Crypto Coins */}
        <div className="absolute inset-0">
          {[...Array(8)].map((_, i) => (
            <div
              key={i}
              className={`absolute animate-float-slow opacity-10 text-6xl ${
                i % 3 === 0 ? 'animate-delay-0' : i % 3 === 1 ? 'animate-delay-1000' : 'animate-delay-2000'
              }`}
              style={{
                left: `${Math.random() * 100}%`,
                top: `${Math.random() * 100}%`,
                animationDuration: `${8 + Math.random() * 4}s`,
                animationDelay: `${Math.random() * 2}s`,
              }}
            >
              {i % 4 === 0 ? '₿' : i % 4 === 1 ? 'Ξ' : i % 4 === 2 ? '₳' : '⟠'}
            </div>
          ))}
        </div>

        {/* Animated Grid Pattern */}
        <div className="absolute inset-0 opacity-5">
          <div className="grid grid-cols-12 gap-4 h-full animate-pulse">
            {[...Array(144)].map((_, i) => (
              <div key={i} className="border border-purple-400 rounded animate-glow"></div>
            ))}
          </div>
        </div>

        {/* Gradient Orbs */}
        <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-float"></div>
        <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl animate-bounce-gentle"></div>
        <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-purple-500/10 to-blue-500/10 rounded-full blur-3xl animate-spin-slow"></div>
      </div>

      {/* Main Content */}
      <div className="relative z-10 flex items-center justify-center min-h-screen p-4">
        <div className="w-full max-w-md transform transition-all duration-500 hover:scale-105">
          {/* Logo and Brand */}
          <div className="text-center mb-8 animate-fade-in">
            <div className="relative group">
              <div className="w-20 h-20 mx-auto mb-4 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center shadow-2xl group-hover:shadow-yellow-400/50 transition-all duration-300 animate-glow-pulse">
                <span className="text-black font-bold text-3xl">₿</span>
                <Sparkles className="absolute -top-1 -right-1 w-6 h-6 text-yellow-400 animate-spin-slow" />
              </div>
              <h1 className="text-4xl font-bold mb-2">
                <span className="bg-gradient-to-r from-purple-400 via-pink-400 to-yellow-400 bg-clip-text text-transparent animate-gradient">
                  LuckyBet2Log
                </span>
              </h1>
              <p className="text-slate-400 text-sm flex items-center justify-center gap-2">
                <TrendingUp className="w-4 h-4" />
                Your Gateway to Crypto Gaming
              </p>
            </div>
          </div>

          {/* Auth Card */}
          <Card className="backdrop-blur-xl bg-white/10 border border-white/20 shadow-2xl overflow-hidden">
            <CardHeader className="pb-6">
              <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                <TabsList className="grid w-full grid-cols-2 bg-black/20 border border-white/10">
                  <TabsTrigger 
                    value="login" 
                    className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-purple-500 data-[state=active]:to-pink-500 data-[state=active]:text-white transition-all duration-300"
                  >
                    Login
                  </TabsTrigger>
                  <TabsTrigger 
                    value="register"
                    className="data-[state=active]:bg-gradient-to-r data-[state=active]:from-blue-500 data-[state=active]:to-cyan-500 data-[state=active]:text-white transition-all duration-300"
                  >
                    Register
                  </TabsTrigger>
                </TabsList>
              </Tabs>
            </CardHeader>

            <CardContent className="space-y-6">
              <Tabs value={activeTab} className="space-y-6">
                <TabsContent value="login" className="space-y-0 animate-slide-in">
                  <form onSubmit={handleLogin} className="space-y-6">
                    {/* Email Field */}
                    <div className="space-y-2">
                      <Label htmlFor="login-email" className="text-white/90 font-medium">
                        Email Address
                      </Label>
                      <div className="relative group">
                        <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400 group-hover:text-purple-400 transition-colors duration-300" />
                        <Input
                          id="login-email"
                          type="email"
                          placeholder="Enter your email"
                          value={loginEmail}
                          onChange={(e) => setLoginEmail(e.target.value)}
                          className="pl-12 h-12 bg-black/20 border-white/20 text-white placeholder-slate-400 focus:border-purple-500 focus:ring-purple-500/50 transition-all duration-300 hover:bg-black/30"
                          required
                        />
                      </div>
                    </div>

                    {/* Password Field */}
                    <div className="space-y-2">
                      <Label htmlFor="login-password" className="text-white/90 font-medium">
                        Password
                      </Label>
                      <div className="relative group">
                        <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400 group-hover:text-purple-400 transition-colors duration-300" />
                        <Input
                          id="login-password"
                          type={showLoginPassword ? "text" : "password"}
                          placeholder="Enter your password"
                          value={loginPassword}
                          onChange={(e) => setLoginPassword(e.target.value)}
                          className="pl-12 pr-12 h-12 bg-black/20 border-white/20 text-white placeholder-slate-400 focus:border-purple-500 focus:ring-purple-500/50 transition-all duration-300 hover:bg-black/30"
                          required
                        />
                        <button
                          type="button"
                          onClick={() => setShowLoginPassword(!showLoginPassword)}
                          className="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-purple-400 transition-colors duration-300"
                        >
                          {showLoginPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                        </button>
                      </div>
                    </div>

                    <Button 
                      type="submit" 
                      className="w-full h-12 bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-semibold shadow-lg hover:shadow-purple-500/50 transition-all duration-300 transform hover:scale-105" 
                      disabled={isLoading}
                    >
                      {isLoading ? (
                        <div className="flex items-center gap-2">
                          <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                          Logging in...
                        </div>
                      ) : (
                        "Login"
                      )}
                    </Button>
                  </form>
                </TabsContent>

                <TabsContent value="register" className="space-y-0 animate-slide-in">
                  <form onSubmit={handleRegister} className="space-y-6">
                    {/* Username Field */}
                    <div className="space-y-2">
                      <Label htmlFor="register-username" className="text-white/90 font-medium">
                        Username
                      </Label>
                      <div className="relative group">
                        <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400 group-hover:text-blue-400 transition-colors duration-300" />
                        <Input
                          id="register-username"
                          type="text"
                          placeholder="Choose a username"
                          value={registerUsername}
                          onChange={(e) => setRegisterUsername(e.target.value)}
                          className="pl-12 h-12 bg-black/20 border-white/20 text-white placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500/50 transition-all duration-300 hover:bg-black/30"
                          required
                        />
                      </div>
                    </div>

                    {/* Email Field */}
                    <div className="space-y-2">
                      <Label htmlFor="register-email" className="text-white/90 font-medium">
                        Email Address
                      </Label>
                      <div className="relative group">
                        <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400 group-hover:text-blue-400 transition-colors duration-300" />
                        <Input
                          id="register-email"
                          type="email"
                          placeholder="Enter your email"
                          value={registerEmail}
                          onChange={(e) => setRegisterEmail(e.target.value)}
                          className="pl-12 h-12 bg-black/20 border-white/20 text-white placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500/50 transition-all duration-300 hover:bg-black/30"
                          required
                        />
                      </div>
                    </div>

                    {/* Password Field */}
                    <div className="space-y-2">
                      <Label htmlFor="register-password" className="text-white/90 font-medium">
                        Password
                      </Label>
                      <div className="relative group">
                        <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400 group-hover:text-blue-400 transition-colors duration-300" />
                        <Input
                          id="register-password"
                          type={showRegisterPassword ? "text" : "password"}
                          placeholder="Create a password"
                          value={registerPassword}
                          onChange={(e) => setRegisterPassword(e.target.value)}
                          className="pl-12 pr-12 h-12 bg-black/20 border-white/20 text-white placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500/50 transition-all duration-300 hover:bg-black/30"
                          required
                        />
                        <button
                          type="button"
                          onClick={() => setShowRegisterPassword(!showRegisterPassword)}
                          className="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-blue-400 transition-colors duration-300"
                        >
                          {showRegisterPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                        </button>
                      </div>
                    </div>

                    {/* Confirm Password Field */}
                    <div className="space-y-2">
                      <Label htmlFor="confirm-password" className="text-white/90 font-medium">
                        Confirm Password
                      </Label>
                      <div className="relative group">
                        <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400 group-hover:text-blue-400 transition-colors duration-300" />
                        <Input
                          id="confirm-password"
                          type={showConfirmPassword ? "text" : "password"}
                          placeholder="Confirm your password"
                          value={confirmPassword}
                          onChange={(e) => setConfirmPassword(e.target.value)}
                          className="pl-12 pr-12 h-12 bg-black/20 border-white/20 text-white placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500/50 transition-all duration-300 hover:bg-black/30"
                          required
                        />
                        <button
                          type="button"
                          onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                          className="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-blue-400 transition-colors duration-300"
                        >
                          {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                        </button>
                      </div>
                    </div>

                    <Button 
                      type="submit" 
                      className="w-full h-12 bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white font-semibold shadow-lg hover:shadow-blue-500/50 transition-all duration-300 transform hover:scale-105" 
                      disabled={isLoading}
                    >
                      {isLoading ? (
                        <div className="flex items-center gap-2">
                          <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                          Creating account...
                        </div>
                      ) : (
                        "Create Account"
                      )}
                    </Button>
                  </form>
                </TabsContent>
              </Tabs>

              {/* Footer */}
              <div className="text-center pt-4 border-t border-white/10">
                <p className="text-slate-400 text-sm">
                  {activeTab === "login" ? "New to LuckyBet2Log? " : "Already have an account? "}
                  <button
                    type="button"
                    onClick={() => setActiveTab(activeTab === "login" ? "register" : "login")}
                    className="text-purple-400 hover:text-purple-300 font-medium transition-colors duration-300"
                  >
                    {activeTab === "login" ? "Create an account" : "Sign in"}
                  </button>
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Auth;
