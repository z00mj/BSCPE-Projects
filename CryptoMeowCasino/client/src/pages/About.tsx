
import { useState, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Sparkles,
  Coins,
  Cat,
  Shield,
  Users,
  Trophy,
  Zap,
  Heart,
  Target,
  Star,
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

export default function About() {
  const [particles, setParticles] = useState<number[]>([]);
  const [coins, setCoins] = useState<number[]>([]);

  // Initialize particles and coin rain
  useEffect(() => {
    setParticles(Array.from({ length: 20 }, (_, i) => i));
    setCoins(Array.from({ length: 8 }, (_, i) => i));
  }, []);

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
          <h1 className="text-5xl font-bold mb-4 gradient-pink bg-clip-text text-transparent animate-jackpot">
            About CryptoMeow
          </h1>
          <p className="text-xl text-gray-300 mb-8 max-w-3xl mx-auto">
            Where cryptocurrency meets adorable cats in the most exciting online casino experience
          </p>
        </div>

        {/* Our Story */}
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow mb-8">
          <CardHeader>
            <CardTitle className="text-2xl font-bold text-crypto-pink flex items-center">
              <Heart className="w-6 h-6 mr-2" />
              Our Story
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-gray-300 text-lg leading-relaxed mb-4">
              CryptoMeow was born from a simple idea: what if we could combine the excitement of cryptocurrency 
              trading with the charm of adorable cats and the thrill of casino gaming? Founded by a team of 
              blockchain enthusiasts and cat lovers, we set out to create a platform that would revolutionize 
              online gambling.
            </p>
            <p className="text-gray-300 text-lg leading-relaxed">
              Our unique approach brings together provably fair casino games, an innovative cat farming system 
              that generates $MEOW tokens, and a vibrant community of players who share our passion for both 
              cryptocurrency and feline friends.
            </p>
          </CardContent>
        </Card>

        {/* Our Mission */}
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow mb-8">
          <CardHeader>
            <CardTitle className="text-2xl font-bold text-crypto-pink flex items-center">
              <Target className="w-6 h-6 mr-2" />
              Our Mission
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-gray-300 text-lg leading-relaxed">
              To provide a safe, fair, and entertaining platform where players can enjoy the best of both worlds: 
              the excitement of casino gaming and the innovative potential of cryptocurrency. We're committed to 
              transparency, security, and creating an experience that's both fun and rewarding for our community.
            </p>
          </CardContent>
        </Card>

        {/* What Makes Us Special */}
        <div className="mb-12">
          <h2 className="text-3xl font-bold text-center mb-8 text-crypto-pink">
            What Makes Us Special
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Shield className="w-12 h-12 text-crypto-green mx-auto mb-4" />
                <h3 className="text-lg font-semibold mb-2">Provably Fair Gaming</h3>
                <p className="text-gray-400 text-sm">
                  Every game uses cryptographic algorithms that can be verified by players, 
                  ensuring complete transparency and fairness.
                </p>
              </CardContent>
            </Card>

            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Cat className="w-12 h-12 text-crypto-pink mx-auto mb-4" />
                <h3 className="text-lg font-semibold mb-2">Unique Cat Farming</h3>
                <p className="text-gray-400 text-sm">
                  Our innovative cat farming system lets you collect adorable cats that 
                  generate $MEOW tokens passively over time.
                </p>
              </CardContent>
            </Card>

            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Zap className="w-12 h-12 text-yellow-500 mx-auto mb-4" />
                <h3 className="text-lg font-semibold mb-2">Instant Transactions</h3>
                <p className="text-gray-400 text-sm">
                  Lightning-fast deposits and withdrawals with secure payment processing 
                  and immediate balance updates.
                </p>
              </CardContent>
            </Card>

            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Trophy className="w-12 h-12 text-crypto-pink mx-auto mb-4" />
                <h3 className="text-lg font-semibold mb-2">Progressive Jackpots</h3>
                <p className="text-gray-400 text-sm">
                  Every game offers a chance to win our ever-growing progressive jackpot, 
                  with life-changing prizes waiting to be claimed.
                </p>
              </CardContent>
            </Card>

            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Users className="w-12 h-12 text-blue-500 mx-auto mb-4" />
                <h3 className="text-lg font-semibold mb-2">Vibrant Community</h3>
                <p className="text-gray-400 text-sm">
                  Join thousands of players in our welcoming community where cat lovers 
                  and crypto enthusiasts come together.
                </p>
              </CardContent>
            </Card>

            <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow hover-scale">
              <CardContent className="p-6 text-center">
                <Star className="w-12 h-12 text-yellow-400 mx-auto mb-4" />
                <h3 className="text-lg font-semibold mb-2">Premium Experience</h3>
                <p className="text-gray-400 text-sm">
                  Enjoy high-quality graphics, smooth gameplay, and an intuitive interface 
                  designed for the best possible user experience.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>

        {/* Our Values */}
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow mb-8">
          <CardHeader>
            <CardTitle className="text-2xl font-bold text-crypto-pink">
              Our Core Values
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <h4 className="text-lg font-semibold text-crypto-green mb-2">🔒 Security First</h4>
                <p className="text-gray-300">
                  We prioritize the security of our players' funds and personal information 
                  with industry-leading security measures.
                </p>
              </div>
              <div>
                <h4 className="text-lg font-semibold text-crypto-green mb-2">⚖️ Fair Play</h4>
                <p className="text-gray-300">
                  All our games are provably fair and transparent, giving every player 
                  an equal chance to win.
                </p>
              </div>
              <div>
                <h4 className="text-lg font-semibold text-crypto-green mb-2">🎯 Innovation</h4>
                <p className="text-gray-300">
                  We constantly evolve and improve our platform, introducing new features 
                  and games to keep the experience fresh.
                </p>
              </div>
              <div>
                <h4 className="text-lg font-semibold text-crypto-green mb-2">🤝 Community</h4>
                <p className="text-gray-300">
                  We believe in building strong relationships with our players and 
                  creating a supportive gaming environment.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Contact Information */}
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95 animate-glow">
          <CardHeader>
            <CardTitle className="text-2xl font-bold text-crypto-pink text-center">
              Join the CryptoMeow Family
            </CardTitle>
          </CardHeader>
          <CardContent className="text-center">
            <p className="text-gray-300 text-lg mb-6">
              Ready to start your adventure? Join thousands of players who have already 
              discovered the magic of CryptoMeow!
            </p>
            <div className="text-crypto-pink">
              <p className="text-sm">
                For support or inquiries, please contact our team through the platform.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
