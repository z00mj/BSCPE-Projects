import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { ThemeProvider } from "next-themes";
import { AuthProvider } from "@/hooks/useAuth";
import ProtectedRoute from "@/components/ProtectedRoute";
import Index from "./pages/Index";
import NotFound from "./pages/NotFound";
import Games from "./pages/Games";
import Mines from "./pages/games/Mines";
import WheelOfFortune from "./pages/games/WheelOfFortune";
import FortuneReels from "./pages/games/FortuneReels";
import Blackjack from "./pages/games/Blackjack";
import DiceRoll from "./pages/games/DiceRoll";
import UserProfile from "./pages/UserProfile";
import Wallet from "./pages/Wallet";
import Earn from "./pages/Earn";
import Deposit from "./pages/Deposit";
import Auth from "./pages/Auth";
import Admin from "./pages/Admin";
import Appeal from "./pages/Appeal";

const queryClient = new QueryClient();

const App = () => (
  <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false}>
    <AuthProvider>
      <QueryClientProvider client={queryClient}>
        <TooltipProvider>
          <BrowserRouter>
            <Routes>
              <Route path="/auth" element={<Auth />} />
              <Route path="/" element={
                <ProtectedRoute>
                  <Index />
                </ProtectedRoute>
              } />
              <Route path="/games" element={
                <ProtectedRoute>
                  <Games />
                </ProtectedRoute>
              } />
              <Route path="/games/mines" element={
                <ProtectedRoute>
                  <Mines />
                </ProtectedRoute>
              } />
              <Route path="/games/wheel" element={
                <ProtectedRoute>
                  <WheelOfFortune />
                </ProtectedRoute>
              } />
              <Route path="/games/slots" element={
                <ProtectedRoute>
                  <FortuneReels />
                </ProtectedRoute>
              } />
              <Route path="/games/blackjack" element={
                <ProtectedRoute>
                  <Blackjack />
                </ProtectedRoute>
              } />
              <Route path="/games/dice" element={
                <ProtectedRoute>
                  <DiceRoll />
                </ProtectedRoute>
              } />
              <Route path="/profile" element={
                <ProtectedRoute>
                  <UserProfile />
                </ProtectedRoute>
              } />
              <Route path="/wallet" element={
                <ProtectedRoute>
                  <Wallet />
                </ProtectedRoute>
              } />
              <Route path="/earn" element={
                <ProtectedRoute>
                  <Earn />
                </ProtectedRoute>
              } />
              <Route path="/deposit" element={
                <ProtectedRoute>
                  <Deposit />
                </ProtectedRoute>
              } />
              <Route path="/admin" element={
                <ProtectedRoute requireAdmin={true}>
                  <Admin />
                </ProtectedRoute>
              } />
              <Route path="/appeal" element={
                <ProtectedRoute>
                  <Appeal />
                </ProtectedRoute>
              } />
              <Route path="*" element={<NotFound />} />
            </Routes>
          </BrowserRouter>
          <Toaster />
        </TooltipProvider>
      </QueryClientProvider>
    </AuthProvider>
  </ThemeProvider>
);

export default App;