import { useState, useEffect, useCallback } from "react";
import Layout from "@/components/Layout";
import { useBannedCheck } from "@/hooks/useBannedCheck";
import BannedOverlay from "@/components/BannedOverlay";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useToast } from "@/components/ui/use-toast";
import { useProfile } from "@/hooks/useProfile";
import { useActivityTracker } from "@/hooks/useActivityTracker";
import { useAuth } from "@/hooks/useAuth";
import { useQuestTracker } from "@/hooks/useQuestTracker";
import { usePetSystem } from "@/hooks/usePetSystem";
import { useGameHistory } from "@/hooks/useGameHistory";
import { useGameSounds } from "@/hooks/useGameSounds";
import GameHistory from "@/components/GameHistory";
import { Spade, Heart, Diamond, Club, Sparkles, TrendingUp, Target, Volume2 } from "lucide-react";

type CardType = {
  suit: string;
  value: string;
  numValue: number;
};

const Blackjack = () => {
  const [currentBet, setCurrentBet] = useState("10");
  const [gameStarted, setGameStarted] = useState(false);
  const [playerCards, setPlayerCards] = useState<CardType[]>([]);
  const [dealerCards, setDealerCards] = useState<CardType[]>([]);
  const [playerTotal, setPlayerTotal] = useState(0);
  const [dealerTotal, setDealerTotal] = useState(0);
  const [gameResult, setGameResult] = useState<string | null>(null);
  const [balance, setBalance] = useState(0);
  const [showDealerCards, setShowDealerCards] = useState(false);
  const { isBanned, reason } = useBannedCheck();
  const { toast } = useToast();
  const { profile, updateBalance } = useProfile();
  const { user } = useAuth();
  const { trackGamePlay: trackActivityGamePlay, trackBet: trackActivityBet, trackGameWin: trackActivityGameWin, trackGameLoss: trackActivityGameLoss, trackGameSession } = useActivityTracker();
  const [sessionId] = useState(`blackjack_session_${Date.now()}`);
  const [sessionStartTime] = useState(Date.now());
  const { trackGameWin, trackGamePlay, trackBet } = useQuestTracker();
  const { activePetBoosts } = usePetSystem();
  const { addHistoryEntry } = useGameHistory();
  const { playDiamondSound, playWinSound, playLossSound, audioEnabled, enableAudio } = useGameSounds();

  useEffect(() => {
    if (profile) {
      setBalance(profile.coins);
    }
  }, [profile]);

  const betAmounts = ["10", "25", "50", "100", "250", "500"];

  const suits = ["♠", "♥", "♦", "♣"];
  const values = ["A", "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K"];

  const createDeck = (): CardType[] => {
    const deck: CardType[] = [];
    suits.forEach(suit => {
      values.forEach(value => {
        let numValue = parseInt(value);
        if (value === "A") numValue = 11;
        else if (["J", "Q", "K"].includes(value)) numValue = 10;

        deck.push({ suit, value, numValue });
      });
    });
    return shuffleDeck(deck);
  };

  const shuffleDeck = (deck: CardType[]): CardType[] => {
    const shuffled = [...deck];
    for (let i = shuffled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }

    const luckBoost = activePetBoosts.find(boost => boost.trait_type === 'luck_boost');
    if (luckBoost && luckBoost.total_boost > 1.0) {
      const luckBias = luckBoost.total_boost - 1.0;
      const shouldApplyLuck = Math.random() < luckBias;

      if (shouldApplyLuck) {
        const goodCards = shuffled.filter(card => card.numValue === 11 || card.numValue === 10);
        const otherCards = shuffled.filter(card => card.numValue !== 11 && card.numValue !== 10);

        const goodCardsToMove = goodCards.slice(0, Math.min(3, goodCards.length));
        const remainingGoodCards = goodCards.slice(goodCardsToMove.length);

        const newDeck = [...otherCards, ...remainingGoodCards];
        goodCardsToMove.forEach((card, index) => {
          const playerPosition = index * 2;
          if (playerPosition < newDeck.length) {
            newDeck.splice(playerPosition, 0, card);
          } else {
            newDeck.unshift(card);
          }
        });

        return newDeck;
      }
    }

    return shuffled;
  };

  const calculateTotal = (cards: CardType[]): number => {
    let total = 0;
    let aces = 0;

    cards.forEach(card => {
      if (card.value === "A") {
        aces++;
        total += 11;
      } else {
        total += card.numValue;
      }
    });

    while (total > 21 && aces > 0) {
      total -= 10;
      aces--;
    }

    return total;
  };

  const dealCard = (deck: CardType[]): [CardType, CardType[]] => {
    const newDeck = [...deck];
    const card = newDeck.pop();
    return [card!, newDeck];
  };

  const startNewGame = async () => {
    const betAmount = parseFloat(currentBet);

    if (betAmount > balance) {
      toast({
        title: "Insufficient balance",
        description: "You don't have enough coins to place this bet.",
        variant: "destructive"
      });
      return;
    }

    // Play card dealing sound
    playDiamondSound();

    try {
      await updateBalance.mutateAsync({
        coinsChange: -betAmount
      });
      setBalance(prev => prev - betAmount);
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to place bet. Please try again.",
        variant: "destructive"
      });
      return;
    }

    const deck = createDeck();

    const [playerCard1, deck1] = dealCard(deck);
    const [dealerCard1, deck2] = dealCard(deck1);
    const [playerCard2, deck3] = dealCard(deck2);
    const [dealerCard2, newDeck] = dealCard(deck3);

    const initialPlayerCards = [playerCard1, playerCard2];
    const initialDealerCards = [dealerCard1, dealerCard2];

    setPlayerCards(initialPlayerCards);
    setDealerCards(initialDealerCards);
    setPlayerTotal(calculateTotal(initialPlayerCards));
    setDealerTotal(calculateTotal(initialDealerCards));
    setGameStarted(true);
    setGameResult(null);
    setShowDealerCards(false);

    const playerBJ = calculateTotal(initialPlayerCards) === 21;
    const dealerBJ = calculateTotal(initialDealerCards) === 21;

    if (playerBJ || dealerBJ) {
      setShowDealerCards(true);
      if (playerBJ && dealerBJ) {
        setGameResult("push");
        updateBalance.mutateAsync({
          coinsChange: betAmount
        }).then(() => {
          setBalance(prev => prev + betAmount);
          toast({
            title: "Push!",
            description: "Both have blackjack. Bet returned."
          });
        }).catch(() => {
          toast({
            title: "Error updating balance",
            description: "Please contact support.",
            variant: "destructive"
          });
        });
      } else if (playerBJ) {
        // Play win sound for blackjack
        playWinSound();
        
        const winnings = betAmount * 2.5;
        setGameResult("blackjack");
        updateBalance.mutateAsync({
          coinsChange: winnings
        }).then(() => {
          setBalance(prev => prev + winnings);
          trackGameWin(winnings, 'blackjack');
          trackActivityGameWin('blackjack', winnings, sessionId);
          addHistoryEntry({
            game_type: 'blackjack',
            bet_amount: betAmount,
            result_type: 'win',
            win_amount: winnings,
            loss_amount: 0,
            multiplier: 2.5,
            game_details: { result: 'blackjack', playerCards: initialPlayerCards, dealerCards: initialDealerCards }
          });
          toast({
            title: "Blackjack!",
            description: `You won ${winnings.toFixed(2)} coins with a natural blackjack!`
          });
        }).catch(() => {
          toast({
            title: "Error updating balance",
            description: "Please contact support.",
            variant: "destructive"
          });
        });
      } else {
        // Play loss sound for dealer blackjack
        playLossSound();
        
        setGameResult("dealer_blackjack");
        trackActivityGameLoss('blackjack', betAmount, sessionId);
        addHistoryEntry({
          game_type: 'blackjack',
          bet_amount: betAmount,
          result_type: 'loss',
          win_amount: 0,
          loss_amount: betAmount,
          multiplier: 0,
          game_details: { result: 'dealer_blackjack', playerCards: initialPlayerCards, dealerCards: initialDealerCards }
        });
        toast({
          title: "Dealer Blackjack",
          description: "Dealer has blackjack. You lose.",
          variant: "destructive"
        });
      }
      setGameStarted(false);
    } else {
      const betAmountNumber = parseFloat(currentBet);
      trackBet(betAmountNumber, 'blackjack');
      trackGamePlay('blackjack');
      trackActivityBet('blackjack', betAmountNumber, sessionId);
      trackActivityGamePlay('blackjack', sessionId);
    }
  };

  const hit = () => {
    if (!gameStarted) return;

    // Play card dealing sound
    playDiamondSound();

    const deck = createDeck();
    const [newCard] = dealCard(deck);
    const newPlayerCards = [...playerCards, newCard];
    const newTotal = calculateTotal(newPlayerCards);

    setPlayerCards(newPlayerCards);
    setPlayerTotal(newTotal);

    if (newTotal > 21) {
      // Play loss sound for bust
      playLossSound();
      
      const betAmount = parseFloat(currentBet);
      setGameResult("bust");
      setGameStarted(false);
      setShowDealerCards(true);
      trackActivityGameLoss('blackjack', betAmount, sessionId);
      addHistoryEntry({
        game_type: 'blackjack',
        bet_amount: betAmount,
        result_type: 'loss',
        win_amount: 0,
        loss_amount: betAmount,
        multiplier: 0,
        game_details: { result: 'bust', playerTotal: newTotal, playerCards: newPlayerCards }
      });
      toast({
        title: "Bust!",
        description: "You went over 21. You lose.",
        variant: "destructive"
      });
    }
  };

  const stand = () => {
    if (!gameStarted) return;

    setShowDealerCards(true);
    let currentDealerCards = [...dealerCards];
    let currentDealerTotal = dealerTotal;

    while (currentDealerTotal < 17) {
      // Play card dealing sound for dealer
      playDiamondSound();
      
      const deck = createDeck();
      const [newCard] = dealCard(deck);
      currentDealerCards = [...currentDealerCards, newCard];
      currentDealerTotal = calculateTotal(currentDealerCards);
    }

    setDealerCards(currentDealerCards);
    setDealerTotal(currentDealerTotal);

    const betAmount = parseFloat(currentBet);
    if (currentDealerTotal > 21) {
      // Play win sound for dealer bust
      playWinSound();
      
      setGameResult("dealer_bust");
      const winnings = betAmount * 2;
      updateBalance.mutateAsync({
        coinsChange: winnings
      }).then(() => {
        setBalance(prev => prev + winnings);
        trackGameWin(winnings, 'blackjack');
        trackActivityGameWin('blackjack', winnings, sessionId);
        addHistoryEntry({
          game_type: 'blackjack',
          bet_amount: betAmount,
          result_type: 'win',
          win_amount: winnings,
          loss_amount: 0,
          multiplier: 2,
          game_details: { result: 'dealer_bust', playerTotal, dealerTotal: currentDealerTotal, dealerCards: currentDealerCards }
        });
        toast({
          title: "Dealer Bust!",
          description: `Dealer went over 21. You won ${winnings.toFixed(2)} coins!`
        });
      }).catch(() => {
        toast({
          title: "Error updating balance",
          description: "Please contact support.",
          variant: "destructive"
        });
      });
    } else if (playerTotal > currentDealerTotal) {
      // Play win sound
      playWinSound();
      
      setGameResult("win");
      const winnings = betAmount * 2;
      updateBalance.mutateAsync({
        coinsChange: winnings
      }).then(() => {
        setBalance(prev => prev + winnings);
        trackGameWin(winnings, 'blackjack');
        trackActivityGameWin('blackjack', winnings, sessionId);
        addHistoryEntry({
          game_type: 'blackjack',
          bet_amount: betAmount,
          result_type: 'win',
          win_amount: winnings,
          loss_amount: 0,
          multiplier: 2,
          game_details: { result: 'win', playerTotal, dealerTotal: currentDealerTotal, dealerCards: currentDealerCards }
        });
        toast({
          title: "You Win!",
          description: `You beat the dealer! Won ${winnings.toFixed(2)} coins!`
        });
      }).catch(() => {
        toast({
          title: "Error updating balance",
          description: "Please contact support.",
          variant: "destructive"
        });
      });
    } else if (playerTotal < currentDealerTotal) {
      // Play loss sound
      playLossSound();
      
      setGameResult("lose");
      trackActivityGameLoss('blackjack', betAmount, sessionId);
      addHistoryEntry({
        game_type: 'blackjack',
        bet_amount: betAmount,
        result_type: 'loss',
        win_amount: 0,
        loss_amount: betAmount,
        multiplier: 0,
        game_details: { result: 'lose', playerTotal, dealerTotal: currentDealerTotal, dealerCards: currentDealerCards }
      });
      toast({
        title: "You Lose",
        description: "Dealer has a higher hand.",
        variant: "destructive"
      });
    } else {
      setGameResult("push");
      updateBalance.mutateAsync({
        coinsChange: betAmount
      }).then(() => {
        setBalance(prev => prev + betAmount);
        trackGameWin(betAmount, 'blackjack');
        trackActivityGameWin('blackjack', betAmount, sessionId);
        addHistoryEntry({
          game_type: 'blackjack',
          bet_amount: betAmount,
          result_type: 'push',
          win_amount: betAmount,
          loss_amount: 0,
          multiplier: 1,
          game_details: { result: 'push', playerTotal, dealerTotal: currentDealerTotal, dealerCards: currentDealerCards }
        });
        toast({
          title: "Push!",
          description: "Same total. Bet returned."
        });
      }).catch(() => {
        toast({
          title: "Error updating balance",
          description: "Please contact support.",
          variant: "destructive"
        });
      });
    }

    setGameStarted(false);
  };

  const renderCard = (card: CardType, hidden = false) => {
    if (hidden) {
      return (
        <div className="w-20 h-28 sm:w-24 sm:h-32 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl border-2 border-blue-400 flex items-center justify-center shadow-lg">
          <div className="text-white text-3xl font-bold">?</div>
        </div>
      );
    }

    const isRed = card.suit === "♥" || card.suit === "♦";
    return (
      <div className="w-20 h-28 sm:w-24 sm:h-32 bg-gradient-to-br from-white to-gray-100 rounded-xl border-2 border-gray-300 flex flex-col items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300">
        <span className={`text-lg sm:text-xl font-bold ${isRed ? "text-red-500" : "text-black"}`}>
          {card.value}
        </span>
        <span className={`text-2xl sm:text-3xl ${isRed ? "text-red-500" : "text-black"}`}>
          {card.suit}
        </span>
      </div>
    );
  };

  useEffect(() => {
    return () => {
      if (user) {
        const sessionDuration = Math.floor((Date.now() - sessionStartTime) / 1000);
        trackGameSession('blackjack', sessionDuration, sessionId);
      }
    };
  }, [user, trackGameSession, sessionId, sessionStartTime]);

  return (
    <Layout>
      {isBanned && <BannedOverlay reason={reason} />}
      <div className="casino-game-container py-8">
        <div className="responsive-container">
          <div className="casino-game-header">
            <h1 className="casino-game-title">
              Blackjack
            </h1>
            <p className="casino-game-subtitle">
              Get as close to 21 as possible without going over!
            </p>
            {!audioEnabled && (
              <Button 
                onClick={enableAudio} 
                className="mt-4 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-6 py-3 rounded-lg font-bold shadow-lg"
              >
                <Volume2 className="w-5 h-5 mr-2" />
                Enable Sound Effects
              </Button>
            )}
          </div>

          <div className="responsive-grid">
            <div className="responsive-game-grid">
              <div className="casino-game-area">
                <h2 className="casino-game-area-title">Blackjack Table</h2>
                
                <div className="space-y-8">
                  <div className="text-center">
                    <h3 className="text-xl font-bold mb-4 text-gray-300">
                      Dealer's Hand {showDealerCards && `(${dealerTotal})`}
                    </h3>
                    <div className="flex justify-center space-x-3 mb-6">
                      {dealerCards.map((card, index) => (
                        <div key={index} className="transform hover:scale-105 transition-transform duration-200">
                          {renderCard(card, index === 1 && !showDealerCards)}
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="text-center">
                    <h3 className="text-xl font-bold mb-4 text-white">
                      Your Hand ({playerTotal})
                    </h3>
                    <div className="flex justify-center space-x-3 mb-6">
                      {playerCards.map((card, index) => (
                        <div key={index} className="transform hover:scale-105 transition-transform duration-200">
                          {renderCard(card)}
                        </div>
                      ))}
                    </div>
                  </div>

                  {gameResult && (
                    <div className="text-center mb-6">
                      <Badge 
                        className={`text-xl px-8 py-4 rounded-full font-bold shadow-lg ${
                          ["win", "blackjack", "dealer_bust"].includes(gameResult) 
                            ? "bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-emerald-500/50" 
                            : gameResult === "push" 
                            ? "bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-blue-500/50" 
                            : "bg-gradient-to-r from-red-500 to-pink-600 text-white shadow-red-500/50"
                        }`}
                      >
                        {gameResult === "win" && "You Win!"}
                        {gameResult === "lose" && "You Lose"}
                        {gameResult === "push" && "Push"}
                        {gameResult === "bust" && "Bust!"}
                        {gameResult === "blackjack" && "Blackjack!"}
                        {gameResult === "dealer_bust" && "Dealer Bust!"}
                        {gameResult === "dealer_blackjack" && "Dealer Blackjack"}
                      </Badge>
                    </div>
                  )}

                  <div className="flex justify-center space-x-4">
                    {!gameStarted ? (
                      <Button 
                        onClick={startNewGame} 
                        className="casino-primary-button max-w-xs"
                      >
                        Deal Cards ({currentBet} coins)
                      </Button>
                    ) : (
                      <div className="flex flex-col sm:flex-row gap-4">
                        <Button 
                          onClick={hit} 
                          className="casino-secondary-button max-w-xs"
                          disabled={playerTotal >= 21}
                        >
                          Hit
                        </Button>
                        <Button 
                          onClick={stand} 
                          className="casino-danger-button max-w-xs"
                        >
                          Stand
                        </Button>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              <GameHistory gameType="blackjack" maxHeight="400px" />
            </div>

            <div className="responsive-control-panel">
              <div className="casino-balance-card">
                <p className="casino-balance-label">Coins Balance</p>
                <p className="casino-balance-amount">{balance.toFixed(2)}</p>
              </div>

              {activePetBoosts.find(boost => boost.trait_type === 'luck_boost') && (
                <div className="casino-luck-boost-card">
                  <div className="casino-luck-icon">
                    <Sparkles className="text-white font-bold text-xl" />
                  </div>
                  <p className="text-lg font-bold mb-2 text-emerald-400">Luck Boost Active!</p>
                  <p className="text-sm text-gray-300">
                    +{(((activePetBoosts.find(boost => boost.trait_type === 'luck_boost')?.total_boost || 1) - 1) * 100).toFixed(1)}% Better odds!
                  </p>
                </div>
              )}

              <div className="casino-settings-card">
                <h3 className="casino-settings-title">
                  <Target className="w-6 h-6 inline mr-2" />
                  Bet Amount
                </h3>
                <Select value={currentBet} onValueChange={setCurrentBet} disabled={gameStarted}>
                  <SelectTrigger className="casino-select">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {betAmounts.map(amount => (
                      <SelectItem key={amount} value={amount}>{amount} coins</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="casino-stats-card">
                <h3 className="casino-stats-title">
                  <TrendingUp className="w-5 h-5 inline mr-2" />
                  Game Rules
                </h3>
                <div className="space-y-3 text-sm">
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Goal</span>
                    <span className="casino-stat-value">Get to 21</span>
                  </div>
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Face Cards</span>
                    <span className="casino-stat-value">Worth 10</span>
                  </div>
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Aces</span>
                    <span className="casino-stat-value">11 or 1</span>
                  </div>
                  <div className="casino-stat-row">
                    <span className="casino-stat-label">Dealer Stands</span>
                    <span className="casino-stat-value">On 17</span>
                  </div>
                </div>
              </div>

              <div className="casino-payout-card">
                <h3 className="casino-payout-title">Payouts</h3>
                <div className="space-y-3">
                  <div className="casino-payout-row">
                    <span>Blackjack</span>
                    <span className="casino-payout-multiplier">2.5x</span>
                  </div>
                  <div className="casino-payout-row">
                    <span>Regular Win</span>
                    <span className="casino-payout-multiplier">2x</span>
                  </div>
                  <div className="casino-payout-row">
                    <span>Push</span>
                    <span className="text-blue-400 font-bold">Bet Returned</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
};

export default Blackjack;
