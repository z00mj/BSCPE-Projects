
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { usePetSystem } from "@/hooks/usePetSystem";
import { useProfile } from "@/hooks/useProfile";

const rarityColors = {
  common: "bg-gray-500",
  uncommon: "bg-green-500", 
  rare: "bg-blue-500",
  legendary: "bg-purple-500",
  mythical: "bg-gold-500"
};

const rarityGlow = {
  common: "glow-gray",
  uncommon: "glow-green",
  rare: "glow-blue", 
  legendary: "glow-purple",
  mythical: "glow-gold"
};

const eggSprites = {
  common: "🥚", // Basic white egg
  uncommon: "🟫", // Brown-ish egg (using brown square as placeholder for brown egg)
  rare: "🔵", // Blue tinted egg
  legendary: "🟣", // Purple egg
  mythical: "🟡" // Golden egg
};

// More detailed egg representations using text art
const detailedEggSprites = {
  common: (
    <div className="text-4xl mb-2 relative">
      <div className="inline-block transform hover:scale-110 transition-transform duration-200">
        🥚
      </div>
    </div>
  ),
  uncommon: (
    <div className="text-4xl mb-2 relative">
      <div className="inline-block transform hover:scale-110 transition-transform duration-200 filter sepia-[0.3] hue-rotate-[30deg] brightness-[0.9]">
        🥚
      </div>
    </div>
  ),
  rare: (
    <div className="text-4xl mb-2 relative">
      <div className="inline-block transform hover:scale-110 transition-transform duration-200 filter hue-rotate-[200deg] brightness-[1.1] saturate-[1.3]">
        🥚
      </div>
    </div>
  ),
  legendary: (
    <div className="text-4xl mb-2 relative">
      <div className="inline-block transform hover:scale-110 transition-transform duration-200 filter hue-rotate-[270deg] brightness-[1.2] saturate-[1.5]">
        🥚
      </div>
      <div className="absolute inset-0 animate-pulse">
        ✨
      </div>
    </div>
  ),
  mythical: (
    <div className="text-4xl mb-2 relative">
      <div className="inline-block transform hover:scale-110 transition-transform duration-200 filter hue-rotate-[45deg] brightness-[1.4] saturate-[2] contrast-[1.2]">
        🥚
      </div>
      <div className="absolute inset-0 animate-pulse text-yellow-300">
        ✨
      </div>
      <div className="absolute inset-0 animate-ping opacity-20 text-yellow-400">
        💫
      </div>
    </div>
  )
};

export const EggShop = () => {
  const { eggTypes, purchaseEgg } = usePetSystem();
  const { profile } = useProfile();

  const handlePurchase = (eggTypeId: number) => {
    purchaseEgg(eggTypeId);
  };

  return (
    <Card className="bg-card/50 backdrop-blur-sm border-primary/20">
      <CardHeader>
        <CardTitle className="text-center">🥚 Egg Shop 🥚</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {eggTypes.map((eggType) => (
            <Card key={eggType.id} className={`${rarityColors[eggType.rarity as keyof typeof rarityColors]}/10 border-2 border-${eggType.rarity}`}>
              <CardContent className="p-4 text-center">
                {detailedEggSprites[eggType.rarity as keyof typeof detailedEggSprites]}
                <h3 className="font-bold text-lg mb-2">{eggType.name}</h3>
                <Badge className={`mb-2 ${rarityColors[eggType.rarity as keyof typeof rarityColors]} text-white`}>
                  {eggType.rarity.toUpperCase()}
                </Badge>
                <p className="text-2xl font-bold text-gold-400 mb-3">
                  {eggType.price.toLocaleString()} $ITLOG
                </p>
                <p className="text-sm text-muted-foreground mb-4">
                  Hatch time: {Math.floor(eggType.hatch_time / 60)} minutes
                </p>
                <Button
                  onClick={() => handlePurchase(eggType.id)}
                  disabled={!profile || profile.itlog_tokens < eggType.price}
                  className={`w-full ${rarityGlow[eggType.rarity as keyof typeof rarityGlow]}`}
                >
                  {profile && profile.itlog_tokens >= eggType.price 
                    ? "Purchase" 
                    : "Insufficient Tokens"
                  }
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
        <div className="mt-4 text-center">
          <p className="text-lg">
            Your $ITLOG Balance: <span className="text-gold-400 font-bold">
              {profile?.itlog_tokens?.toLocaleString() || 0}
            </span>
          </p>
        </div>
      </CardContent>
    </Card>
  );
};

export default EggShop;
