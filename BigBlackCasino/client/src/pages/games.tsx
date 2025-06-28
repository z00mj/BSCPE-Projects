import { useState } from "react";
import GameCard from "@/components/game-card";
import { Button } from "@/components/ui/button";
import { 
  Dice1, 
  Coins, 
  Trophy, 
  Gem,
  Mountain
} from "lucide-react";

export default function Games() {
  const [activeFilter, setActiveFilter] = useState("all");

  const filters = [
    { key: "all", label: "All Games" },
    { key: "slots", label: "Slots" },
    { key: "table", label: "Table Games" },
    { key: "crash", label: "Crash Games" },
  ];

  const games = [
    {
      title: "Luck and Roll",
      description: "A spinning 16-slice wheel with 6 Bankrupt slices, 9 multiplier slices (1.1x to 10x), and 1 Jackpot slice. High-risk fun with strategy and tension.",
      category: "table",
      icon: (
        <div className="w-32 h-32 border-4 border-casino-orange rounded-full flex items-center justify-center relative">
          <div className="w-24 h-24 border-2 border-casino-gold rounded-full flex items-center justify-center">
            <Dice1 className="text-casino-gold text-3xl w-8 h-8" />
          </div>
          <div className="absolute -top-2 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-8 border-l-transparent border-r-transparent border-b-casino-gold"></div>
        </div>
      ),
      badge: "HOT",
      href: "/games/luck-and-roll",
      gradient: "bg-gradient-to-br from-casino-orange/20 to-casino-red/20"
    },
    {
      title: "Flip it Jonathan!",
      description: "A coin flip streak game where you choose heads/tails. Each successful flip increases the multiplier, but one mistake resets everything.",
      category: "table",
      icon: (
        <div className="w-20 h-20 bg-casino-gold rounded-full flex items-center justify-center relative animate-pulse">
          <Coins className="text-casino-dark text-2xl w-6 h-6" />
        </div>
      ),
      href: "/games/flip-it-jonathan",
      gradient: "bg-gradient-to-br from-casino-gold/20 to-casino-orange/20"
    },
    {
      title: "Paldo!",
      description: "A 5-reel, 3-row slot game with 25 paylines featuring wilds, scatters, free spins, and progressive jackpots.",
      category: "slots",
      icon: (
        <div className="grid grid-cols-3 gap-2">
          <div className="w-12 h-12 bg-casino-dark border border-casino-orange rounded flex items-center justify-center">
            <Gem className="text-casino-orange w-6 h-6" />
          </div>
          <div className="w-12 h-12 bg-casino-dark border border-casino-gold rounded flex items-center justify-center">
            <Trophy className="text-casino-gold w-6 h-6" />
          </div>
          <div className="w-12 h-12 bg-casino-dark border border-casino-orange rounded flex items-center justify-center">
            <Gem className="text-casino-orange w-6 h-6" />
          </div>
        </div>
      ),
      badge: "NEW",
      badgeColor: "bg-casino-red",
      href: "/games/paldo",
      gradient: "bg-gradient-to-br from-casino-red/20 to-casino-orange/20"
    },
    {
      title: "Ipis Sipi",
      description: "A thrilling cockroach adventure game with 9 progressive steps and multipliers from 1.2x to 20x. Avoid kitchen hazards!",
      category: "crash",
      icon: (
        <div className="relative">
          <div className="w-16 h-12 bg-casino-dark rounded-full flex items-center justify-center border border-casino-orange">
            <span className="text-casino-orange text-xl">🪳</span>
          </div>
          <div className="absolute -right-2 -top-1 text-casino-gold text-xs">💰</div>
        </div>
      ),
      href: "/games/ipis-sipi",
      gradient: "bg-gradient-to-br from-green-900/20 to-casino-orange/20"
    },
    {
      title: "Blow it Bolims!",
      description: "A balloon-inflation crash game where you cash out before it bursts. Features auto-cashout and bonus balloons.",
      category: "crash",
      icon: (
        <div className="w-20 h-24 rounded-full bg-gradient-to-t from-casino-orange to-casino-red flex items-center justify-center relative">
          <span className="text-white text-xl">💣</span>
          <div className="absolute -top-2 w-2 h-6 bg-casino-gold rounded-full"></div>
        </div>
      ),
      href: "/games/blow-it-bolims",
      gradient: "bg-gradient-to-br from-blue-900/20 to-casino-orange/20"
    }
  ];

  const filteredGames = activeFilter === "all" 
    ? games 
    : games.filter(game => game.category === activeFilter);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h2 className="text-3xl font-bold text-white mb-8">Casino Games</h2>
      
      {/* Game Filter */}
      <div className="flex flex-wrap gap-4 mb-8">
        {filters.map(filter => (
          <Button
            key={filter.key}
            onClick={() => setActiveFilter(filter.key)}
            className={
              activeFilter === filter.key
                ? "casino-button"
                : "casino-button-secondary"
            }
          >
            {filter.label}
          </Button>
        ))}
      </div>

      {/* Games Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredGames.map((game, index) => (
          <GameCard
            key={index}
            title={game.title}
            description={game.description}
            icon={game.icon}
            badge={game.badge}
            badgeColor={game.badgeColor}
            minBet="0.25"
            maxBet="1000.00"
            href={game.href}
            gradient={game.gradient}
          />
        ))}
      </div>
    </div>
  );
}
