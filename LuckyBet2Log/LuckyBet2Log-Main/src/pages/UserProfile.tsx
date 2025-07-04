
import Layout from "@/components/Layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { 
  User, 
  Wallet, 
  Coins, 
  TrendingUp, 
  Crown, 
  Star, 
  Shield, 
  Calendar,
  Mail,
  Activity,
  Sparkles,
  Trophy,
  Target,
  Zap,
  Edit,
  Key,
  Save,
  X
} from "lucide-react";
import { useProfile } from "@/hooks/useProfile";
import { useAuth } from "@/hooks/useAuth";
import { useToast } from "@/hooks/use-toast";
import { useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { Link } from "react-router-dom";
import { useState } from "react";

const UserProfile = () => {
  const { user } = useAuth();
  const { profile, isLoading } = useProfile();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  
  // Edit profile states
  const [isEditingUsername, setIsEditingUsername] = useState(false);
  const [newUsername, setNewUsername] = useState("");
  const [isUsernameLoading, setIsUsernameLoading] = useState(false);
  
  // Change password states
  const [isPasswordDialogOpen, setIsPasswordDialogOpen] = useState(false);
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [isPasswordLoading, setIsPasswordLoading] = useState(false);

  // Update username function
  const handleUpdateUsername = async () => {
    if (!newUsername.trim() || !profile) return;
    
    if (newUsername.trim() === profile.username) {
      setIsEditingUsername(false);
      return;
    }

    setIsUsernameLoading(true);
    try {
      const { error } = await supabase
        .from('profiles')
        .update({ username: newUsername.trim() })
        .eq('user_id', user?.id);

      if (error) throw error;

      toast({
        title: "Success!",
        description: "Username updated successfully!",
      });
      
      setIsEditingUsername(false);
      // Immediately invalidate and refetch the profile to update the UI
      queryClient.invalidateQueries({ queryKey: ["profile", user?.id] });
    } catch (error: any) {
      console.error('Username update error:', error);
      toast({
        title: "Error",
        description: error.message || "Failed to update username",
        variant: "destructive",
      });
    } finally {
      setIsUsernameLoading(false);
    }
  };

  // Change password function
  const handleChangePassword = async () => {
    if (!currentPassword || !newPassword || !confirmPassword) {
      toast({
        title: "Error",
        description: "Please fill in all password fields",
        variant: "destructive",
      });
      return;
    }

    if (newPassword !== confirmPassword) {
      toast({
        title: "Error",
        description: "New passwords don't match",
        variant: "destructive",
      });
      return;
    }

    if (newPassword.length < 6) {
      toast({
        title: "Error",
        description: "Password must be at least 6 characters long",
        variant: "destructive",
      });
      return;
    }

    setIsPasswordLoading(true);
    try {
      // First verify current password by attempting to sign in
      const { error: signInError } = await supabase.auth.signInWithPassword({
        email: user?.email || '',
        password: currentPassword
      });

      if (signInError) {
        throw new Error('Current password is incorrect');
      }

      // Update password
      const { error: updateError } = await supabase.auth.updateUser({
        password: newPassword
      });

      if (updateError) throw updateError;

      toast({
        title: "Success!",
        description: "Password changed successfully!",
      });
      
      setIsPasswordDialogOpen(false);
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
    } catch (error: any) {
      console.error('Password change error:', error);
      toast({
        title: "Error",
        description: error.message || "Failed to change password",
        variant: "destructive",
      });
    } finally {
      setIsPasswordLoading(false);
    }
  };

  // Initialize username when editing starts
  const startEditingUsername = () => {
    setNewUsername(profile?.username || "");
    setIsEditingUsername(true);
  };

  if (isLoading) {
    return (
      <Layout>
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-background via-background to-primary/5">
          <div className="flex flex-col items-center space-y-4">
            <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-500"></div>
            <p className="text-muted-foreground animate-pulse">Loading your profile...</p>
          </div>
        </div>
      </Layout>
    );
  }

  if (!profile) {
    return (
      <Layout>
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-background via-background to-primary/5">
          <div className="text-center space-y-4">
            <div className="w-24 h-24 bg-gradient-to-r from-red-500 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6">
              <User className="w-12 h-12 text-white" />
            </div>
            <h2 className="text-3xl font-bold mb-4">Profile not found</h2>
            <p className="text-muted-foreground text-lg">Unable to load your profile data.</p>
            <Link to="/">
              <Button className="mt-6">Return Home</Button>
            </Link>
          </div>
        </div>
      </Layout>
    );
  }

  const achievements = [
    { icon: Star, title: "First Game", description: "Played your first game", unlocked: true },
    { icon: Trophy, title: "Big Winner", description: "Won over ₱1000 in a single game", unlocked: false },
    { icon: Target, title: "Precision", description: "Win 10 games in a row", unlocked: false },
    { icon: Crown, title: "High Roller", description: "Bet over ₱10,000", unlocked: false }
  ];

  const accountStats = [
    { 
      label: "Account Status", 
      value: profile.is_banned ? 'Banned' : profile.is_suspended ? 'Suspended' : 'Active',
      color: profile.is_banned ? 'text-red-400' : profile.is_suspended ? 'text-yellow-400' : 'text-green-400',
      icon: Shield
    },
    { 
      label: "Account Type", 
      value: profile.is_admin ? 'Admin' : 'Player',
      color: "text-purple-400",
      icon: Crown
    },
    { 
      label: "Total Portfolio", 
      value: `₱${(Number(profile.php_balance) + Number(profile.coins) + (Number(profile.itlog_tokens) * 5000)).toFixed(2)}`,
      color: "text-blue-400",
      icon: TrendingUp
    },
    { 
      label: "Member Since", 
      value: new Date(profile.created_at).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }),
      color: "text-muted-foreground",
      icon: Calendar
    }
  ];

  return (
    <Layout>
      <div className="min-h-screen bg-gradient-to-br from-background via-background to-primary/5">
        {/* Animated Background Elements */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500/5 rounded-full blur-3xl animate-float"></div>
          <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl animate-bounce-gentle"></div>
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-r from-purple-500/3 to-blue-500/3 rounded-full blur-3xl"></div>
        </div>

        <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          {/* Hero Section */}
          <div className="text-center mb-12">
            <div className="inline-flex items-center space-x-2 glass rounded-full px-6 py-3 border border-white/20 mb-6">
              <User className="w-5 h-5 text-purple-400" />
              <span className="text-sm font-medium text-gradient">Player Profile</span>
            </div>
            
            <h1 className="text-4xl sm:text-5xl md:text-6xl font-black mb-4">
              <span className="bg-gradient-to-r from-purple-400 via-pink-400 to-gold-400 bg-clip-text text-transparent">
                {profile.username}
              </span>
            </h1>
            
            <div className="flex flex-wrap items-center justify-center gap-3 mb-6">
              {profile.is_admin && (
                <Badge className="bg-gradient-to-r from-gold-500 to-amber-500 text-black font-semibold px-4 py-2 glow-gold">
                  <Crown className="w-4 h-4 mr-2" />
                  Admin
                </Badge>
              )}
              <Badge 
                className={`px-4 py-2 border font-semibold flex items-center ${
                  profile.is_banned 
                    ? 'border-red-500/50 text-red-400 bg-red-500/20' 
                    : profile.is_suspended 
                    ? 'border-yellow-500/50 text-yellow-400 bg-yellow-500/20' 
                    : 'border-green-500/50 text-green-400 bg-green-500/20'
                }`}
              >
                <Activity className="w-4 h-4 mr-2 flex-shrink-0" />
                <span>{profile.is_banned ? 'Banned' : profile.is_suspended ? 'Suspended' : 'Active'}</span>
              </Badge>
            </div>
            
            <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
              Welcome to your personal gaming dashboard. Track your progress, manage your assets, and unlock achievements.
            </p>
          </div>

          <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
            {/* Profile Info Card */}
            <div className="xl:col-span-1">
              <Card className="modern-card hover-lift">
                <CardHeader className="text-center pb-6">
                  <div className="relative mx-auto mb-6">
                    <div className="w-32 h-32 bg-gradient-to-r from-purple-500 via-pink-500 to-gold-500 rounded-full flex items-center justify-center shadow-2xl glow-purple">
                      <User className="w-16 h-16 text-white" />
                    </div>
                    <div className="absolute -bottom-2 -right-2 w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full flex items-center justify-center border-4 border-background">
                      <Shield className="w-5 h-5 text-white" />
                    </div>
                  </div>
                  
                  {isEditingUsername ? (
                    <div className="space-y-3">
                      <Input
                        value={newUsername}
                        onChange={(e) => setNewUsername(e.target.value)}
                        placeholder="Enter new username"
                        className="text-center"
                        maxLength={20}
                      />
                      <div className="flex gap-2 justify-center">
                        <Button
                          size="sm"
                          onClick={handleUpdateUsername}
                          disabled={isUsernameLoading || !newUsername.trim()}
                          className="bg-green-500 hover:bg-green-600"
                        >
                          {isUsernameLoading ? (
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                          ) : (
                            <Save className="w-4 h-4" />
                          )}
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setIsEditingUsername(false)}
                          disabled={isUsernameLoading}
                        >
                          <X className="w-4 h-4" />
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      <div className="flex items-center justify-center gap-2">
                        <CardTitle className="text-xl sm:text-2xl font-bold break-words">{profile.username}</CardTitle>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={startEditingUsername}
                          className="text-muted-foreground hover:text-purple-400"
                        >
                          <Edit className="w-4 h-4" />
                        </Button>
                      </div>
                    </div>
                  )}
                  <p className="text-sm text-gray-300 font-mono bg-gray-800/50 px-3 py-1 rounded-md border border-gray-600/30 mt-2 break-all">Player ID: {profile.wallet_id}</p>
                </CardHeader>
                
                <CardContent className="space-y-6">
                  <div className="space-y-4">
                    <div className="flex items-center space-x-3 p-3 rounded-lg bg-gradient-to-r from-blue-500/10 to-cyan-500/10 border border-blue-500/20">
                      <Mail className="w-5 h-5 text-blue-400" />
                      <div>
                        <p className="text-sm text-muted-foreground">Email</p>
                        <p className="font-semibold">{user?.email}</p>
                      </div>
                    </div>
                    
                    <div className="flex items-center space-x-3 p-3 rounded-lg bg-gradient-to-r from-purple-500/10 to-pink-500/10 border border-purple-500/20">
                      <Calendar className="w-5 h-5 text-purple-400" />
                      <div>
                        <p className="text-sm text-muted-foreground">Member Since</p>
                        <p className="font-semibold">{new Date(profile.created_at).toLocaleDateString('en-US', { 
                          month: 'long', 
                          day: 'numeric', 
                          year: 'numeric' 
                        })}</p>
                      </div>
                    </div>
                  </div>

                  <Separator />

                  <div className="space-y-3">
                    <h4 className="font-semibold flex items-center">
                      <Sparkles className="w-4 h-4 mr-2 text-yellow-400" />
                      Quick Actions
                    </h4>
                    <div className="grid grid-cols-1 gap-3">
                      <Link to="/wallet">
                        <Button className="w-full bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white">
                          <Wallet className="w-4 h-4 mr-2" />
                          Manage Wallet
                        </Button>
                      </Link>
                      <Link to="/games">
                        <Button variant="outline" className="w-full border-purple-500/30 text-purple-400 hover:bg-purple-500/10">
                          <Zap className="w-4 h-4 mr-2" />
                          Play Games
                        </Button>
                      </Link>
                      <Dialog open={isPasswordDialogOpen} onOpenChange={setIsPasswordDialogOpen}>
                        <DialogTrigger asChild>
                          <Button variant="outline" className="w-full border-orange-500/30 text-orange-400 hover:bg-orange-500/10">
                            <Key className="w-4 h-4 mr-2" />
                            Change Password
                          </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                          <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                              <Key className="w-5 h-5 text-orange-400" />
                              Change Password
                            </DialogTitle>
                          </DialogHeader>
                          <div className="space-y-4">
                            <div className="space-y-2">
                              <Label htmlFor="current-password">Current Password</Label>
                              <Input
                                id="current-password"
                                type="password"
                                value={currentPassword}
                                onChange={(e) => setCurrentPassword(e.target.value)}
                                placeholder="Enter current password"
                              />
                            </div>
                            <div className="space-y-2">
                              <Label htmlFor="new-password">New Password</Label>
                              <Input
                                id="new-password"
                                type="password"
                                value={newPassword}
                                onChange={(e) => setNewPassword(e.target.value)}
                                placeholder="Enter new password"
                              />
                            </div>
                            <div className="space-y-2">
                              <Label htmlFor="confirm-password">Confirm New Password</Label>
                              <Input
                                id="confirm-password"
                                type="password"
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                placeholder="Confirm new password"
                              />
                            </div>
                            <div className="flex gap-2 pt-4">
                              <Button
                                onClick={handleChangePassword}
                                disabled={isPasswordLoading}
                                className="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600"
                              >
                                {isPasswordLoading ? (
                                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
                                ) : (
                                  <Key className="w-4 h-4 mr-2" />
                                )}
                                Change Password
                              </Button>
                              <Button
                                variant="outline"
                                onClick={() => {
                                  setIsPasswordDialogOpen(false);
                                  setCurrentPassword("");
                                  setNewPassword("");
                                  setConfirmPassword("");
                                }}
                                disabled={isPasswordLoading}
                              >
                                Cancel
                              </Button>
                            </div>
                          </div>
                        </DialogContent>
                      </Dialog>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>

            {/* Main Content */}
            <div className="xl:col-span-2 space-y-8">
              {/* Balance Cards */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <Card className="modern-card bg-gradient-to-br from-green-500/10 to-emerald-500/10 border-green-500/30 glow-green hover-lift">
                  <CardContent className="p-4 sm:p-6 text-center">
                    <div className="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-r from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-3 sm:mb-4 shadow-lg">
                      <Wallet className="w-6 h-6 sm:w-8 sm:h-8 text-white" />
                    </div>
                    <p className="text-xs sm:text-sm text-muted-foreground mb-2">PHP Balance</p>
                    <p className="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-green-400 break-words overflow-hidden">₱{Number(profile.php_balance).toFixed(2)}</p>
                    <p className="text-xs text-green-300 mt-1">Ready to withdraw</p>
                  </CardContent>
                </Card>

                <Card className="modern-card bg-gradient-to-br from-blue-500/10 to-cyan-500/10 border-blue-500/30 glow-blue hover-lift">
                  <CardContent className="p-4 sm:p-6 text-center">
                    <div className="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center mx-auto mb-3 sm:mb-4 shadow-lg">
                      <Coins className="w-6 h-6 sm:w-8 sm:h-8 text-white" />
                    </div>
                    <p className="text-xs sm:text-sm text-muted-foreground mb-2">Game Coins</p>
                    <p className="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-blue-400 break-words overflow-hidden">{Number(profile.coins).toFixed(2)}</p>
                    <p className="text-xs text-blue-300 mt-1">For gaming</p>
                  </CardContent>
                </Card>

                <Card className="modern-card bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border-yellow-500/30 glow-gold hover-lift sm:col-span-2 lg:col-span-1">
                  <CardContent className="p-4 sm:p-6 text-center">
                    <div className="w-12 h-12 sm:w-16 sm:h-16 itlog-token rounded-2xl flex items-center justify-center mx-auto mb-3 sm:mb-4 shadow-lg">
                      <span className="text-black font-bold text-xl sm:text-2xl">₿</span>
                    </div>
                    <p className="text-xs sm:text-sm text-muted-foreground mb-2">$ITLOG Tokens</p>
                    <p className="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-yellow-400 break-words overflow-hidden">{Number(profile.itlog_tokens).toFixed(2)}</p>
                    <p className="text-xs text-yellow-300 mt-1">Premium rewards</p>
                  </CardContent>
                </Card>
              </div>

              {/* Stats Grid */}
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center text-2xl">
                    <TrendingUp className="w-6 h-6 mr-3 text-purple-400" />
                    Account Statistics
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                    {accountStats.map((stat, index) => {
                      const Icon = stat.icon;
                      return (
                        <div key={index} className="text-center space-y-3 p-3 rounded-lg bg-muted/30">
                          <div className="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-purple-500/20 to-pink-500/20 rounded-xl">
                            <Icon className="w-6 h-6 text-purple-400" />
                          </div>
                          <div>
                            <p className="text-xs sm:text-sm text-muted-foreground">{stat.label}</p>
                            <p className={`text-lg sm:text-xl font-bold ${stat.color} break-words`}>{stat.value}</p>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>

              {/* Achievements */}
              <Card className="modern-card hover-lift">
                <CardHeader>
                  <CardTitle className="flex items-center text-2xl">
                    <Trophy className="w-6 h-6 mr-3 text-gold-400" />
                    Achievements
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
                    {achievements.map((achievement, index) => {
                      const Icon = achievement.icon;
                      return (
                        <div 
                          key={index} 
                          className={`p-3 sm:p-4 rounded-lg border transition-all duration-300 ${
                            achievement.unlocked 
                              ? 'bg-gradient-to-r from-gold-500/10 to-yellow-500/10 border-gold-500/30 glow-gold' 
                              : 'bg-muted/50 border-muted-foreground/20 opacity-50'
                          }`}
                        >
                          <div className="flex items-start space-x-3">
                            <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${
                              achievement.unlocked 
                                ? 'bg-gradient-to-r from-gold-500 to-yellow-500' 
                                : 'bg-muted-foreground/20'
                            }`}>
                              <Icon className={`w-5 h-5 ${achievement.unlocked ? 'text-black' : 'text-muted-foreground'}`} />
                            </div>
                            <div className="min-w-0 flex-1">
                              <h4 className={`text-sm sm:text-base font-semibold ${achievement.unlocked ? 'text-gold-400' : 'text-muted-foreground'}`}>
                                {achievement.title}
                              </h4>
                              <p className="text-xs sm:text-sm text-muted-foreground break-words">{achievement.description}</p>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default UserProfile;
