
-- Fix itlog_tokens column precision to properly handle 4+ decimal places
ALTER TABLE profiles ALTER COLUMN itlog_tokens TYPE DECIMAL(20, 8);

-- Ensure the column properly handles the precision
ALTER TABLE profiles ALTER COLUMN itlog_tokens SET DEFAULT 0.00000000;

-- Update any existing records to ensure proper precision
UPDATE profiles SET itlog_tokens = ROUND(itlog_tokens::DECIMAL(20, 8), 8);

-- Also fix the farming reward function to properly handle decimal precision and pet boosts
CREATE OR REPLACE FUNCTION harvest_farming_rewards(
  p_user_id UUID,
  p_session_id UUID
) RETURNS JSON AS $$
DECLARE
  v_farming_session RECORD;
  v_time_diff INTEGER;
  v_base_reward DECIMAL(20, 8);
  v_total_reward DECIMAL(20, 8);
  v_farming_boost DECIMAL(20, 8) := 1.0;
  v_token_multiplier DECIMAL(20, 8) := 1.0;
  v_boost_record RECORD;
BEGIN
  -- Get the farming session
  SELECT * INTO v_farming_session
  FROM farming_sessions
  WHERE id = p_session_id 
    AND user_id = p_user_id 
    AND is_active = true;
  
  IF NOT FOUND THEN
    RETURN json_build_object('success', false, 'error', 'No active farming session found');
  END IF;
  
  -- Calculate time difference in seconds since session started
  v_time_diff := EXTRACT(EPOCH FROM (NOW() - v_farming_session.started_at));
  
  -- Only allow harvest if at least 5 minutes (300 seconds) have passed
  IF v_time_diff < 300 THEN
    RETURN json_build_object('success', false, 'error', 'Not ready to harvest yet');
  END IF;
  
  -- Base reward is 0.0021 per harvest (every 5 minutes)
  v_base_reward := 0.0021::DECIMAL(20, 8);
  
  -- Get pet boosts for this user
  FOR v_boost_record IN 
    SELECT trait_type, total_boost FROM get_user_pet_boosts(p_user_id)
  LOOP
    IF v_boost_record.trait_type = 'farming_boost' THEN
      v_farming_boost := v_boost_record.total_boost;
    ELSIF v_boost_record.trait_type = 'token_multiplier' THEN
      v_token_multiplier := v_boost_record.total_boost;
    END IF;
  END LOOP;
  
  -- Calculate final reward with boosts applied
  v_total_reward := (v_base_reward * v_farming_boost * v_token_multiplier)::DECIMAL(20, 8);
  
  -- Update user's itlog_tokens with proper precision
  UPDATE profiles
  SET itlog_tokens = (itlog_tokens + v_total_reward)::DECIMAL(20, 8)
  WHERE user_id = p_user_id;
  
  -- Update farming session with new start time for next harvest
  UPDATE farming_sessions
  SET 
    tokens_earned = (tokens_earned + v_total_reward)::DECIMAL(20, 8),
    started_at = NOW()
  WHERE id = p_session_id;
  
  -- Record in earning history
  INSERT INTO earning_history (user_id, session_type, tokens_earned)
  VALUES (p_user_id, 'farming', v_total_reward);
  
  RETURN json_build_object(
    'success', true,
    'base_reward', v_base_reward,
    'farming_boost', v_farming_boost,
    'token_multiplier', v_token_multiplier,
    'reward', v_total_reward,
    'total_earned', v_farming_session.tokens_earned + v_total_reward
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Update the existing balance update function to handle proper precision
CREATE OR REPLACE FUNCTION update_user_balance(
  p_user_id UUID,
  p_php_change DECIMAL(10, 2) DEFAULT 0,
  p_coins_change DECIMAL(10, 2) DEFAULT 0,
  p_itlog_change DECIMAL(20, 8) DEFAULT 0
) RETURNS BOOLEAN AS $$
BEGIN
  UPDATE profiles
  SET 
    php_balance = php_balance + p_php_change,
    coins = coins + p_coins_change,
    itlog_tokens = (itlog_tokens + p_itlog_change)::DECIMAL(20, 8),
    updated_at = NOW()
  WHERE user_id = p_user_id;
  
  RETURN FOUND;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
