import { Link, useLocation } from "wouter";
import { useAuth } from "@/hooks/use-auth";
import { formatNumber, formatCurrency } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { LogOut, User, Wallet, Gamepad2, Mountain, Shield } from "lucide-react";

export default function Navbar() {
  const [location] = useLocation();
  const { user, logout } = useAuth();

  const navItems = [
    { path: "/", label: "Dashboard", icon: User },
    { path: "/games", label: "Games", icon: Gamepad2 },
    { path: "/wallet", label: "Wallet", icon: Wallet },
    { path: "/mining", label: "Mining", icon: Mountain },
    { path: "/admin", label: "Admin", icon: Shield },
  ];

  return (
    <nav className="bg-casino-dark border-b border-casino-orange/30 sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <Link href="/">
                <div className="cursor-pointer">
                  <h1 className="text-2xl font-bold text-casino-orange">BigBlackCoin</h1>
                  <span className="text-xs text-casino-gold">$BBC Casino</span>
                </div>
              </Link>
            </div>
            <div className="hidden md:block ml-10">
              <div className="flex items-baseline space-x-4">
                {navItems.map((item) => {
                  const Icon = item.icon;
                  const isActive = location === item.path || (item.path === "/games" && location.startsWith("/games"));
                  
                  return (
                    <Link key={item.path} href={item.path}>
                      <a className={`
                        flex items-center space-x-2 px-3 py-2 rounded-md text-sm font-medium transition-colors
                        ${isActive 
                          ? "text-casino-orange border-b-2 border-casino-orange" 
                          : "text-gray-300 hover:text-white"
                        }
                      `}>
                        <Icon className="w-4 h-4" />
                        <span>{item.label}</span>
                      </a>
                    </Link>
                  );
                })}
              </div>
            </div>
          </div>
          
          <div className="flex items-center space-x-4">
            {user && (
              <>
                <div className="bg-casino-dark border border-casino-orange/30 rounded-lg px-4 py-2">
                  <div className="text-xs text-gray-400">Balance</div>
                  <div className="text-sm font-semibold text-casino-gold">
                    {formatNumber(user.balance)} Coins
                  </div>
                </div>
                <div className="bg-casino-dark border border-casino-orange/30 rounded-lg px-4 py-2">
                  <div className="text-xs text-gray-400">$BBC Tokens</div>
                  <div className="text-sm font-semibold text-casino-orange">
                    {formatNumber(user.bbcTokens, 6)} $BBC
                  </div>
                </div>
              </>
            )}
            <Button
              onClick={() => logout()}
              variant="outline"
              size="sm"
              className="bg-casino-orange hover:bg-casino-red text-black border-casino-orange"
            >
              <LogOut className="w-4 h-4 mr-2" />
              Logout
            </Button>
          </div>
        </div>
      </div>
    </nav>
  );
}
