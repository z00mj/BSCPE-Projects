
-- Function to safely clear all user data and progress
CREATE OR REPLACE FUNCTION clear_user_data(p_user_id UUID)
RETURNS BOOLEAN AS $$
DECLARE
    rec_count INTEGER;
    total_deleted INTEGER := 0;
    user_exists BOOLEAN := FALSE;
BEGIN
    -- First check if user exists
    SELECT EXISTS(SELECT 1 FROM profiles WHERE user_id = p_user_id) INTO user_exists;
    
    IF NOT user_exists THEN
        RAISE LOG 'User % does not exist', p_user_id;
        RETURN FALSE;
    END IF;
    
    RAISE LOG 'Starting data cleanup for user %', p_user_id;
    
    -- Clear all user progress and data except the profile itself
    -- Delete in order to avoid foreign key constraints
    
    -- Delete notifications first (they reference other tables)
    DELETE FROM deposit_notifications WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % deposit notifications for user %', rec_count, p_user_id;
    
    DELETE FROM withdrawal_notifications WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % withdrawal notifications for user %', rec_count, p_user_id;
    
    -- Delete farming sessions
    DELETE FROM farming_sessions WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % farming sessions for user %', rec_count, p_user_id;
    
    -- Delete user pets and eggs
    DELETE FROM user_pets WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % user pets for user %', rec_count, p_user_id;
    
    -- Delete user eggs (corrected table name)
    DELETE FROM user_eggs WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % user eggs for user %', rec_count, p_user_id;
    
    -- Delete financial records
    DELETE FROM deposits WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % deposits for user %', rec_count, p_user_id;
    
    DELETE FROM withdrawals WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % withdrawals for user %', rec_count, p_user_id;
    
    DELETE FROM appeals WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % appeals for user %', rec_count, p_user_id;
    
    -- Delete game and activity history
    DELETE FROM game_history WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % game history records for user %', rec_count, p_user_id;
    
    DELETE FROM earning_history WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % earning history records for user %', rec_count, p_user_id;
    
    DELETE FROM user_activities WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % user activities for user %', rec_count, p_user_id;
    
    -- Delete quest related data
    DELETE FROM quest_rewards_claimed WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % quest rewards claimed for user %', rec_count, p_user_id;
    
    DELETE FROM daily_quests WHERE user_id = p_user_id;
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    total_deleted := total_deleted + rec_count;
    RAISE LOG 'Deleted % daily quests for user %', rec_count, p_user_id;
    
    -- Reset balances and ban status
    UPDATE profiles 
    SET php_balance = 0, 
        coins = 0, 
        itlog_tokens = 0, 
        is_banned = false, 
        ban_reason = null,
        updated_at = NOW()
    WHERE user_id = p_user_id;
    
    GET DIAGNOSTICS rec_count = ROW_COUNT;
    RAISE LOG 'Updated profile for user %, rows affected: %', p_user_id, rec_count;
    
    -- Final verification that the user profile was updated
    IF rec_count = 0 THEN
        RAISE LOG 'Error: Failed to update profile for user %', p_user_id;
        RETURN FALSE;
    END IF;
    
    RAISE LOG 'Successfully cleared all data for user %. Total records deleted: %', p_user_id, total_deleted;
    RETURN TRUE;
    
EXCEPTION
    WHEN OTHERS THEN
        -- Log the detailed error and return false
        RAISE LOG 'Error clearing user data for user %: % - %', p_user_id, SQLSTATE, SQLERRM;
        RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
