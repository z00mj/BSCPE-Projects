
import { useState, useEffect } from "react";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Link } from "wouter";
import {
  Gamepad2,
  Wallet,
  Cat,
  Sparkles,
  Coins,
  TrendingUp,
  Shield,
  Users,
  Trophy,
  Zap,
} from "lucide-react";

// Particle component
const Particle = ({ delay }: { delay: number }) => {
  const [style, setStyle] = useState({});

  useEffect(() => {
    const randomX = Math.random() * 100;
    const randomSize = Math.random() * 4 + 2;
    const randomDuration = Math.random() * 10 + 15;

    setStyle({
      left: `${randomX}%`,
      width: `${randomSize}px`,
      height: `${randomSize}px`,
      animationDelay: `${delay}s`,
      animationDuration: `${randomDuration}s`,
    });
  }, [delay]);

  return <div className="particle" style={style} />;
};

// Coin rain component
const CoinRain = ({ delay }: { delay: number }) => {
  const [style, setStyle] = useState({});

  useEffect(() => {
    const randomX = Math.random() * 100;
    const randomDuration = Math.random() * 4 + 6;

    setStyle({
      left: `${randomX}%`,
      animationDelay: `${delay}s`,
      animationDuration: `${randomDuration}s`,
    });
  }, [delay]);

  return (
    <div className="coin-rain" style={style}>
      💰
    </div>
  );
};

export default function Homepage() {
  const { user } = useAuth();
  const [particles, setParticles] = useState<number[]>([]);
  const [coins, setCoins] = useState<number[]>([]);

  // Initialize particles and coin rain
  useEffect(() => {
    setParticles(Array.from({ length: 20 }, (_, i) => i));
    setCoins(Array.from({ length: 8 }, (_, i) => i));
  }, []);

  if (!user) {
    return <div>Loading...</div>;
  }

  return (
    <div className="relative min-h-screen background-animated">
      {/* Particle System */}
      <div className="particles-container">
        {particles.map((particle, index) => (
          <Particle key={`particle-${particle}`} delay={index * 0.5} />
        ))}
        {coins.map((coin, index) => (
          <CoinRain key={`coin-${coin}`} delay={index * 1.2} />
        ))}
      </div>

      {/* Floating decorative elements */}
      <div className="fixed top-20 left-10 text-crypto-pink/20 animate-float z-10">
        <Sparkles size={32} />
      </div>
      <div className="fixed top-40 right-20 text-crypto-pink/20 animate-float-delayed z-10">
        <Coins size={28} />
      </div>
      <div className="fixed bottom-32 left-20 text-crypto-pink/20 animate-float z-10">
        <Cat size={24} />
      </div>

      <div className="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Hero Section */}
        <div className="text-center mb-12 animate-fade-in">
          <div className="w-24 h-24 gradient-pink rounded-full flex items-center justify-center mx-auto mb-6 animate-glow">
            <span className="text-4xl">🐱</span>
          </div>
          <h1 className="text-5xl font-display font-bold mb-4 gradient-pink bg-clip-text text-transparent animate-jackpot text-balance">
            Welcome to CryptoMeow
          </h1>
          <p className="text-xl font-body text-gray-300 mb-2">
            Hello, <span className="text-crypto-pink font-crypto font-semibold">{user.username}</span>!
          </p>
          <p className="text-lg font-body text-gray-400 mb-8 text-pretty max-w-2xl mx-auto">
            Your ultimate crypto casino and cat farming experience
          </p>
          
          {/* Quick Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Wallet className="w-8 h-8 text-crypto-green mx-auto mb-2" />
                <div className="text-2xl font-mono font-bold crypto-green">
                  {parseFloat(user.balance).toFixed(2)}
                </div>
                <div className="text-sm text-gray-400">Coins Balance</div>
              </CardContent>
            </Card>
            
            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Cat className="w-8 h-8 text-crypto-pink mx-auto mb-2" />
                <div className="text-2xl font-mono font-bold text-crypto-pink">
                  {parseFloat(user.meowBalance).toFixed(4)}
                </div>
                <div className="text-sm text-gray-400">$MEOW Tokens</div>
              </CardContent>
            </Card>
            
            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Shield className="w-8 h-8 text-yellow-500 mx-auto mb-2" />
                <div className="text-2xl font-bold text-yellow-500">
                  {user.isAdmin ? 'Admin' : 'Player'}
                </div>
                <div className="text-sm text-gray-400">Account Status</div>
              </CardContent>
            </Card>
          </div>
        </div>

        {/* Main Actions */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
          <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale transition-all hover:scale-105 flex flex-col h-full">
            <CardHeader>
              <CardTitle className="text-xl font-heading font-bold text-crypto-pink flex items-center">
                <Gamepad2 className="w-6 h-6 mr-2" />
                Casino Games
              </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col flex-grow">
              <p className="text-gray-300 mb-4 flex-grow">
                Play our exciting casino games including Mines, Crash, Slots, Hi-Lo, and Dice. 
                Every game offers a chance to win the progressive jackpot!
              </p>
              <Button asChild className="w-full crypto-pink hover:bg-crypto-pink-light mt-auto">
                <Link href="/casino">
                  Start Playing
                </Link>
              </Button>
            </CardContent>
          </Card>

          <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale transition-all hover:scale-105 flex flex-col h-full">
            <CardHeader>
              <CardTitle className="text-xl font-heading font-bold text-crypto-pink flex items-center">
                <Cat className="w-6 h-6 mr-2" />
                Cat Farm
              </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col flex-grow">
              <p className="text-gray-300 mb-4 flex-grow">
                Collect and breed adorable cats that generate $MEOW tokens passively. 
                Build your cat empire and watch your earnings grow!
              </p>
              <Button asChild className="w-full crypto-pink hover:bg-crypto-pink-light mt-auto">
                <Link href="/farm">
                  Visit Farm
                </Link>
              </Button>
            </CardContent>
          </Card>

          <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale transition-all hover:scale-105 flex flex-col h-full">
            <CardHeader>
              <CardTitle className="text-xl font-heading font-bold text-crypto-pink flex items-center">
                <Wallet className="w-6 h-6 mr-2" />
                Wallet
              </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col flex-grow">
              <p className="text-gray-300 mb-4 flex-grow">
                Manage your funds, deposit money, withdraw winnings, and convert between 
                coins and $MEOW tokens seamlessly.
              </p>
              <Button asChild className="w-full crypto-pink hover:bg-crypto-pink-light mt-auto">
                <Link href="/wallet">
                  Manage Wallet
                </Link>
              </Button>
            </CardContent>
          </Card>
        </div>

        {/* Features Section */}
        <div className="mb-12">
          <h2 className="text-3xl font-heading font-bold text-center mb-8 text-crypto-pink text-balance">
            Why Choose CryptoMeow?
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="text-center animate-fade-in">
              <Shield className="w-12 h-12 text-crypto-green mx-auto mb-4" />
              <h3 className="text-lg font-heading font-semibold mb-2">Provably Fair</h3>
              <p className="text-gray-400 text-sm">All games use cryptographic algorithms to ensure fairness</p>
            </div>
            
            <div className="text-center animate-fade-in" style={{ animationDelay: "0.1s" }}>
              <Zap className="w-12 h-12 text-yellow-500 mx-auto mb-4" />
              <h3 className="text-lg font-heading font-semibold mb-2">Instant Payouts</h3>
              <p className="text-gray-400 text-sm">Fast and secure transactions with instant winnings</p>
            </div>
            
            <div className="text-center animate-fade-in" style={{ animationDelay: "0.2s" }}>
              <Trophy className="w-12 h-12 text-crypto-pink mx-auto mb-4" />
              <h3 className="text-lg font-heading font-semibold mb-2">Progressive Jackpots</h3>
              <p className="text-gray-400 text-sm">Every game has a chance to trigger massive jackpot wins</p>
            </div>
            
            <div className="text-center animate-fade-in" style={{ animationDelay: "0.3s" }}>
              <Users className="w-12 h-12 text-blue-500 mx-auto mb-4" />
              <h3 className="text-lg font-heading font-semibold mb-2">Community</h3>
              <p className="text-gray-400 text-sm">Join a thriving community of crypto gaming enthusiasts</p>
            </div>
          </div>
        </div>

        {/* Recent Activity / News Section */}
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow">
          <CardHeader>
            <CardTitle className="text-xl font-heading font-bold text-crypto-pink flex items-center">
              <TrendingUp className="w-6 h-6 mr-2" />
              Latest Updates
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="border-b border-crypto-pink/20 pb-4">
                <h4 className="font-semibold text-crypto-green">🎰 New Games Available!</h4>
                <p className="text-gray-300 text-sm">Try our latest casino games with improved graphics and bigger payouts.</p>
              </div>
              <div className="border-b border-crypto-pink/20 pb-4">
                <h4 className="font-semibold text-crypto-green">🐱 Cat Farm Expansion</h4>
                <p className="text-gray-300 text-sm">Discover new cat breeds and unlock special farming bonuses.</p>
              </div>
              <div>
                <h4 className="font-semibold text-crypto-green">💰 Progressive Jackpot Growing</h4>
                <p className="text-gray-300 text-sm">The current jackpot pool is at an all-time high. Will you be the next winner?</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
