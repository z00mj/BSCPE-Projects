
import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { History } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import GameHistory from "./GameHistory";

interface MobileGameHistoryProps {
  gameType: string;
}

const MobileGameHistory = ({ gameType }: MobileGameHistoryProps) => {
  return (
    <div className="sm:hidden">
      <Sheet>
        <SheetTrigger asChild>
          <Button variant="outline" className="w-full mobile-button">
            <History className="w-4 h-4 mr-2" />
            View Game History
          </Button>
        </SheetTrigger>
        <SheetContent side="bottom" className="h-[70vh]">
          <SheetHeader>
            <SheetTitle>Game History</SheetTitle>
          </SheetHeader>
          <div className="mt-4 h-full overflow-hidden">
            <GameHistory gameType={gameType} maxHeight="100%" />
          </div>
        </SheetContent>
      </Sheet>
    </div>
  );
};

export default MobileGameHistory;
