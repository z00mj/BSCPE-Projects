import { useState } from "react";
import { Link, useLocation } from "wouter";
import { useAuth } from "@/hooks/use-auth";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";

export default function Register() {
  const [, setLocation] = useLocation();
  const { register, isAuthenticated, isRegisterLoading } = useAuth();
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  if (isAuthenticated) {
    setLocation("/");
    return null;
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (password !== confirmPassword) {
      alert("Passwords do not match");
      return;
    }
    
    register({ username, email, password });
  };

  return (
    <div className="min-h-screen bg-casino-black flex items-center justify-center px-4">
      <Card className="w-full max-w-md casino-card">
        <CardHeader className="text-center">
          <div className="mb-4">
            <h1 className="text-3xl font-bold text-casino-orange mb-2">BigBlackCoin</h1>
            <span className="text-casino-gold text-sm">$BBC Casino</span>
          </div>
          <CardTitle className="text-2xl text-white">Join the Casino</CardTitle>
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
              <Label htmlFor="email" className="text-gray-400">Email</Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
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
            
            <div className="space-y-2">
              <Label htmlFor="confirmPassword" className="text-gray-400">Confirm Password</Label>
              <Input
                id="confirmPassword"
                type="password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="bg-casino-black border-casino-orange/30 text-white focus:border-casino-orange"
                required
              />
            </div>
            
            <Button
              type="submit"
              className="w-full casino-button"
              disabled={isRegisterLoading}
            >
              {isRegisterLoading ? "Creating Account..." : "Create Account"}
            </Button>
          </form>
          
          <Separator className="bg-casino-orange/30" />
          
          <div className="text-center">
            <p className="text-gray-400 text-sm mb-4">Already have an account?</p>
            <Link href="/login">
              <Button
                type="button"
                variant="outline"
                className="casino-button-secondary"
              >
                Login Here
              </Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
