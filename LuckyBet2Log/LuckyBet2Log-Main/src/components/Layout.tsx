
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Bell, User, LogOut, Settings, Home, Gamepad2, TrendingUp, CreditCard, Wallet, Shield, BellRing, Menu, X } from "lucide-react";
import { useAuth } from "@/hooks/useAuth";
import { useToast } from "@/hooks/use-toast";
import { useProfile } from "@/hooks/useProfile";
import NotificationBell from "@/components/NotificationBell";
import { useEffect, useState } from "react";
import { supabase } from "@/integrations/supabase/client";
import { Sheet, SheetContent, SheetTrigger, SheetClose, SheetTitle, SheetDescription } from "@/components/ui/sheet";

interface LayoutProps {
  children: React.ReactNode;
}

const Layout = ({ children }: LayoutProps) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { signOut, user } = useAuth();
  const { toast } = useToast();
  const { profile } = useProfile();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  const navigation = [
    { name: "Home", href: "/", icon: Home, color: "text-blue-400" },
    { name: "Games", href: "/games", icon: Gamepad2, color: "text-purple-400" },
    { name: "Profile", href: "/profile", icon: User, color: "text-green-400" },
    { name: "Wallet", href: "/wallet", icon: Wallet, color: "text-yellow-400" },
    { name: "Deposit", href: "/deposit", icon: CreditCard, color: "text-orange-400" },
    { name: "Earn", href: "/earn", icon: TrendingUp, color: "text-emerald-400" },
    ...(profile?.is_admin ? [{ name: "Admin", href: "/admin", icon: Shield, color: "text-red-400" }] : []),
  ];

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleLogout = async () => {
    try {
      const { error } = await signOut();
      if (!error) {
        toast({
          title: "✨ Successfully logged out",
          description: "See you next time!",
          className: "bg-gradient-to-r from-green-500 to-emerald-500 text-white border-0",
        });
        navigate('/auth');
      } else {
        toast({
          title: "❌ Logout failed",
          description: "There was an error logging out. Please try again.",
          variant: "destructive",
        });
      }
    } catch (error) {
      console.error('Logout error:', error);
      toast({
        title: "❌ Logout failed",
        description: "There was an error logging out. Please try again.",
        variant: "destructive",
      });
    }
  };

  return (
    <div className="min-h-screen gradient-bg pb-20 lg:pb-0">
      {/* Enhanced Modern Navigation - Fixed mobile background issues */}
      <nav className={`fixed top-0 left-0 right-0 z-[100] transition-all duration-500 ease-out ${
        scrolled 
          ? 'bg-slate-900/95 backdrop-blur-xl border-b border-white/10 py-2' 
          : 'bg-slate-900/80 backdrop-blur-sm py-4'
      }`}>
        <div className="w-full max-w-none px-4 sm:px-6 lg:px-8 xl:px-12">
          <div className="flex items-center justify-between h-16 w-full">
            {/* Enhanced Logo */}
            <Link 
              to="/" 
              className="flex items-center space-x-3 group hover-lift"
            >
              <div className="relative">
                <div className="w-10 h-10 sm:w-12 sm:h-12 itlog-token rounded-2xl flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all duration-300">
                  <span className="text-black font-black text-lg sm:text-xl">₿</span>
                </div>
                <div className="absolute -inset-1 bg-gradient-to-r from-purple-600 to-blue-600 rounded-2xl blur opacity-30 group-hover:opacity-60 transition duration-300"></div>
              </div>
              <div className="flex flex-col">
                <span className="text-xl sm:text-2xl font-black text-gradient">
                  LuckyBet2Log
                </span>
                <span className="text-xs text-muted-foreground hidden sm:block">
                  Crypto Casino
                </span>
              </div>
            </Link>

            {/* Desktop Navigation */}
            <div className="hidden lg:flex items-center space-x-2 flex-1 justify-center">
              {navigation.map((item) => {
                const Icon = item.icon;
                const isActive = location.pathname === item.href;
                return (
                  <Link
                    key={item.name}
                    to={item.href}
                    className={`group flex items-center space-x-2 px-4 py-3 rounded-xl transition-all duration-300 text-base ${
                      isActive
                        ? "bg-gradient-to-r from-purple-600/20 to-blue-600/20 text-white shadow-lg"
                        : "text-muted-foreground hover:text-white hover:bg-white/5"
                    }`}
                  >
                    <Icon className={`w-5 h-5 transition-colors duration-300 ${isActive ? 'text-white' : item.color}`} />
                    <span className="font-medium whitespace-nowrap text-sm">{item.name}</span>
                    {isActive && (
                      <div className="w-1.5 h-1.5 bg-gradient-to-r from-purple-400 to-blue-400 rounded-full animate-pulse"></div>
                    )}
                  </Link>
                );
              })}
            </div>

            {/* Enhanced Right Side */}
            <div className="flex items-center space-x-1">
              {/* Compact Balance Display - Fixed mobile background */}
              <div className="hidden lg:flex items-center space-x-1">
                <div className="bg-slate-800/80 backdrop-blur-sm rounded-lg px-2 py-1 border border-white/10">
                  <div className="flex items-center space-x-2 text-xs">
                    <div className="flex items-center space-x-1">
                      <div className="w-1.5 h-1.5 bg-green-400 rounded-full"></div>
                      <span className="text-green-400 font-bold">
                        ₱{profile?.php_balance.toFixed(0) || "0"}
                      </span>
                    </div>
                    <div className="w-px h-3 bg-white/20"></div>
                    <div className="flex items-center space-x-1">
                      <div className="w-1.5 h-1.5 bg-gradient-to-r from-yellow-400 to-orange-400 rounded-full"></div>
                      <span className="font-bold text-gradient-gold">
                        {profile?.itlog_tokens?.toFixed(2) || "0.00"}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              
              {/* Mobile Balance Indicator - Fixed background */}
              <div className="lg:hidden bg-slate-800/80 backdrop-blur-sm rounded-lg px-2 py-1 border border-white/10">
                <div className="flex flex-col text-xs space-y-1">
                  <span className="text-green-400 font-bold">₱{profile?.php_balance.toFixed(0) || "0"}</span>
                  <span className="text-gradient-gold font-bold">{profile?.itlog_tokens?.toFixed(2) || "0.00"}</span>
                </div>
              </div>

              <NotificationBell />
              
              {/* Enhanced Mobile Menu */}
              <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
                <SheetTrigger asChild>
                  <Button 
                    variant="ghost" 
                    size="sm" 
                    className="lg:hidden bg-slate-800/80 backdrop-blur-sm rounded-lg p-2 hover:bg-slate-700/80 border border-white/10"
                  >
                    <Menu className="w-4 h-4" />
                  </Button>
                </SheetTrigger>
                <SheetContent side="right" className="w-[280px] sm:w-80 bg-slate-900/95 backdrop-blur-2xl border-l border-white/10 z-[200]">
                  <SheetTitle className="sr-only">Navigation Menu</SheetTitle>
                  <SheetDescription className="sr-only">Navigate through your casino dashboard</SheetDescription>
                  <div className="flex flex-col h-full">
                    {/* Mobile Menu Header */}
                    <div className="flex items-center justify-between mb-6">
                      <div className="flex items-center space-x-2">
                        <div className="w-8 h-8 itlog-token rounded-lg flex items-center justify-center">
                          <span className="text-black font-bold text-sm">₿</span>
                        </div>
                        <div>
                          <h2 className="font-bold text-base text-gradient">Menu</h2>
                          <p className="text-xs text-muted-foreground">Navigate</p>
                        </div>
                      </div>
                      <SheetClose asChild>
                        <Button variant="ghost" size="sm" className="rounded-lg p-1">
                          <X className="w-4 h-4" />
                        </Button>
                      </SheetClose>
                    </div>
                    
                    {/* Enhanced Balance Display - Fixed background for mobile */}
                    <div className="bg-slate-800/60 backdrop-blur-sm rounded-xl p-4 mb-4 border border-white/10">
                      <h3 className="font-semibold mb-3 text-sm text-gradient">Your Balance</h3>
                      <div className="space-y-3">
                        <div className="flex items-center justify-between p-2 bg-green-500/10 rounded-lg border border-green-500/20">
                          <div className="flex items-center space-x-2">
                            <div className="w-6 h-6 bg-green-500 rounded-md flex items-center justify-center">
                              <span className="text-white font-bold text-xs">₱</span>
                            </div>
                            <span className="text-muted-foreground text-sm">PHP</span>
                          </div>
                          <span className="font-bold text-green-400 text-sm">
                            ₱{profile?.php_balance.toFixed(0) || "0"}
                          </span>
                        </div>
                        <div className="flex items-center justify-between p-2 bg-gradient-to-r from-yellow-500/10 to-orange-500/10 rounded-lg border border-yellow-500/20">
                          <div className="flex items-center space-x-2">
                            <div className="w-6 h-6 itlog-token rounded-md flex items-center justify-center">
                              <span className="text-black font-bold text-xs">₿</span>
                            </div>
                            <span className="text-muted-foreground text-sm">$ITLOG</span>
                          </div>
                          <span className="font-bold text-gradient-gold text-sm">
                            {profile?.itlog_tokens?.toFixed(2) || "0.00"}
                          </span>
                        </div>
                      </div>
                    </div>
                    
                    {/* Navigation Links */}
                    <div className="flex-1 space-y-1">
                      {navigation.map((item) => {
                        const Icon = item.icon;
                        const isActive = location.pathname === item.href;
                        return (
                          <Link
                            key={item.name}
                            to={item.href}
                            onClick={() => setMobileMenuOpen(false)}
                            className={`group flex items-center space-x-3 px-3 py-3 rounded-lg transition-all duration-300 ${
                              isActive
                                ? "bg-gradient-to-r from-purple-600/20 to-blue-600/20 text-white glow-purple"
                                : "text-muted-foreground hover:text-white hover:bg-white/5"
                            }`}
                          >
                            <Icon className={`w-5 h-5 transition-colors duration-300 ${
                              isActive ? 'text-white' : item.color
                            }`} />
                            <span className="text-base font-medium">{item.name}</span>
                            {isActive && (
                              <div className="ml-auto w-1.5 h-1.5 bg-gradient-to-r from-purple-400 to-blue-400 rounded-full animate-pulse"></div>
                            )}
                          </Link>
                        );
                      })}
                    </div>
                    
                    {/* Enhanced Logout Button */}
                    <Button 
                      onClick={handleLogout} 
                      className="w-full mt-4 bg-gradient-to-r from-red-500 to-pink-500 hover:from-red-600 hover:to-pink-600 text-white border-0 rounded-lg py-3 font-semibold"
                    >
                      <LogOut className="w-4 h-4 mr-2" />
                      Logout
                    </Button>
                  </div>
                </SheetContent>
              </Sheet>
              
              {/* Desktop Logout */}
              <Button 
                variant="ghost" 
                size="sm" 
                onClick={handleLogout} 
                className="hidden lg:flex bg-slate-800/80 backdrop-blur-sm rounded-lg hover:bg-red-500/10 hover:text-red-400 border border-white/10 p-2"
              >
                <LogOut className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>
      </nav>

      {/* Enhanced Main Content */}
      <main className="pt-20 lg:pt-24 min-h-screen">
        <div className="page-enter page-enter-active">
          {children}
        </div>
      </main>

      {/* Redesigned Mobile Bottom Navigation - Fixed background */}
      <nav className="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-2xl border-t border-white/10 safe-area-pb">
        <div className="grid grid-cols-5 gap-1 p-2">
          {navigation.slice(0, 5).map((item) => {
            const Icon = item.icon;
            const isActive = location.pathname === item.href;
            return (
              <Link
                key={item.name}
                to={item.href}
                className={`group flex flex-col items-center justify-center p-3 rounded-2xl transition-all duration-300 min-h-[70px] relative overflow-hidden ${
                  isActive 
                    ? "text-white" 
                    : "text-muted-foreground hover:text-white"
                }`}
              >
                {isActive && (
                  <div className="absolute inset-0 bg-gradient-to-r from-purple-600/20 to-blue-600/20 rounded-2xl"></div>
                )}
                <Icon className={`w-6 h-6 mb-2 transition-all duration-300 relative z-10 ${
                  isActive ? 'text-white scale-110' : item.color
                }`} />
                <span className={`text-xs font-medium text-center relative z-10 transition-all duration-300 ${
                  isActive ? 'text-white' : ''
                }`}>
                  {item.name}
                </span>
                {isActive && (
                  <div className="absolute bottom-1 w-1 h-1 bg-gradient-to-r from-purple-400 to-blue-400 rounded-full animate-pulse"></div>
                )}
              </Link>
            );
          })}
        </div>
      </nav>
    </div>
  );
};

export default Layout;
