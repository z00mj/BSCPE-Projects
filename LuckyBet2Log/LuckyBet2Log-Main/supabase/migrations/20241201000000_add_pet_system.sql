
-- Create egg types table
CREATE TABLE egg_types (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  rarity TEXT NOT NULL CHECK (rarity IN ('common', 'uncommon', 'rare', 'legendary', 'mythical')),
  price INTEGER NOT NULL,
  hatch_time INTEGER NOT NULL DEFAULT 300, -- 5 minutes in seconds
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create pet types table
CREATE TABLE pet_types (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  egg_type_id INTEGER REFERENCES egg_types(id),
  rarity TEXT NOT NULL CHECK (rarity IN ('common', 'uncommon', 'rare', 'legendary', 'mythical')),
  sprite_emoji TEXT NOT NULL DEFAULT '🐾',
  trait_type TEXT NOT NULL CHECK (trait_type IN ('farming_boost', 'staking_boost', 'token_multiplier', 'luck_boost')),
  trait_value DECIMAL(4,3) NOT NULL DEFAULT 1.0, -- Multiplier value (1.1 = 10% boost)
  drop_rate DECIMAL(4,3) NOT NULL DEFAULT 0.1, -- Chance to get this pet from egg
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create user eggs table
CREATE TABLE user_eggs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL,
  egg_type_id INTEGER REFERENCES egg_types(id),
  status TEXT NOT NULL DEFAULT 'inventory' CHECK (status IN ('inventory', 'incubating', 'hatched')),
  incubation_start TIMESTAMP WITH TIME ZONE,
  hatch_time TIMESTAMP WITH TIME ZONE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create user pets table
CREATE TABLE user_pets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL,
  pet_type_id INTEGER REFERENCES pet_types(id),
  name TEXT,
  is_active BOOLEAN DEFAULT FALSE, -- Whether pet is placed in garden
  garden_position INTEGER, -- Position in garden (0-8 for 3x3 grid)
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Insert default egg types
INSERT INTO egg_types (name, rarity, price, hatch_time) VALUES
('Common Egg', 'common', 10, 300),
('Uncommon Egg', 'uncommon', 50, 600),
('Rare Egg', 'rare', 100, 1800),
('Legendary Egg', 'legendary', 1000, 3600),
('Mythical Egg', 'mythical', 10000, 7200);

-- Insert default pet types
INSERT INTO pet_types (name, egg_type_id, rarity, sprite_emoji, trait_type, trait_value, drop_rate) VALUES
-- Common pets
('Lucky Chick', 1, 'common', '🐣', 'farming_boost', 1.05, 0.4),
('Garden Snail', 1, 'common', '🐌', 'farming_boost', 1.03, 0.4),
('Busy Bee', 1, 'common', '🐝', 'token_multiplier', 1.02, 0.2),

-- Uncommon pets
('Golden Hamster', 2, 'uncommon', '🐹', 'staking_boost', 1.1, 0.3),
('Magic Rabbit', 2, 'uncommon', '🐰', 'farming_boost', 1.15, 0.3),
('Fortune Cat', 2, 'uncommon', '🐱', 'luck_boost', 1.08, 0.2),
('Crystal Turtle', 2, 'uncommon', '🐢', 'token_multiplier', 1.05, 0.2),

-- Rare pets
('Phoenix Chick', 3, 'rare', '🔥', 'farming_boost', 1.25, 0.25),
('Mystic Fox', 3, 'rare', '🦊', 'staking_boost', 1.2, 0.25),
('Diamond Dog', 3, 'rare', '💎', 'token_multiplier', 1.1, 0.25),
('Lucky Dragon', 3, 'rare', '🐉', 'luck_boost', 1.15, 0.25),

-- Legendary pets
('Golden Phoenix', 4, 'legendary', '🌟', 'farming_boost', 1.5, 0.2),
('Ancient Dragon', 4, 'legendary', '🐲', 'staking_boost', 1.4, 0.2),
('Cosmic Wolf', 4, 'legendary', '🌌', 'token_multiplier', 1.25, 0.2),
('Fortune Spirit', 4, 'legendary', '✨', 'luck_boost', 1.3, 0.2),
('Royal Griffin', 4, 'legendary', '🦅', 'farming_boost', 1.45, 0.2),

-- Mythical pets
('Eternal Phoenix', 5, 'mythical', '🔆', 'farming_boost', 2.0, 0.1),
('Void Dragon', 5, 'mythical', '🌑', 'staking_boost', 1.8, 0.1),
('Reality Bender', 5, 'mythical', '🌀', 'token_multiplier', 1.5, 0.1),
('Luck Incarnate', 5, 'mythical', '🍀', 'luck_boost', 1.75, 0.1),
('Dimension Walker', 5, 'mythical', '🚪', 'farming_boost', 1.9, 0.1),
('Time Guardian', 5, 'mythical', '⏰', 'staking_boost', 1.75, 0.1),
('Chaos Entity', 5, 'mythical', '💫', 'token_multiplier', 1.4, 0.1),
('Dream Weaver', 5, 'mythical', '🌙', 'luck_boost', 1.7, 0.1),
('Soul Keeper', 5, 'mythical', '👻', 'farming_boost', 1.85, 0.1),
('Star Forger', 5, 'mythical', '⭐', 'staking_boost', 1.7, 0.1);

-- Create indexes
CREATE INDEX idx_user_eggs_user_id ON user_eggs(user_id);
CREATE INDEX idx_user_pets_user_id ON user_pets(user_id);
CREATE INDEX idx_user_pets_active ON user_pets(user_id, is_active);

-- Add RLS policies
ALTER TABLE user_eggs ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_pets ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view their own eggs" ON user_eggs FOR SELECT USING (auth.uid()::text = user_id::text);
CREATE POLICY "Users can insert their own eggs" ON user_eggs FOR INSERT WITH CHECK (auth.uid()::text = user_id::text);
CREATE POLICY "Users can update their own eggs" ON user_eggs FOR UPDATE USING (auth.uid()::text = user_id::text);

CREATE POLICY "Users can view their own pets" ON user_pets FOR SELECT USING (auth.uid()::text = user_id::text);
CREATE POLICY "Users can insert their own pets" ON user_pets FOR INSERT WITH CHECK (auth.uid()::text = user_id::text);
CREATE POLICY "Users can update their own pets" ON user_pets FOR UPDATE USING (auth.uid()::text = user_id::text);

-- Public read access for reference tables
ALTER TABLE egg_types ENABLE ROW LEVEL SECURITY;
ALTER TABLE pet_types ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Anyone can view egg types" ON egg_types FOR SELECT TO PUBLIC USING (true);
CREATE POLICY "Anyone can view pet types" ON pet_types FOR SELECT TO PUBLIC USING (true);
