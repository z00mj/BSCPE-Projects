
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function Terms() {
  return (
    <div className="relative min-h-screen background-animated">
      <div className="relative z-20 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95">
          <CardHeader>
            <CardTitle className="text-3xl font-bold text-crypto-pink text-center">
              Terms of Service
            </CardTitle>
            <p className="text-gray-400 text-center">Last updated: {new Date().toLocaleDateString()}</p>
          </CardHeader>
          <CardContent className="prose prose-invert max-w-none">
            <div className="text-gray-300 space-y-6">
              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">1. Acceptance of Terms</h3>
                <p>By accessing and using CryptoMeow, you agree to be bound by these Terms of Service and all applicable laws and regulations.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">2. Eligibility</h3>
                <p>You must be at least 18 years old to use this service. By using CryptoMeow, you represent that you meet this age requirement and have the legal capacity to enter into this agreement.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">3. Account Registration</h3>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>You must provide accurate information when creating an account</li>
                  <li>You are responsible for maintaining the security of your account</li>
                  <li>One account per person is allowed</li>
                  <li>We reserve the right to suspend or terminate accounts for violations</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">4. Gaming Rules</h3>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>All games are provably fair and use cryptographic algorithms</li>
                  <li>Game results are final and cannot be changed</li>
                  <li>We reserve the right to void bets in case of technical errors</li>
                  <li>Maximum bet limits may apply to certain games</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">5. Deposits and Withdrawals</h3>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>All transactions are processed securely</li>
                  <li>Minimum deposit and withdrawal amounts may apply</li>
                  <li>We reserve the right to verify transactions for security</li>
                  <li>Processing times may vary depending on payment method</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">6. Prohibited Activities</h3>
                <p>The following activities are strictly prohibited:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Using bots or automated systems</li>
                  <li>Colluding with other players</li>
                  <li>Attempting to exploit technical vulnerabilities</li>
                  <li>Money laundering or fraudulent activities</li>
                  <li>Creating multiple accounts</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">7. Responsible Gaming</h3>
                <p>We promote responsible gaming and encourage players to:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Set limits on their gaming activities</li>
                  <li>Never gamble more than they can afford to lose</li>
                  <li>Seek help if gambling becomes a problem</li>
                  <li>Take regular breaks from gaming</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">8. Limitation of Liability</h3>
                <p>CryptoMeow shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of our services.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">9. Changes to Terms</h3>
                <p>We reserve the right to modify these terms at any time. Continued use of the service constitutes acceptance of the modified terms.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">10. Contact Information</h3>
                <p>For questions about these Terms of Service, please contact our support team through the platform.</p>
              </section>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
