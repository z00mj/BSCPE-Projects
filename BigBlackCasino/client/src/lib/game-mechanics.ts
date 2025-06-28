export interface BetResult {
  winAmount: string;
  bbcWon: string;
  gameData: any;
}

export interface GameBetAmounts {
  amounts: number[];
}

export const BETTING_AMOUNTS: GameBetAmounts = {
  amounts: [0.25, 0.50, 1.00, 1.50, 2.00, 5.00, 10.00, 50.00, 100.00, 500.00, 1000.00]
};

export class LuckAndRollGame {
  private static WHEEL_SLICES = [
    { type: 'bankrupt', multiplier: 0, count: 6 },
    { type: 'multiplier', multiplier: 1.1, count: 1 },
    { type: 'multiplier', multiplier: 1.3, count: 1 },
    { type: 'multiplier', multiplier: 1.5, count: 1 },
    { type: 'multiplier', multiplier: 1.8, count: 1 },
    { type: 'multiplier', multiplier: 2.0, count: 1 },
    { type: 'multiplier', multiplier: 4.0, count: 1 },
    { type: 'multiplier', multiplier: 5.0, count: 1 },
    { type: 'multiplier', multiplier: 8.0, count: 1 },
    { type: 'multiplier', multiplier: 10.0, count: 1 },
    { type: 'jackpot', multiplier: 0, bbcMultiplier: 0.05, count: 1 }
  ];
  private static lastJackpotTimestamp: number = 0;

  static spin(betAmount: number): BetResult {
    const totalSlices = 16;
    let randomSlice = Math.floor(Math.random() * totalSlices);
    let currentIndex = 0;
    let result = null;
    for (const slice of this.WHEEL_SLICES) {
      if (randomSlice >= currentIndex && randomSlice < currentIndex + slice.count) {
        result = slice;
        break;
      }
      currentIndex += slice.count;
    }
    if (!result) result = this.WHEEL_SLICES[0]; // fallback
    let winAmount = 0;
    let bbcWon = 0;
    // Enforce no consecutive jackpots
    const now = Date.now();
    if (result.type === 'jackpot') {
      if (now - this.lastJackpotTimestamp < 60000) {
        // If jackpot was won less than 1 min ago, reroll to a multiplier
        result = this.WHEEL_SLICES.find(s => s.type === 'multiplier') || this.WHEEL_SLICES[2];
      } else {
        this.lastJackpotTimestamp = now;
      }
    }
    if (result.type === 'multiplier') {
      winAmount = betAmount * result.multiplier;
    } else if (result.type === 'jackpot') {
      bbcWon = betAmount * (result.bbcMultiplier || 0);
    }
    return {
      winAmount: winAmount.toString(),
      bbcWon: bbcWon.toString(),
      gameData: {
        sliceIndex: randomSlice,
        sliceType: result.type,
        multiplier: result.multiplier,
        bbcMultiplier: result.bbcMultiplier
      }
    };
  }
}

export class FlipItJonathanGame {
  static flip(betAmount: number, choice: 'heads' | 'tails', currentStreak: number = 0): BetResult {
    // Biased RNG: 48% win chance
    const isCorrect = Math.random() < 0.48;
    const playerWins = (choice === 'heads' && isCorrect) || (choice === 'tails' && !isCorrect);
    let winAmount = 0;
    let bbcWon = 0;
    let newStreak = 0;
    // Non-linear multiplier curve
    const multipliers = [1.5, 2.2, 3.5, 5.0, 7.5, 12.0];
    if (playerWins) {
      newStreak = currentStreak + 1;
      const multiplier = multipliers[Math.min(newStreak - 1, multipliers.length - 1)];
      winAmount = betAmount * multiplier;
      // BBC chance increases with streak
      const bbcChance = Math.min(0.1, 0.01 * newStreak);
      if (Math.random() < bbcChance) {
        bbcWon = betAmount * 0.02;
      }
    }
    // Max 6 flips per round
    if (newStreak > 6) {
      newStreak = 6;
    }
    return {
      winAmount: winAmount.toString(),
      bbcWon: bbcWon.toString(),
      gameData: {
        choice,
        result: isCorrect ? 'heads' : 'tails',
        playerWins,
        streak: newStreak,
        multiplier: playerWins ? multipliers[Math.min(newStreak - 1, multipliers.length - 1)] : 0
      }
    };
  }
}

export class PaldoGame {
  private static SYMBOLS = [
    { name: 'heart', value: 1, weight: 30 },
    { name: 'diamond', value: 2, weight: 25 },
    { name: 'club', value: 3, weight: 20 },
    { name: 'spade', value: 4, weight: 15 },
    { name: 'ace', value: 10, weight: 8 },
    { name: 'wild', value: 0, weight: 1, isWild: true },
    { name: 'scatter', value: 0, weight: 1, isScatter: true }
  ];
  static spin(betAmount: number): BetResult {
    const reels = [];
    // Generate 5 reels with 3 symbols each
    for (let reel = 0; reel < 5; reel++) {
      const reelSymbols = [];
      for (let row = 0; row < 3; row++) {
        reelSymbols.push(this.getRandomSymbol());
      }
      reels.push(reelSymbols);
    }
    // Check for wins
    let totalWin = 0;
    let bbcWon = 0;
    const scatterCount = this.countScatters(reels);
    let freeSpins = 0;
    // Check paylines (horizontal lines)
    for (let row = 0; row < 3; row++) {
      const line = reels.map(reel => reel[row]);
      const lineWin = this.calculateLineWin(line, betAmount);
      totalWin += lineWin;
    }
    // Scatter bonus
    if (scatterCount >= 3) {
      freeSpins = scatterCount === 3 ? 10 : scatterCount === 4 ? 15 : 20;
      totalWin += betAmount * scatterCount;
      // BBC chance on free spins trigger
      if (Math.random() < 0.15) {
        bbcWon = betAmount * 0.03;
      }
    }
    // Progressive jackpot (5 scatters on max bet)
    if (scatterCount === 5 && betAmount >= 10) {
      bbcWon += 0.5;
    }
    return {
      winAmount: totalWin.toString(),
      bbcWon: bbcWon.toString(),
      gameData: {
        reels,
        scatterCount,
        freeSpins,
        totalWin
      }
    };
  }

  private static getRandomSymbol() {
    const totalWeight = this.SYMBOLS.reduce((sum, symbol) => sum + symbol.weight, 0);
    let random = Math.random() * totalWeight;
    
    for (const symbol of this.SYMBOLS) {
      random -= symbol.weight;
      if (random <= 0) {
        return symbol;
      }
    }
    
    return this.SYMBOLS[0]; // fallback
  }

  private static countScatters(reels: any[][]): number {
    let count = 0;
    for (const reel of reels) {
      for (const symbol of reel) {
        if (symbol.isScatter) count++;
      }
    }
    return count;
  }

  private static calculateLineWin(line: any[], betAmount: number): number {
    // Simple win calculation - 3+ matching symbols
    const firstSymbol = line[0];
    if (firstSymbol.isScatter || firstSymbol.isWild) return 0;
    
    let matchCount = 1;
    for (let i = 1; i < line.length; i++) {
      if (line[i].name === firstSymbol.name || line[i].isWild) {
        matchCount++;
      } else {
        break;
      }
    }
    
    if (matchCount >= 3) {
      return betAmount * firstSymbol.value * (matchCount - 2);
    }
    
    return 0;
  }
}

export class IpisSipiGame {
  // 30 steps, multiplier grows to 50x, hazard chance increases per step
  private static STEPS = Array.from({ length: 30 }, (_, i) => {
    // Multiplier: exponential growth to reach 50x at step 30
    const multiplier = 1.2 * Math.pow(50 / 1.2, i / 29); // 1.2x at step 1, 50x at step 30
    // Hazard: starts at 5%, grows to 60% at step 30
    const hazardChance = 0.05 + (0.60 - 0.05) * (i / 29);
    return { multiplier, hazardChance };
  });

  static step(betAmount: number, currentStep: number): BetResult {
    if (currentStep >= this.STEPS.length) {
      // Final step reached - maximum reward + BBC
      return {
        winAmount: (betAmount * 50).toFixed(2),
        bbcWon: (betAmount * 0.2).toFixed(2),
        gameData: {
          step: currentStep,
          survived: true,
          finalStep: true,
          multiplier: 50
        }
      };
    }
    const stepData = this.STEPS[currentStep];
    const hitHazard = Math.random() < stepData.hazardChance;
    if (hitHazard) {
      return {
        winAmount: '0',
        bbcWon: '0',
        gameData: {
          step: currentStep,
          survived: false,
          hazardHit: true,
          multiplier: 0
        }
      };
    }
    // Survived this step
    return {
      winAmount: (betAmount * stepData.multiplier).toFixed(2),
      bbcWon: '0',
      gameData: {
        step: currentStep,
        survived: true,
        multiplier: stepData.multiplier
      }
    };
  }
}

export class BlowItBolimsGame {
  static inflate(betAmount: number): BetResult {
    // Random pop point between 5s and 20s (simulate as multiplier 1.1x to 20x)
    const popPoint = 1.1 + Math.random() * 18.9;
    const currentMultiplier = 1.0;
    // Bonus balloon: up to 30s burst, 10% chance
    const isBonusBalloon = Math.random() < 0.1;
    const maxMultiplier = isBonusBalloon ? 30 : 20;
    let bbcWon = 0;
    if (isBonusBalloon && Math.random() < 0.2) { // 20% chance on bonus balloon
      bbcWon = betAmount * (0.1 + Math.random() * 0.2); // 0.1x-0.3x
    }
    return {
      winAmount: '0', // Player needs to cash out
      bbcWon: bbcWon.toString(),
      gameData: {
        popPoint,
        currentMultiplier,
        isBonusBalloon,
        maxMultiplier,
        status: 'inflating'
      }
    };
  }
  static cashOut(betAmount: number, multiplier: number, gameData: any): BetResult {
    if (multiplier >= gameData.popPoint) {
      // Balloon popped - no win
      return {
        winAmount: '0',
        bbcWon: '0',
        gameData: {
          ...gameData,
          status: 'popped',
          finalMultiplier: multiplier
        }
      };
    }
    // Successful cash out
    const winAmount = betAmount * multiplier;
    let bbcWon = 0;
    if (gameData.isBonusBalloon && multiplier > 10) {
      bbcWon = betAmount * (0.1 + Math.random() * 0.2); // 0.1x-0.3x
    }
    return {
      winAmount: winAmount.toString(),
      bbcWon: bbcWon.toString(),
      gameData: {
        ...gameData,
        status: 'cashed_out',
        finalMultiplier: multiplier
      }
    };
  }
}
