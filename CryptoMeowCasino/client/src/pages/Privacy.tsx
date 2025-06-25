
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function Privacy() {
  return (
    <div className="relative min-h-screen background-animated">
      <div className="relative z-20 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95">
          <CardHeader>
            <CardTitle className="text-3xl font-bold text-crypto-pink text-center">
              Privacy Policy
            </CardTitle>
            <p className="text-gray-400 text-center">Last updated: {new Date().toLocaleDateString()}</p>
          </CardHeader>
          <CardContent className="prose prose-invert max-w-none">
            <div className="text-gray-300 space-y-6">
              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">1. Information We Collect</h3>
                <p>We collect information you provide directly to us, such as when you create an account, make deposits, or contact us for support.</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Account information (username, password)</li>
                  <li>Transaction history and payment information</li>
                  <li>Game play data and statistics</li>
                  <li>Communication records with our support team</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">2. How We Use Your Information</h3>
                <p>We use the information we collect to:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Provide and maintain our gaming services</li>
                  <li>Process transactions and manage your account</li>
                  <li>Ensure fair play and platform security</li>
                  <li>Communicate with you about your account and our services</li>
                  <li>Comply with legal obligations and prevent fraud</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">3. Information Sharing</h3>
                <p>We do not sell, trade, or otherwise transfer your personal information to third parties except:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>With your explicit consent</li>
                  <li>To comply with legal requirements</li>
                  <li>To protect our rights and prevent fraud</li>
                  <li>With service providers who assist in our operations</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">4. Data Security</h3>
                <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. This includes encryption, secure servers, and regular security audits.</p>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">5. Your Rights</h3>
                <p>You have the right to:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Access and update your personal information</li>
                  <li>Request deletion of your account and data</li>
                  <li>Opt-out of marketing communications</li>
                  <li>File complaints with relevant authorities</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">6. Contact Us</h3>
                <p>If you have any questions about this Privacy Policy, please contact us through our support system.</p>
              </section>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
