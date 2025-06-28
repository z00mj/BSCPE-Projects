import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { BETTING_AMOUNTS } from '@/lib/game-mechanics';

interface BettingInterfaceProps {
  onBet: (amount: number) => void;
  isPlaying: boolean;
  maxBalance: number;
}

export default function BettingInterface({ onBet, isPlaying, maxBalance }: BettingInterfaceProps) {
  const [customAmount, setCustomAmount] = useState('');
  const [selectedAmount, setSelectedAmount] = useState<number | null>(null);

  const handleQuickBet = (amount: number) => {
    if (amount <= maxBalance && !isPlaying) {
      setSelectedAmount(amount);
      onBet(amount);
    }
  };

  const handleCustomBet = () => {
    const amount = parseFloat(customAmount);
    if (amount > 0 && amount <= maxBalance && !isPlaying) {
      setSelectedAmount(amount);
      onBet(amount);
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-400 mb-2">
          Bet Amount (Max: {maxBalance.toLocaleString()} coins)
        </label>
        <div className="grid grid-cols-4 gap-2">
          {BETTING_AMOUNTS.amounts.slice(0, 8).map((amount) => (
            <Button
              key={amount}
              variant="outline"
              size="sm"
              disabled={amount > maxBalance || isPlaying}
              onClick={() => handleQuickBet(amount)}
              className={`bg-casino-black border-casino-orange/30 text-white hover:border-casino-orange ${
                selectedAmount === amount ? 'border-casino-orange bg-casino-orange/20' : ''
              }`}
            >
              {amount}
            </Button>
          ))}
        </div>
      </div>
      
      <div className="flex items-center gap-2">
        <Input
          type="number"
          placeholder="Custom amount"
          value={customAmount}
          onChange={(e) => setCustomAmount(e.target.value)}
          disabled={isPlaying}
          className="flex-1 bg-casino-black border-casino-orange/30 text-white"
          max={maxBalance}
          min={0.25}
          step={0.25}
        />
        <Button
          onClick={handleCustomBet}
          disabled={!customAmount || parseFloat(customAmount) <= 0 || parseFloat(customAmount) > maxBalance || isPlaying}
          className="bg-casino-orange hover:bg-casino-red text-white px-6"
        >
          Bet
        </Button>
      </div>
    </div>
  );
}
