
-- Create earning history table
CREATE TABLE IF NOT EXISTS earning_history (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  session_type TEXT NOT NULL CHECK (session_type IN ('farming', 'staking')),
  tokens_earned DECIMAL(20, 8) NOT NULL DEFAULT 0,
  stake_amount DECIMAL(20, 2) NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Add RLS policies
ALTER TABLE earning_history ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view their own earning history" ON earning_history
  FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own earning history" ON earning_history
  FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Add indexes for better performance
CREATE INDEX idx_earning_history_user_id ON earning_history(user_id);
CREATE INDEX idx_earning_history_created_at ON earning_history(created_at DESC);
