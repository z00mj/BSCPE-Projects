import { useState } from "react";
import { Link, useLocation } from "wouter";
import { useAuth } from "@/hooks/use-auth";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";

export default function Login() {
  const [, setLocation] = useLocation();
  const { login, adminLogin, isAuthenticated, isLoginLoading, isAdminLoginLoading } = useAuth();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [isAdminMode, setIsAdminMode] = useState(false);

  if (isAuthenticated) {
    setLocation("/");
    return null;
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isAdminMode) {
      adminLogin({ username, password });
    } else {
      login({ username, password });
    }
  };

  return (
    <div className="min-h-screen bg-casino-black flex items-center justify-center px-4">
      <Card className="w-full max-w-md casino-card">
        <CardHeader className="text-center">
          <div className="mb-4">
            <h1 className="text-3xl font-bold text-casino-orange mb-2">BigBlackCoin</h1>
            <span className="text-casino-gold text-sm">$BBC Casino</span>
          </div>
          <CardTitle className="text-2xl text-white">
            {isAdminMode ? "Admin Login" : "Welcome Back"}
          </CardTitle>
        </CardHeader>
        
        <CardContent className="space-y-6">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="username" className="text-gray-400">Username</Label>
              <Input
                id="username"
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                required
              />
            </div>
            
            <div className="space-y-2">
              <Label htmlFor="password" className="text-gray-400">Password</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                required
              />
            </div>
            
            <Button
              type="submit"
              className="w-full casino-button"
              disabled={isLoginLoading || isAdminLoginLoading}
            >
              {(isLoginLoading || isAdminLoginLoading) ? "Logging in..." : "Login"}
            </Button>
          </form>
          
          <div className="text-center">
            <Button
              type="button"
              variant="link"
              onClick={() => setIsAdminMode(!isAdminMode)}
              className="text-casino-orange hover:text-casino-red"
            >
              {isAdminMode ? "Switch to User Login" : "Admin Login"}
            </Button>
          </div>
          
          {!isAdminMode && (
            <>
              <Separator className="bg-casino-orange/30" />
              
              <div className="text-center">
                <p className="text-gray-400 text-sm mb-4">Don't have an account?</p>
                <Link href="/register">
                  <Button
                    type="button"
                    variant="outline"
                    className="casino-button-secondary"
                  >
                    Register Now
                  </Button>
                </Link>
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
