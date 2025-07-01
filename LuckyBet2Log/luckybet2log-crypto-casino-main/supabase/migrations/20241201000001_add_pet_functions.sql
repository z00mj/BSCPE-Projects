
-- Function to purchase an egg
CREATE OR REPLACE FUNCTION purchase_egg(
  p_user_id UUID,
  p_egg_type_id INTEGER
) RETURNS JSON AS $$
DECLARE
  v_egg_price INTEGER;
  v_user_balance INTEGER;
  v_new_egg_id UUID;
BEGIN
  -- Get egg price
  SELECT price INTO v_egg_price
  FROM egg_types
  WHERE id = p_egg_type_id;
  
  IF v_egg_price IS NULL THEN
    RETURN json_build_object('success', false, 'error', 'Invalid egg type');
  END IF;
  
  -- Get user's current ITLOG balance
  SELECT itlog_tokens INTO v_user_balance
  FROM profiles
  WHERE user_id = p_user_id;
  
  IF v_user_balance < v_egg_price THEN
    RETURN json_build_object('success', false, 'error', 'Insufficient ITLOG tokens');
  END IF;
  
  -- Deduct tokens and create egg
  UPDATE profiles
  SET itlog_tokens = itlog_tokens - v_egg_price
  WHERE user_id = p_user_id;
  
  INSERT INTO user_eggs (user_id, egg_type_id)
  VALUES (p_user_id, p_egg_type_id)
  RETURNING id INTO v_new_egg_id;
  
  RETURN json_build_object(
    'success', true,
    'egg_id', v_new_egg_id,
    'tokens_spent', v_egg_price
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to start incubating an egg
CREATE OR REPLACE FUNCTION start_incubation(
  p_user_id UUID,
  p_egg_id UUID
) RETURNS JSON AS $$
DECLARE
  v_hatch_time INTEGER;
  v_hatch_timestamp TIMESTAMP WITH TIME ZONE;
BEGIN
  -- Get hatch time for this egg type
  SELECT et.hatch_time INTO v_hatch_time
  FROM user_eggs ue
  JOIN egg_types et ON ue.egg_type_id = et.id
  WHERE ue.id = p_egg_id AND ue.user_id = p_user_id AND ue.status = 'inventory';
  
  IF v_hatch_time IS NULL THEN
    RETURN json_build_object('success', false, 'error', 'Egg not found or already incubating');
  END IF;
  
  v_hatch_timestamp := NOW() + (v_hatch_time || ' seconds')::INTERVAL;
  
  UPDATE user_eggs
  SET 
    status = 'incubating',
    incubation_start = NOW(),
    hatch_time = v_hatch_timestamp
  WHERE id = p_egg_id AND user_id = p_user_id;
  
  RETURN json_build_object(
    'success', true,
    'hatch_time', v_hatch_timestamp
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to hatch an egg
CREATE OR REPLACE FUNCTION hatch_egg(
  p_user_id UUID,
  p_egg_id UUID
) RETURNS JSON AS $$
DECLARE
  v_egg_type_id INTEGER;
  v_pet_type_record RECORD;
  v_new_pet_id UUID;
  v_random_roll DECIMAL;
  v_cumulative_rate DECIMAL := 0;
BEGIN
  -- Check if egg is ready to hatch
  SELECT egg_type_id INTO v_egg_type_id
  FROM user_eggs
  WHERE id = p_egg_id 
    AND user_id = p_user_id 
    AND status = 'incubating'
    AND hatch_time <= NOW();
  
  IF v_egg_type_id IS NULL THEN
    RETURN json_build_object('success', false, 'error', 'Egg not ready to hatch');
  END IF;
  
  -- Random selection based on drop rates
  v_random_roll := RANDOM();
  
  FOR v_pet_type_record IN 
    SELECT * FROM pet_types 
    WHERE egg_type_id = v_egg_type_id 
    ORDER BY drop_rate DESC
  LOOP
    v_cumulative_rate := v_cumulative_rate + v_pet_type_record.drop_rate;
    IF v_random_roll <= v_cumulative_rate THEN
      EXIT;
    END IF;
  END LOOP;
  
  -- Create the pet
  INSERT INTO user_pets (user_id, pet_type_id)
  VALUES (p_user_id, v_pet_type_record.id)
  RETURNING id INTO v_new_pet_id;
  
  -- Mark egg as hatched
  UPDATE user_eggs
  SET status = 'hatched'
  WHERE id = p_egg_id;
  
  RETURN json_build_object(
    'success', true,
    'pet_id', v_new_pet_id,
    'pet_name', v_pet_type_record.name,
    'pet_emoji', v_pet_type_record.sprite_emoji,
    'rarity', v_pet_type_record.rarity,
    'trait_type', v_pet_type_record.trait_type,
    'trait_value', v_pet_type_record.trait_value
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to place pet in garden
CREATE OR REPLACE FUNCTION place_pet_in_garden(
  p_user_id UUID,
  p_pet_id UUID,
  p_position INTEGER
) RETURNS JSON AS $$
BEGIN
  -- Check if position is available (0-8 for 3x3 grid)
  IF p_position < 0 OR p_position > 8 THEN
    RETURN json_build_object('success', false, 'error', 'Invalid garden position');
  END IF;
  
  -- Check if position is already occupied
  IF EXISTS (
    SELECT 1 FROM user_pets 
    WHERE user_id = p_user_id 
      AND is_active = true 
      AND garden_position = p_position
  ) THEN
    RETURN json_build_object('success', false, 'error', 'Position already occupied');
  END IF;
  
  -- Place pet in garden
  UPDATE user_pets
  SET 
    is_active = true,
    garden_position = p_position
  WHERE id = p_pet_id AND user_id = p_user_id;
  
  RETURN json_build_object('success', true);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to remove pet from garden
CREATE OR REPLACE FUNCTION remove_pet_from_garden(
  p_user_id UUID,
  p_pet_id UUID
) RETURNS JSON AS $$
BEGIN
  UPDATE user_pets
  SET 
    is_active = false,
    garden_position = NULL
  WHERE id = p_pet_id AND user_id = p_user_id;
  
  RETURN json_build_object('success', true);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to get active pet boosts for a user
CREATE OR REPLACE FUNCTION get_user_pet_boosts(p_user_id UUID)
RETURNS TABLE(
  trait_type TEXT,
  total_boost DECIMAL
) AS $$
BEGIN
  RETURN QUERY
  SELECT 
    pt.trait_type,
    1 + SUM(pt.trait_value - 1) as total_boost -- Properly combine additive boosts
  FROM user_pets up
  JOIN pet_types pt ON up.pet_type_id = pt.id
  WHERE up.user_id = p_user_id AND up.is_active = true
  GROUP BY pt.trait_type;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
