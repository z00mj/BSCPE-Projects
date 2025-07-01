
-- Drop existing functions first to avoid parameter name conflicts
DROP FUNCTION IF EXISTS assign_daily_quests(UUID);
DROP FUNCTION IF EXISTS update_quest_progress(UUID, VARCHAR, DECIMAL, VARCHAR, JSONB);
DROP FUNCTION IF EXISTS claim_quest_rewards(UUID);
DROP FUNCTION IF EXISTS reset_daily_quests();

-- Function to assign daily quests to a user
CREATE OR REPLACE FUNCTION assign_daily_quests(p_user_id UUID)
RETURNS VOID AS $$
DECLARE
    quest_exists INTEGER;
    easy_quest_id INTEGER;
    medium_quest_id INTEGER;
    hard_quest_id INTEGER;
BEGIN
    -- Check if user already has quests for today
    SELECT COUNT(*) INTO quest_exists
    FROM daily_quests
    WHERE user_id = p_user_id AND date = CURRENT_DATE;
    
    -- If no quests exist for today, assign new ones
    IF quest_exists = 0 THEN
        -- Get one quest from each difficulty tier randomly
        SELECT id INTO easy_quest_id
        FROM quest_definitions 
        WHERE difficulty_tier = 'easy' 
        ORDER BY RANDOM() 
        LIMIT 1;
        
        SELECT id INTO medium_quest_id
        FROM quest_definitions 
        WHERE difficulty_tier = 'medium' 
        ORDER BY RANDOM() 
        LIMIT 1;
        
        SELECT id INTO hard_quest_id
        FROM quest_definitions 
        WHERE difficulty_tier = 'hard' 
        ORDER BY RANDOM() 
        LIMIT 1;
        
        -- Insert the selected quests
        INSERT INTO daily_quests (user_id, quest_definition_id, date)
        VALUES 
            (p_user_id, easy_quest_id, CURRENT_DATE),
            (p_user_id, medium_quest_id, CURRENT_DATE),
            (p_user_id, hard_quest_id, CURRENT_DATE);
            
        -- Check balance-based quests immediately after assignment
        PERFORM check_balance_quests(p_user_id);
    END IF;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to update quest progress based on user activity
CREATE OR REPLACE FUNCTION update_quest_progress(
    p_user_id UUID,
    p_activity_type VARCHAR,
    p_activity_value DECIMAL DEFAULT 1,
    p_game_type VARCHAR DEFAULT NULL,
    p_metadata JSONB DEFAULT '{}'
)
RETURNS VOID AS $$
DECLARE
    quest_record RECORD;
    progress_increment DECIMAL := 0;
    current_progress DECIMAL;
    target_value DECIMAL;
    today_date DATE := CURRENT_DATE;
    current_balance DECIMAL;
BEGIN
    -- Process each active quest for the user
    FOR quest_record IN (
        SELECT dq.id, dq.progress, dq.is_completed, qd.task_type, qd.target_value, qd.difficulty_tier
        FROM daily_quests dq
        JOIN quest_definitions qd ON dq.quest_definition_id = qd.id
        WHERE dq.user_id = p_user_id 
        AND dq.date = today_date
        AND dq.is_completed = FALSE
    ) LOOP
        progress_increment := 0;
        
        CASE quest_record.task_type
            -- Play any casino game once
            WHEN 'play_game' THEN
                IF p_activity_type = 'play_game' THEN
                    progress_increment := 1;
                END IF;
            
            -- Win 3 times in any game
            WHEN 'win_games' THEN
                IF p_activity_type = 'win_game' THEN
                    progress_increment := 1;
                END IF;
            
            -- Bet a total of at least X tokens
            WHEN 'total_bets' THEN
                IF p_activity_type = 'place_bet' THEN
                    progress_increment := p_activity_value;
                END IF;
            
            -- Try X different games
            WHEN 'different_games' THEN
                IF p_activity_type = 'play_game' THEN
                    -- Count unique games played today
                    SELECT COUNT(DISTINCT game_type) INTO progress_increment
                    FROM user_activities
                    WHERE user_id = p_user_id
                    AND activity_type = 'play_game'
                    AND DATE(created_at) = today_date;
                    
                    -- Set progress to current unique game count
                    progress_increment := progress_increment - quest_record.progress;
                END IF;
            
            -- Accumulate daily winnings
            WHEN 'daily_winnings' THEN
                IF p_activity_type = 'win_game' THEN
                    progress_increment := p_activity_value;
                END IF;
            
            -- Lose streak and continue playing
            WHEN 'lose_streak_continue' THEN
                IF p_activity_type = 'play_game' THEN
                    -- Simplified implementation for now
                    progress_increment := 0;
                END IF;
            
            -- Play duration
            WHEN 'play_duration' THEN
                IF p_activity_type = 'game_session' THEN
                    progress_increment := p_activity_value; -- Duration in seconds
                END IF;
            
            -- Claim farming rewards
            WHEN 'claim_farming' THEN
                IF p_activity_type = 'claim_farming' THEN
                    progress_increment := 1;
                END IF;
            
            -- Stake PHP
            WHEN 'stake_php' THEN
                IF p_activity_type = 'stake_php' THEN
                    progress_increment := p_activity_value;
                END IF;
            
            -- Complete quests (count how many quests user has completed today)
            WHEN 'complete_quest' THEN
                IF p_activity_type = 'complete_quest' THEN
                    -- Count total completed quests for today
                    SELECT COUNT(*) INTO progress_increment
                    FROM daily_quests
                    WHERE user_id = p_user_id
                    AND date = today_date
                    AND is_completed = TRUE;
                    
                    -- Set progress to current completed quest count (absolute value, not increment)
                    progress_increment := progress_increment;
                END IF;
            
            -- Deposit PHP
            WHEN 'deposit_php' THEN
                IF p_activity_type = 'deposit' AND (p_metadata->>'status')::text = 'approved' THEN
                    progress_increment := p_activity_value;
                END IF;
            
            -- Withdraw PHP
            WHEN 'withdraw_php' THEN
                IF p_activity_type = 'withdraw' AND (p_metadata->>'status')::text = 'approved' THEN
                    progress_increment := p_activity_value;
                END IF;
            
            -- Convert currency
            WHEN 'convert_currency' THEN
                IF p_activity_type = 'convert_currency' THEN
                    progress_increment := p_activity_value;
                END IF;
            
            -- Exchange ITLOG
            WHEN 'exchange_itlog' THEN
                IF p_activity_type = 'exchange_itlog' THEN
                    progress_increment := 1;
                END IF;
            
            -- Single bet amount
            WHEN 'single_bet' THEN
                IF p_activity_type = 'place_bet' AND p_activity_value >= quest_record.target_value THEN
                    progress_increment := quest_record.target_value; -- Complete the quest
                END IF;
            
            -- ITLOG balance check
            WHEN 'itlog_balance' THEN
                IF p_activity_type IN ('claim_farming', 'exchange_itlog', 'win_game', 'deposit', 'balance_check') THEN
                    -- Check current ITLOG balance
                    SELECT COALESCE(itlog_tokens, 0) INTO progress_increment
                    FROM profiles
                    WHERE id = p_user_id;
                    
                    -- Set progress to absolute balance value, not increment
                    progress_increment := progress_increment - quest_record.progress;
                END IF;
            
            -- All games in a day
            WHEN 'all_games' THEN
                IF p_activity_type = 'play_game' THEN
                    -- Count unique games played today
                    SELECT COUNT(DISTINCT game_type) INTO progress_increment
                    FROM user_activities
                    WHERE user_id = p_user_id
                    AND activity_type = 'play_game'
                    AND DATE(created_at) = today_date
                    AND game_type IN ('mines', 'wheel-of-fortune', 'fortune-reels', 'blackjack', 'dice-roll');
                    
                    progress_increment := progress_increment - quest_record.progress;
                END IF;
            
            -- Farming streak
            WHEN 'farming_streak' THEN
                IF p_activity_type = 'claim_farming' THEN
                    progress_increment := 1;
                END IF;
            
            -- Maintain balance (special case)
            WHEN 'maintain_balance' THEN
                -- Check if user currently has required balance
                SELECT COALESCE(php_balance, 0) INTO current_balance
                FROM profiles
                WHERE id = p_user_id;
                
                IF current_balance >= quest_record.target_value THEN
                    progress_increment := quest_record.target_value; -- Complete quest
                END IF;
            
            -- Single game win
            WHEN 'single_game_win' THEN
                IF p_activity_type = 'win_game' AND p_activity_value >= quest_record.target_value THEN
                    progress_increment := quest_record.target_value; -- Complete the quest
                END IF;
            
            ELSE
                progress_increment := 0;
        END CASE;
        
        -- Update quest progress if there's an increment
        IF progress_increment > 0 THEN
            -- For complete_quest type, use absolute value instead of increment
            IF quest_record.task_type = 'complete_quest' THEN
                current_progress := progress_increment;
            ELSE
                current_progress := quest_record.progress + progress_increment;
            END IF;
            
            target_value := quest_record.target_value;
            
            -- Ensure progress doesn't exceed target
            IF current_progress >= target_value THEN
                current_progress := target_value;
                
                UPDATE daily_quests
                SET progress = current_progress, is_completed = TRUE
                WHERE id = quest_record.id;
                
                -- Track quest completion
                INSERT INTO user_activities (user_id, activity_type, activity_value, metadata)
                VALUES (p_user_id, 'complete_quest', 1, jsonb_build_object('quest_id', quest_record.id));
                
                -- Recursively check for quest completion dependent quests
                PERFORM update_quest_progress(p_user_id, 'complete_quest', 1, NULL, jsonb_build_object('quest_id', quest_record.id));
            ELSE
                UPDATE daily_quests
                SET progress = current_progress
                WHERE id = quest_record.id;
            END IF;
        END IF;
    END LOOP;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to claim quest rewards
CREATE OR REPLACE FUNCTION claim_quest_rewards(p_user_id UUID)
RETURNS JSON AS $$
DECLARE
    total_reward DECIMAL := 0;
    quest_ids UUID[] := ARRAY[]::UUID[];
    quest_record RECORD;
    reward_amount DECIMAL;
    result JSON;
    completed_count INTEGER;
BEGIN
    -- Check if user has completed all 3 quests today
    SELECT array_agg(dq.id), COUNT(*) INTO quest_ids, completed_count
    FROM daily_quests dq
    WHERE dq.user_id = p_user_id
    AND dq.date = CURRENT_DATE
    AND dq.is_completed = TRUE;
    
    -- Ensure exactly 3 quests are completed
    IF completed_count != 3 THEN
        RAISE EXCEPTION 'All 3 daily quests must be completed before claiming rewards';
    END IF;
    
    -- Check if rewards already claimed today
    PERFORM 1 FROM quest_rewards_claimed
    WHERE user_id = p_user_id AND date = CURRENT_DATE;
    
    IF FOUND THEN
        RAISE EXCEPTION 'Rewards already claimed today';
    END IF;
    
    -- Calculate total reward based on difficulty tiers
    total_reward := 0;
    FOR quest_record IN (
        SELECT qd.difficulty_tier, qd.reward_min, qd.reward_max
        FROM daily_quests dq
        JOIN quest_definitions qd ON dq.quest_definition_id = qd.id
        WHERE dq.user_id = p_user_id
        AND dq.date = CURRENT_DATE
        AND dq.is_completed = TRUE
    ) LOOP
        -- Random reward within tier range
        reward_amount := quest_record.reward_min + (RANDOM() * (quest_record.reward_max - quest_record.reward_min));
        total_reward := total_reward + reward_amount;
    END LOOP;
    
    -- Update user's ITLOG balance
    UPDATE profiles
    SET itlog_tokens = COALESCE(itlog_tokens, 0) + total_reward
    WHERE id = p_user_id;
    
    -- Record the reward claim
    INSERT INTO quest_rewards_claimed (user_id, date, total_reward, quest_ids)
    VALUES (p_user_id, CURRENT_DATE, total_reward, quest_ids);
    
    result := json_build_object(
        'total_reward', total_reward,
        'quest_ids', quest_ids
    );
    
    RETURN result;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to check and update quest completion dependencies
CREATE OR REPLACE FUNCTION check_quest_completion_dependencies(p_user_id UUID)
RETURNS VOID AS $$
DECLARE
    completed_count INTEGER;
    quest_record RECORD;
BEGIN
    -- Count how many quests are completed today
    SELECT COUNT(*) INTO completed_count
    FROM daily_quests
    WHERE user_id = p_user_id
    AND date = CURRENT_DATE
    AND is_completed = TRUE;
    
    -- Update any quests that depend on quest completion
    FOR quest_record IN (
        SELECT dq.id, qd.task_type, qd.target_value
        FROM daily_quests dq
        JOIN quest_definitions qd ON dq.quest_definition_id = qd.id
        WHERE dq.user_id = p_user_id
        AND dq.date = CURRENT_DATE
        AND dq.is_completed = FALSE
        AND qd.task_type = 'complete_quest'
    ) LOOP
        IF completed_count >= quest_record.target_value THEN
            UPDATE daily_quests
            SET progress = quest_record.target_value, is_completed = TRUE
            WHERE id = quest_record.id;
        ELSE
            UPDATE daily_quests
            SET progress = completed_count
            WHERE id = quest_record.id;
        END IF;
    END LOOP;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to check and update balance-based quests
CREATE OR REPLACE FUNCTION check_balance_quests(p_user_id UUID)
RETURNS VOID AS $$
DECLARE
    quest_record RECORD;
    current_balance DECIMAL;
    php_balance DECIMAL;
BEGIN
    -- Get current balances
    SELECT COALESCE(itlog_tokens, 0), COALESCE(php_balance, 0) 
    INTO current_balance, php_balance
    FROM profiles
    WHERE id = p_user_id;
    
    -- Check ITLOG balance quests
    FOR quest_record IN (
        SELECT dq.id, qd.task_type, qd.target_value
        FROM daily_quests dq
        JOIN quest_definitions qd ON dq.quest_definition_id = qd.id
        WHERE dq.user_id = p_user_id
        AND dq.date = CURRENT_DATE
        AND dq.is_completed = FALSE
        AND qd.task_type = 'itlog_balance'
    ) LOOP
        IF current_balance >= quest_record.target_value THEN
            UPDATE daily_quests
            SET progress = quest_record.target_value, is_completed = TRUE
            WHERE id = quest_record.id;
            
            -- Track quest completion
            INSERT INTO user_activities (user_id, activity_type, activity_value, metadata)
            VALUES (p_user_id, 'complete_quest', 1, jsonb_build_object('quest_id', quest_record.id));
        ELSE
            UPDATE daily_quests
            SET progress = current_balance
            WHERE id = quest_record.id;
        END IF;
    END LOOP;
    
    -- Check maintain balance quests
    FOR quest_record IN (
        SELECT dq.id, qd.task_type, qd.target_value
        FROM daily_quests dq
        JOIN quest_definitions qd ON dq.quest_definition_id = qd.id
        WHERE dq.user_id = p_user_id
        AND dq.date = CURRENT_DATE
        AND dq.is_completed = FALSE
        AND qd.task_type = 'maintain_balance'
    ) LOOP
        IF php_balance >= quest_record.target_value THEN
            UPDATE daily_quests
            SET progress = quest_record.target_value, is_completed = TRUE
            WHERE id = quest_record.id;
            
            -- Track quest completion
            INSERT INTO user_activities (user_id, activity_type, activity_value, metadata)
            VALUES (p_user_id, 'complete_quest', 1, jsonb_build_object('quest_id', quest_record.id));
        ELSE
            UPDATE daily_quests
            SET progress = php_balance
            WHERE id = quest_record.id;
        END IF;
    END LOOP;
    
    -- After updating balance quests, check for quest completion dependencies
    PERFORM check_quest_completion_dependencies(p_user_id);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to reset daily quests (to be called by a cron job)
CREATE OR REPLACE FUNCTION reset_daily_quests()
RETURNS VOID AS $$
BEGIN
    -- This function can be called daily to clean up old quest data if needed
    -- For now, we keep historical data for analytics
    NULL;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
