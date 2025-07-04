
-- Add itlog_balance column to profiles table
ALTER TABLE profiles ADD COLUMN IF NOT EXISTS itlog_balance DECIMAL(20, 8) DEFAULT 0;

-- Update the column to be NOT NULL with default value
ALTER TABLE profiles ALTER COLUMN itlog_balance SET NOT NULL;
ALTER TABLE profiles ALTER COLUMN itlog_balance SET DEFAULT 0;
