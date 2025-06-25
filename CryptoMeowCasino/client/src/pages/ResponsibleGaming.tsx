
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Shield, Heart, Phone, AlertCircle } from "lucide-react";

export default function ResponsibleGaming() {
  return (
    <div className="relative min-h-screen background-animated">
      <div className="relative z-20 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Card className="crypto-gray border-crypto-pink/20 backdrop-blur-sm bg-opacity-95">
          <CardHeader>
            <CardTitle className="text-3xl font-bold text-crypto-pink text-center flex items-center justify-center">
              <Shield className="w-8 h-8 mr-3" />
              Responsible Gaming
            </CardTitle>
            <p className="text-gray-400 text-center">Your wellbeing is our priority</p>
          </CardHeader>
          <CardContent className="prose prose-invert max-w-none">
            <div className="text-gray-300 space-y-6">
              <div className="bg-crypto-green/10 border border-crypto-green/30 rounded-lg p-4">
                <h3 className="text-lg font-semibold text-crypto-green mb-2 flex items-center">
                  <Heart className="w-5 h-5 mr-2" />
                  Our Commitment
                </h3>
                <p className="text-sm">
                  CryptoMeow is committed to promoting responsible gaming and providing a safe environment 
                  for all our players. We believe gaming should be fun, entertaining, and never harmful.
                </p>
              </div>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">What is Responsible Gaming?</h3>
                <p>Responsible gaming means playing within your limits and maintaining control over your gambling activities. It involves:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Setting and sticking to time and money limits</li>
                  <li>Understanding that gambling is entertainment, not a way to make money</li>
                  <li>Never chasing losses</li>
                  <li>Taking regular breaks from gaming</li>
                  <li>Seeking help when gambling becomes problematic</li>
                </ul>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Warning Signs of Problem Gambling</h3>
                <div className="bg-yellow-900/20 border border-yellow-500/30 rounded-lg p-4">
                  <h4 className="text-yellow-400 font-semibold mb-2 flex items-center">
                    <AlertCircle className="w-5 h-5 mr-2" />
                    Recognize These Signs
                  </h4>
                  <ul className="list-disc list-inside space-y-1 ml-4 text-sm">
                    <li>Spending more money than you can afford</li>
                    <li>Gambling for longer periods than intended</li>
                    <li>Lying about gambling activities</li>
                    <li>Neglecting work, family, or other responsibilities</li>
                    <li>Chasing losses with bigger bets</li>
                    <li>Feeling anxious or depressed about gambling</li>
                    <li>Borrowing money to gamble</li>
                    <li>Unable to stop or control gambling urges</li>
                  </ul>
                </div>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Tools to Help You Stay in Control</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="bg-crypto-gray/50 border border-crypto-pink/20 rounded-lg p-4">
                    <h4 className="text-crypto-green font-semibold mb-2">💰 Deposit Limits</h4>
                    <p className="text-sm">Set daily, weekly, or monthly deposit limits to control your spending.</p>
                  </div>
                  <div className="bg-crypto-gray/50 border border-crypto-pink/20 rounded-lg p-4">
                    <h4 className="text-crypto-green font-semibold mb-2">⏰ Time Limits</h4>
                    <p className="text-sm">Set session time limits to prevent extended gaming periods.</p>
                  </div>
                  <div className="bg-crypto-gray/50 border border-crypto-pink/20 rounded-lg p-4">
                    <h4 className="text-crypto-green font-semibold mb-2">💸 Loss Limits</h4>
                    <p className="text-sm">Set maximum loss amounts to protect your bankroll.</p>
                  </div>
                  <div className="bg-crypto-gray/50 border border-crypto-pink/20 rounded-lg p-4">
                    <h4 className="text-crypto-green font-semibold mb-2">🚫 Self-Exclusion</h4>
                    <p className="text-sm">Temporarily or permanently exclude yourself from the platform.</p>
                  </div>
                </div>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Tips for Responsible Gaming</h3>
                <div className="space-y-3">
                  <div className="flex items-start space-x-3">
                    <span className="text-crypto-green text-lg">✓</span>
                    <p><strong>Set a budget</strong> - Only gamble with money you can afford to lose</p>
                  </div>
                  <div className="flex items-start space-x-3">
                    <span className="text-crypto-green text-lg">✓</span>
                    <p><strong>Set time limits</strong> - Decide how long you'll play before you start</p>
                  </div>
                  <div className="flex items-start space-x-3">
                    <span className="text-crypto-green text-lg">✓</span>
                    <p><strong>Take breaks</strong> - Regular breaks help maintain perspective</p>
                  </div>
                  <div className="flex items-start space-x-3">
                    <span className="text-crypto-green text-lg">✓</span>
                    <p><strong>Don't chase losses</strong> - Accept losses as part of the game</p>
                  </div>
                  <div className="flex items-start space-x-3">
                    <span className="text-crypto-green text-lg">✓</span>
                    <p><strong>Keep it fun</strong> - If it's not fun anymore, it's time to stop</p>
                  </div>
                  <div className="flex items-start space-x-3">
                    <span className="text-crypto-green text-lg">✓</span>
                    <p><strong>Stay sober</strong> - Avoid gambling under the influence</p>
                  </div>
                </div>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">Getting Help</h3>
                <p>If you're concerned about your gambling habits, help is available:</p>
                
                <div className="bg-blue-900/20 border border-blue-500/30 rounded-lg p-4 mt-4">
                  <h4 className="text-blue-400 font-semibold mb-3 flex items-center">
                    <Phone className="w-5 h-5 mr-2" />
                    Support Resources
                  </h4>
                  <div className="space-y-2 text-sm">
                    <p><strong>National Gambling Helpline:</strong> Available 24/7 for confidential support</p>
                    <p><strong>Gamblers Anonymous:</strong> Support groups for people with gambling problems</p>
                    <p><strong>Professional Counseling:</strong> Licensed therapists specializing in gambling addiction</p>
                    <p><strong>Online Resources:</strong> Websites and apps designed to help with gambling control</p>
                  </div>
                </div>
              </section>

              <section>
                <h3 className="text-xl font-semibold text-crypto-pink mb-3">For Friends and Family</h3>
                <p>If someone you know has a gambling problem:</p>
                <ul className="list-disc list-inside space-y-1 ml-4">
                  <li>Approach them with care and understanding</li>
                  <li>Avoid lending money or covering their debts</li>
                  <li>Encourage them to seek professional help</li>
                  <li>Consider attending support groups for affected families</li>
                  <li>Take care of your own mental health</li>
                </ul>
              </section>

              <div className="bg-crypto-pink/10 border border-crypto-pink/30 rounded-lg p-4 text-center">
                <p className="text-crypto-pink font-semibold mb-2">
                  Remember: It's never too late to get help
                </p>
                <p className="text-sm">
                  If you need assistance with responsible gaming tools or have concerns about your gambling, 
                  please contact our support team through the platform.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
