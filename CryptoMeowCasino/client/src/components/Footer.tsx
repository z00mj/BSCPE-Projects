
import { Link } from "wouter";
import { Card } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";

export default function Footer() {
  return (
    <footer className="relative z-20 mt-16 crypto-gray border-t border-crypto-pink/20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Brand Section */}
          <div className="md:col-span-1">
            <div className="flex items-center space-x-2 mb-4">
              <div className="w-8 h-8 gradient-pink rounded-lg flex items-center justify-center">
                <span className="text-white text-lg">🐱</span>
              </div>
              <h3 className="text-xl font-bold gradient-pink bg-clip-text text-transparent">
                CryptoMeow
              </h3>
            </div>
            <p className="text-gray-400 text-sm">
              The ultimate crypto casino experience where cats meet cryptocurrency in the most exciting way possible.
            </p>
          </div>

          {/* Quick Links */}
          <div>
            <h4 className="text-lg font-semibold text-crypto-pink mb-4">Quick Links</h4>
            <ul className="space-y-2">
              <li>
                <Link href="/" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Home
                </Link>
              </li>
              <li>
                <Link href="/casino" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Casino
                </Link>
              </li>
              <li>
                <Link href="/farm" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Cat Farm
                </Link>
              </li>
              <li>
                <Link href="/about" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  About Us
                </Link>
              </li>
            </ul>
          </div>

          {/* Legal */}
          <div>
            <h4 className="text-lg font-semibold text-crypto-pink mb-4">Legal</h4>
            <ul className="space-y-2">
              <li>
                <Link href="/privacy" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Privacy Policy
                </Link>
              </li>
              <li>
                <Link href="/terms" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Terms of Service
                </Link>
              </li>
              <li>
                <Link href="/disclaimer" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Disclaimer
                </Link>
              </li>
              <li>
                <Link href="/responsible-gaming" className="text-gray-300 hover:text-crypto-pink text-sm transition-colors">
                  Responsible Gaming
                </Link>
              </li>
            </ul>
          </div>

          {/* Important Notice */}
          <div>
            <h4 className="text-lg font-semibold text-crypto-pink mb-4">Important Notice</h4>
            <div className="text-gray-400 text-xs space-y-2">
              <p>🔞 Must be 18+ to play</p>
              <p>🎲 Gambling can be addictive</p>
              <p>⚖️ Play responsibly</p>
              <p>🔒 Provably fair games</p>
            </div>
          </div>
        </div>

        <Separator className="my-8 bg-crypto-pink/20" />

        {/* Bottom Section */}
        <div className="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
          <div className="text-gray-400 text-sm">
            © 2024 CryptoMeow. All rights reserved.
          </div>
          
          {/* Disclaimer */}
          <div className="text-gray-500 text-xs text-center md:text-right max-w-md">
            <p>
              Gambling involves risk. Please play responsibly and within your means. 
              CryptoMeow promotes responsible gaming.
            </p>
          </div>
        </div>

        {/* Additional Disclaimers */}
        <Card className="crypto-gray/50 border-crypto-pink/10 mt-6 p-4">
          <div className="text-gray-500 text-xs space-y-2">
            <p className="font-semibold text-gray-400">IMPORTANT DISCLAIMERS:</p>
            <p>
              • This platform is for entertainment purposes. Gambling can be addictive - please play responsibly.
            </p>
            <p>
              • You must be 18 years or older to participate. By using this service, you confirm you meet this requirement.
            </p>
            <p>
              • Cryptocurrency values can fluctuate. Never gamble more than you can afford to lose.
            </p>
            <p>
              • All games are provably fair and use cryptographic algorithms to ensure randomness.
            </p>
            <p>
              • We reserve the right to suspend accounts for suspicious activity or violation of terms.
            </p>
            <p>
              • If you have a gambling problem, please seek help from appropriate support services.
            </p>
          </div>
        </Card>
      </div>
    </footer>
  );
}
