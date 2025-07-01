
-- Create quest definitions table
CREATE TABLE IF NOT EXISTS quest_definitions (
  id SERIAL PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  difficulty_tier VARCHAR(10) NOT NULL CHECK (difficulty_tier IN ('easy', 'medium', 'hard')),
  task_type VARCHAR(50) NOT NULL,
  target_value DECIMAL(10,2) DEFAULT 1,
  reward_min DECIMAL(10,4) NOT NULL,
  reward_max DECIMAL(10,4) NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW())
);

-- Create daily quests table
CREATE TABLE IF NOT EXISTS daily_quests (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  quest_definition_id INTEGER NOT NULL REFERENCES quest_definitions(id),
  date DATE NOT NULL DEFAULT CURRENT_DATE,
  progress DECIMAL(10,2) DEFAULT 0,
  is_completed BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW()),
  UNIQUE(user_id, quest_definition_id, date)
);

-- Create user activity tracking table
CREATE TABLE IF NOT EXISTS user_activities (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  activity_type VARCHAR(50) NOT NULL,
  activity_value DECIMAL(10,2) DEFAULT 1,
  game_type VARCHAR(50),
  metadata JSONB DEFAULT '{}',
  session_id VARCHAR(255),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW())
);

-- Create quest rewards claimed table
CREATE TABLE IF NOT EXISTS quest_rewards_claimed (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  date DATE NOT NULL DEFAULT CURRENT_DATE,
  total_reward DECIMAL(10,4) NOT NULL,
  quest_ids UUID[] NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW()),
  UNIQUE(user_id, date)
);

-- Insert quest definitions (only if table is empty)
INSERT INTO quest_definitions (title, description, difficulty_tier, task_type, target_value, reward_min, reward_max) 
SELECT * FROM (VALUES
-- Easy Tasks (1-7)
('Play Casino Game', 'Play any casino game once', 'easy', 'play_game', 1, 0.01, 0.05),
('Win Streak', 'Win 3 times in any game', 'easy', 'win_games', 3, 0.01, 0.05),
('Total Bets', 'Bet a total of at least 100 coins', 'easy', 'total_bets', 100, 0.01, 0.05),
('Game Variety', 'Try 3 different games in one session', 'easy', 'different_games', 3, 0.01, 0.05),
('Daily Winnings', 'Accumulate 10,000 coins in winnings today', 'easy', 'daily_winnings', 10000, 0.01, 0.05),
('Persistence', 'Lose 3 games in a row and keep playing', 'easy', 'lose_streak_continue', 1, 0.01, 0.05),
('Game Duration', 'Play casino games for 5 minutes', 'easy', 'play_duration', 300, 0.01, 0.05),

-- Medium Tasks (8-14)
('Claim Farming Rewards', 'Claim your token farming rewards', 'medium', 'claim_farming', 1, 0.06, 0.09),
('Stake PHP', 'Stake at least PHP 100 today', 'medium', 'stake_php', 100, 0.06, 0.09),
('Complete Daily Quest', 'Complete at least 1 daily quest', 'medium', 'complete_quest', 1, 0.06, 0.09),
('Deposit PHP', 'Deposit at least PHP 100', 'medium', 'deposit_php', 100, 0.06, 0.09),
('Withdraw PHP', 'Withdraw at least PHP 100', 'medium', 'withdraw_php', 100, 0.06, 0.09),
('Convert Currency', 'Convert between currencies 3 times', 'medium', 'convert_currency', 3, 0.06, 0.09),
('Exchange ITLOG', 'Exchange ITLOG tokens once', 'medium', 'exchange_itlog', 1, 0.06, 0.09),

-- Hard Tasks (15-21)
('High Roller', 'Place a single bet of at least 1,000 coins', 'hard', 'single_bet', 1000, 0.10, 0.15),
('ITLOG Collector', 'Accumulate at least 1 ITLOG token in your balance', 'hard', 'itlog_balance', 1, 0.10, 0.15),
('Game Master', 'Play all 5 different casino games in one day', 'hard', 'all_games', 5, 0.10, 0.15),
('Farming Streak', 'Claim farming rewards 3 times in one day', 'hard', 'farming_streak', 3, 0.10, 0.15),
('Balance Keeper', 'Maintain a PHP balance of at least 500 throughout the day', 'hard', 'maintain_balance', 500, 0.10, 0.15),
('Lucky Strike', 'Win a single game with at least 5,000 coins', 'hard', 'single_game_win', 5000, 0.10, 0.15),
('Ultimate Player', 'Complete all easy and medium tasks in one day', 'hard', 'complete_all_tasks', 1, 0.10, 0.15)
) AS v(title, description, difficulty_tier, task_type, target_value, reward_min, reward_max)
WHERE NOT EXISTS (SELECT 1 FROM quest_definitions LIMIT 1);

-- Create indexes for performance (only if they don't exist)
CREATE INDEX IF NOT EXISTS idx_daily_quests_user_date ON daily_quests(user_id, date);
CREATE INDEX IF NOT EXISTS idx_user_activities_user_type_date ON user_activities(user_id, activity_type, created_at);
CREATE INDEX IF NOT EXISTS idx_quest_rewards_user_date ON quest_rewards_claimed(user_id, date);

-- Enable RLS
ALTER TABLE quest_definitions ENABLE ROW LEVEL SECURITY;
ALTER TABLE daily_quests ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_activities ENABLE ROW LEVEL SECURITY;
ALTER TABLE quest_rewards_claimed ENABLE ROW LEVEL SECURITY;

-- RLS Policies (drop if exists first)
DROP POLICY IF EXISTS "Quest definitions are viewable by everyone" ON quest_definitions;
DROP POLICY IF EXISTS "Users can view their own daily quests" ON daily_quests;
DROP POLICY IF EXISTS "Users can insert their own daily quests" ON daily_quests;
DROP POLICY IF EXISTS "Users can update their own daily quests" ON daily_quests;
DROP POLICY IF EXISTS "Users can view their own activities" ON user_activities;
DROP POLICY IF EXISTS "Users can insert their own activities" ON user_activities;
DROP POLICY IF EXISTS "Users can view their own quest rewards" ON quest_rewards_claimed;
DROP POLICY IF EXISTS "Users can insert their own quest rewards" ON quest_rewards_claimed;

CREATE POLICY "Quest definitions are viewable by everyone" ON quest_definitions FOR SELECT USING (true);

CREATE POLICY "Users can view their own daily quests" ON daily_quests FOR SELECT USING (auth.uid() = user_id);
CREATE POLICY "Users can insert their own daily quests" ON daily_quests FOR INSERT WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users can update their own daily quests" ON daily_quests FOR UPDATE USING (auth.uid() = user_id);

CREATE POLICY "Users can view their own activities" ON user_activities FOR SELECT USING (auth.uid() = user_id);
CREATE POLICY "Users can insert their own activities" ON user_activities FOR INSERT WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can view their own quest rewards" ON quest_rewards_claimed FOR SELECT USING (auth.uid() = user_id);
CREATE POLICY "Users can insert their own quest rewards" ON quest_rewards_claimed FOR INSERT WITH CHECK (auth.uid() = user_id);
