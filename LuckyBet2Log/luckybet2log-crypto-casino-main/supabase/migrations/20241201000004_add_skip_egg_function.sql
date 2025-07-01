
-- Function to skip egg hatching time for 50 ITLOG tokens
CREATE OR REPLACE FUNCTION skip_egg_hatching(
  p_user_id UUID,
  p_egg_id UUID
) RETURNS JSON AS $$
DECLARE
  v_user_balance INTEGER;
  v_skip_cost INTEGER := 50; -- Fixed cost of 50 ITLOG tokens
  v_egg_record RECORD;
BEGIN
  -- Get user's current ITLOG balance
  SELECT itlog_tokens INTO v_user_balance
  FROM profiles
  WHERE user_id = p_user_id;
  
  IF v_user_balance < v_skip_cost THEN
    RETURN json_build_object('success', false, 'error', 'Insufficient ITLOG tokens. Need 50 tokens to skip.');
  END IF;
  
  -- Check if egg exists and is incubating
  SELECT * INTO v_egg_record
  FROM user_eggs
  WHERE id = p_egg_id 
    AND user_id = p_user_id 
    AND status = 'incubating';
  
  IF v_egg_record IS NULL THEN
    RETURN json_build_object('success', false, 'error', 'Egg not found or not incubating');
  END IF;
  
  -- Deduct tokens
  UPDATE profiles
  SET itlog_tokens = itlog_tokens - v_skip_cost
  WHERE user_id = p_user_id;
  
  -- Set hatch time to now (making it ready to hatch)
  UPDATE user_eggs
  SET hatch_time = NOW()
  WHERE id = p_egg_id AND user_id = p_user_id;
  
  RETURN json_build_object(
    'success', true,
    'tokens_spent', v_skip_cost,
    'message', 'Egg is now ready to hatch!'
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
