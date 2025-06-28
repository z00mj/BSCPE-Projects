import { Link, useLocation } from 'wouter';
import { useAuth } from '@/hooks/use-auth';
import { Button } from '@/components/ui/button';
import { 
  LogOut, 
  Home, 
  Gamepad2, 
  Wallet, 
  Pickaxe, 
  Shield,
  Coins,
  Gem
} from 'lucide-react';

export default function Navigation() {
  const [location] = useLocation();
  const { user, logout } = useAuth();

  const navItems = [
    { path: '/dashboard', icon: Home, label: 'Dashboard' },
    { path: '/games', icon: Gamepad2, label: 'Games' },
    { path: '/wallet', icon: Wallet, label: 'Wallet' },
    { path: '/mining', icon: Pickaxe, label: 'Mining' },
  ];

  if (user?.isAdmin) {
    navItems.push({ path: '/admin', icon: Shield, label: 'Admin' });
  }

  const handleLogout = async () => {
    try {
      await logout();
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <nav className="bg-casino-dark border-b border-casino-orange/30 sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <Link href="/dashboard">
                <div className="cursor-pointer">
                  <h1 className="text-2xl font-bold casino-orange">BigBlackCoin</h1>
                  <span className="text-xs casino-gold">$BBC Casino</span>
                </div>
              </Link>
            </div>
            <div className="hidden md:block ml-10">
              <div className="flex items-baseline space-x-4">
                {navItems.map((item) => {
                  const Icon = item.icon;
                  const isActive = location === item.path || 
                    (item.path === '/games' && location.startsWith('/games'));
                  
                  return (
                    <Link key={item.path} href={item.path}>
                      <div className={`nav-link flex items-center gap-2 ${isActive ? 'active' : ''}`}>
                        <Icon size={16} />
                        {item.label}
                      </div>
                    </Link>
                  );
                })}
              </div>
            </div>
          </div>
          
          <div className="flex items-center space-x-4">
            <div className="bg-casino-dark border border-casino-orange/30 rounded-lg px-4 py-2">
              <div className="text-xs text-gray-400">Balance</div>
              <div className="text-sm font-semibold casino-gold flex items-center gap-1">
                <Coins size={14} />
                {parseFloat(user?.coinBalance || '0').toLocaleString()} Coins
              </div>
            </div>
            <div className="bg-casino-dark border border-casino-orange/30 rounded-lg px-4 py-2">
              <div className="text-xs text-gray-400">$BBC Tokens</div>
              <div className="text-sm font-semibold casino-orange flex items-center gap-1">
                <Gem size={14} />
                {parseFloat(user?.bbcBalance || '0').toFixed(3)} $BBC
              </div>
            </div>
            <Button 
              onClick={handleLogout}
              className="bg-casino-orange hover:bg-casino-red text-white"
            >
              <LogOut size={16} className="mr-2" />
              Logout
            </Button>
          </div>
        </div>
      </div>
    </nav>
  );
}
