
-- Create game history table
CREATE TABLE IF NOT EXISTS game_history (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  game_type TEXT NOT NULL,
  bet_amount DECIMAL(20, 2) NOT NULL,
  result_type TEXT NOT NULL CHECK (result_type IN ('win', 'loss', 'push')),
  win_amount DECIMAL(20, 2) DEFAULT 0,
  loss_amount DECIMAL(20, 2) DEFAULT 0,
  multiplier DECIMAL(10, 2) DEFAULT 0,
  game_details JSONB DEFAULT '{}',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Add RLS policies
ALTER TABLE game_history ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Users can view their own game history" ON game_history;
DROP POLICY IF EXISTS "Users can insert their own game history" ON game_history;
DROP POLICY IF EXISTS "Users can delete their own game history" ON game_history;

CREATE POLICY "Users can view their own game history" ON game_history
  FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own game history" ON game_history
  FOR INSERT WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can delete their own game history" ON game_history
  FOR DELETE USING (auth.uid() = user_id);

-- Add indexes for better performance
DROP INDEX IF EXISTS idx_game_history_user_id;
DROP INDEX IF EXISTS idx_game_history_game_type;
DROP INDEX IF EXISTS idx_game_history_created_at;
DROP INDEX IF EXISTS idx_game_history_user_game;

CREATE INDEX idx_game_history_user_id ON game_history(user_id);
CREATE INDEX idx_game_history_game_type ON game_history(game_type);
CREATE INDEX idx_game_history_created_at ON game_history(created_at DESC);
CREATE INDEX idx_game_history_user_game ON game_history(user_id, game_type, created_at DESC);
