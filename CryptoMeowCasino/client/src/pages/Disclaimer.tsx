
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertTriangle } from "lucide-react";

export default function Disclaimer() {
  return (
    <div className="relative min-h-screen background-animated">
      <div className="relative z-20 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95">
          <CardHeader>
            <CardTitle className="text-3xl font-bold text-crypto-pink text-center flex items-center justify-center">
              <AlertTriangle className="w-8 h-8 mr-3" />
              Disclaimer
            </CardTitle>
            <p className="text-gray-400 text-center">Important Information - Please Read Carefully</p>
          </CardHeader>
          <CardContent className="prose prose-invert max-w-none">
            <div className="text-gray-300 space-y-6">
              <div className="bg-red-900/20 border border-red-500/30 rounded-lg p-4">
                <h3 className="text-lg font-semibold text-red-400 mb-2">⚠️ GAMBLING WARNING</h3>
                <p className="text-sm">
                  Gambling can be addictive and harmful. Only gamble with money you can afford to lose. 
                  If you think you may have a gambling problem, please seek professional help.
                </p>
              </div>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Age Restriction</h3>
                <p>This platform is strictly for individuals aged 18 years and older. By using CryptoMeow, you confirm that you meet this age requirement and are legally allowed to participate in gambling activities in your jurisdiction.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Financial Risk</h3>
                <p>Gambling involves financial risk. Key points to remember:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>You may lose all money deposited into your account</li>
                  <li>Past performance does not guarantee future results</li>
                  <li>Cryptocurrency values can be volatile</li>
                  <li>Never gamble money you cannot afford to lose</li>
                  <li>Set limits and stick to them</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Legal Compliance</h3>
                <p>It is your responsibility to ensure that:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Online gambling is legal in your jurisdiction</li>
                  <li>You comply with all local laws and regulations</li>
                  <li>You pay any applicable taxes on winnings</li>
                  <li>You understand the legal implications of cryptocurrency gambling</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Technical Risks</h3>
                <p>Please be aware of potential technical issues:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Internet connectivity issues may affect gameplay</li>
                  <li>Technical malfunctions may occur despite our best efforts</li>
                  <li>Cryptocurrency transactions may experience delays</li>
                  <li>We are not responsible for losses due to technical failures</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">No Investment Advice</h3>
                <p>CryptoMeow does not provide investment advice. The $MEOW token and all platform activities are for entertainment purposes only and should not be considered as investment opportunities.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Fair Play</h3>
                <p>While our games are provably fair and use cryptographic algorithms to ensure randomness, gambling inherently favors the house. The odds are always in favor of the platform, and players should expect to lose money over time.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Limitation of Liability</h3>
                <p>CryptoMeow disclaims all liability for:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Financial losses incurred through gambling</li>
                  <li>Technical issues or service interruptions</li>
                  <li>Cryptocurrency market fluctuations</li>
                  <li>User decisions and gambling behavior</li>
                  <li>Third-party services or payment processors</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Getting Help</h3>
                <p>If you believe you have a gambling problem, please reach out for help:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Contact local gambling addiction support services</li>
                  <li>Use our responsible gaming tools to set limits</li>
                  <li>Consider self-exclusion options</li>
                  <li>Speak with friends, family, or professionals</li>
                </ul>
              </section>

              <div className="bg-crypto-pink/10 border border-crypto-pink/30 rounded-lg p-4 text-center">
                <p className="text-crypto-pink font-semibold">
                  By using CryptoMeow, you acknowledge that you have read, understood, and agree to this disclaimer.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
