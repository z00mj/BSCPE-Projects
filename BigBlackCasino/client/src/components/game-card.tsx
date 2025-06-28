import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Link } from "wouter";

interface GameCardProps {
  title: string;
  description: string;
  icon: React.ReactNode;
  badge?: string;
  badgeColor?: string;
  minBet?: string;
  maxBet?: string;
  href: string;
  gradient: string;
}

export default function GameCard({
  title,
  description,
  icon,
  badge,
  badgeColor = "bg-casino-orange",
  minBet,
  maxBet,
  href,
  gradient
}: GameCardProps) {
  return (
    <Card className="casino-card overflow-hidden group">
      <div className="relative">
        <div className={`h-48 ${gradient} flex items-center justify-center`}>
          {icon}
        </div>
        {badge && (
          <div className={`absolute top-4 right-4 ${badgeColor} text-white px-2 py-1 rounded-full text-xs font-bold`}>
            {badge}
          </div>
        )}
      </div>
      <CardContent className="p-6">
        <h3 className="text-xl font-bold text-white mb-2">{title}</h3>
        <p className="text-gray-400 text-sm mb-4">{description}</p>
        <div className="flex items-center justify-between">
          {(minBet || maxBet) && (
            <div className="text-xs text-gray-400">
              Min: {minBet} • Max: {maxBet}
            </div>
          )}
          <Link href={href}>
            <Button className="casino-button">
              Play Now
            </Button>
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}
