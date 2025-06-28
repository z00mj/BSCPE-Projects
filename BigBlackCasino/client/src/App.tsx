import { Switch, Route, Redirect } from "wouter";
import { queryClient } from "./lib/queryClient";
import { QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { useAuth } from "@/hooks/use-auth";
import NotFound from "@/pages/not-found";
import Login from "@/pages/login";
import Register from "@/pages/register";
import Dashboard from "@/pages/dashboard";
import Games from "@/pages/games";
import Wallet from "@/pages/wallet";
import Mining from "@/pages/mining";
import Admin from "@/pages/admin";
import LuckAndRoll from "@/pages/games/luck-and-roll";
import FlipItJonathan from "@/pages/games/flip-it-jonathan";
import Paldo from "@/pages/games/paldo";
import IpisSipi from "@/pages/games/ipis-sipi";
import BlowItBolims from "@/pages/games/blow-it-bolims";
import Navbar from "@/components/layout/navbar";

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();
  
  if (isLoading) {
    return (
      <div className="min-h-screen bg-casino-black flex items-center justify-center">
        <div className="text-casino-orange text-xl">Loading...</div>
      </div>
    );
  }
  
  if (!isAuthenticated) {
    return <Redirect to="/login" />;
  }
  
  return <>{children}</>;
}

function Router() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen bg-casino-black flex items-center justify-center">
        <div className="text-casino-orange text-xl">Loading...</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-casino-black">
      {isAuthenticated && <Navbar />}
      
      <Switch>
        <Route path="/login" component={Login} />
        <Route path="/register" component={Register} />
        
        <Route path="/">
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        </Route>
        
        <Route path="/games">
          <ProtectedRoute>
            <Games />
          </ProtectedRoute>
        </Route>
        
        <Route path="/games/luck-and-roll">
          <ProtectedRoute>
            <LuckAndRoll />
          </ProtectedRoute>
        </Route>
        
        <Route path="/games/flip-it-jonathan">
          <ProtectedRoute>
            <FlipItJonathan />
          </ProtectedRoute>
        </Route>
        
        <Route path="/games/paldo">
          <ProtectedRoute>
            <Paldo />
          </ProtectedRoute>
        </Route>
        
        <Route path="/games/ipis-sipi">
          <ProtectedRoute>
            <IpisSipi />
          </ProtectedRoute>
        </Route>
        
        <Route path="/games/blow-it-bolims">
          <ProtectedRoute>
            <BlowItBolims />
          </ProtectedRoute>
        </Route>
        
        <Route path="/wallet">
          <ProtectedRoute>
            <Wallet />
          </ProtectedRoute>
        </Route>
        
        <Route path="/mining">
          <ProtectedRoute>
            <Mining />
          </ProtectedRoute>
        </Route>
        
        <Route path="/admin">
          <ProtectedRoute>
            <Admin />
          </ProtectedRoute>
        </Route>
        
        <Route component={NotFound} />
      </Switch>
    </div>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <Toaster />
        <Router />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
