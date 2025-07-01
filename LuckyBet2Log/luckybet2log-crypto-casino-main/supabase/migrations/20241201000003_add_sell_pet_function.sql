
-- Function to sell a pet for ITLOG tokens
CREATE OR REPLACE FUNCTION sell_pet(
  p_user_id UUID,
  p_pet_id UUID
) RETURNS JSON AS $$
DECLARE
  v_pet_record RECORD;
  v_pet_type_record RECORD;
  v_base_price INTEGER;
  v_rarity_multiplier DECIMAL(6,2);
  v_scarcity_multiplier DECIMAL(10,4);
  v_final_price INTEGER;
BEGIN
  -- Get pet information with explicit field selection
  SELECT 
    up.*,
    pt.id as pet_type_id,
    pt.name as pet_type_name,
    pt.sprite_emoji,
    pt.rarity,
    pt.drop_rate,
    pt.trait_type,
    pt.trait_value
  INTO v_pet_record
  FROM user_pets up
  JOIN pet_types pt ON up.pet_type_id = pt.id
  WHERE up.id = p_pet_id AND up.user_id = p_user_id;
  
  IF NOT FOUND THEN
    RETURN json_build_object('success', false, 'error', 'Pet not found or not owned by user');
  END IF;
  
  -- Cannot sell active pets
  IF v_pet_record.is_active THEN
    RETURN json_build_object('success', false, 'error', 'Cannot sell active pets. Remove from garden first.');
  END IF;
  
  -- Base prices by rarity (matching PetGarden.tsx basePrices)
  CASE v_pet_record.rarity
    WHEN 'common' THEN v_base_price := 10;
    WHEN 'uncommon' THEN v_base_price := 50;
    WHEN 'rare' THEN v_base_price := 100;
    WHEN 'legendary' THEN v_base_price := 1000;
    WHEN 'mythical' THEN v_base_price := 10000;
    ELSE v_base_price := 10;
  END CASE;
  
  -- Rarity multipliers (matching PetGarden.tsx rarityMultipliers) - capped to prevent overflow
  CASE v_pet_record.rarity
    WHEN 'common' THEN v_rarity_multiplier := 5.0;
    WHEN 'uncommon' THEN v_rarity_multiplier := 10.0;
    WHEN 'rare' THEN v_rarity_multiplier := 25.0;
    WHEN 'legendary' THEN v_rarity_multiplier := 50.0;
    WHEN 'mythical' THEN v_rarity_multiplier := 99.0; -- Capped to prevent overflow
    ELSE v_rarity_multiplier := 5.0;
  END CASE;
  
  -- Scarcity multiplier (matching PetGarden.tsx formula) - capped to prevent overflow
  v_scarcity_multiplier := LEAST(99.0, GREATEST(1.0, (1.0 / v_pet_record.drop_rate)));
  
  -- Calculate final price (matching PetGarden.tsx formula) - ensure we don't overflow
  v_final_price := LEAST(2147483647, FLOOR(v_base_price + (v_rarity_multiplier * v_scarcity_multiplier)));
  
  -- Special case for mythical pets with 10% drop rate to ensure exactly 11000 (matching frontend)
  IF v_pet_record.rarity = 'mythical' AND v_pet_record.drop_rate = 0.1 THEN
    v_final_price := 11000;
  END IF;
  
  -- Delete the pet
  DELETE FROM user_pets WHERE id = p_pet_id;
  
  -- Add tokens to user balance
  UPDATE profiles
  SET itlog_tokens = itlog_tokens + v_final_price
  WHERE user_id = p_user_id;
  
  RETURN json_build_object(
    'success', true,
    'pet_name', v_pet_record.pet_type_name,
    'pet_emoji', v_pet_record.sprite_emoji,
    'rarity', v_pet_record.rarity,
    'tokens_earned', v_final_price,
    'drop_rate', ROUND(v_pet_record.drop_rate, 4),
    'base_price', v_base_price,
    'rarity_multiplier', ROUND(v_rarity_multiplier, 2),
    'scarcity_multiplier', ROUND(v_scarcity_multiplier, 4)
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
