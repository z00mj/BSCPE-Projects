
-- Drop existing functions first
DROP FUNCTION IF EXISTS reset_all_php_balances();
DROP FUNCTION IF EXISTS reset_all_coins();
DROP FUNCTION IF EXISTS reset_all_itlog_tokens();
DROP FUNCTION IF EXISTS reset_all_balances();

-- Function to safely reset all PHP balances
CREATE OR REPLACE FUNCTION reset_all_php_balances()
RETURNS BOOLEAN AS $$
DECLARE
    affected_rows INTEGER := 0;
BEGIN
    -- Update all profiles with explicit column reference
    UPDATE public.profiles SET 
        php_balance = 0.00,
        updated_at = NOW()
    WHERE php_balance != 0.00;
    
    GET DIAGNOSTICS affected_rows = ROW_COUNT;
    
    -- Log the operation
    RAISE LOG 'Reset PHP balances to 0.00 for % users', affected_rows;
    
    -- Return success
    RETURN TRUE;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE LOG 'Error resetting PHP balances: % - %', SQLSTATE, SQLERRM;
        RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to safely reset all coins
CREATE OR REPLACE FUNCTION reset_all_coins()
RETURNS BOOLEAN AS $$
DECLARE
    affected_rows INTEGER := 0;
BEGIN
    -- Update all profiles with explicit column reference
    UPDATE public.profiles SET 
        coins = 0,
        updated_at = NOW()
    WHERE coins != 0;
    
    GET DIAGNOSTICS affected_rows = ROW_COUNT;
    
    -- Log the operation
    RAISE LOG 'Reset coins to 0 for % users', affected_rows;
    
    -- Return success
    RETURN TRUE;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE LOG 'Error resetting coins: % - %', SQLSTATE, SQLERRM;
        RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to safely reset all ITLOG tokens
CREATE OR REPLACE FUNCTION reset_all_itlog_tokens()
RETURNS BOOLEAN AS $$
DECLARE
    affected_rows INTEGER := 0;
BEGIN
    -- Update all profiles with explicit column reference
    UPDATE public.profiles SET 
        itlog_tokens = 0.00000000,
        updated_at = NOW()
    WHERE itlog_tokens != 0.00000000;
    
    GET DIAGNOSTICS affected_rows = ROW_COUNT;
    
    -- Log the operation
    RAISE LOG 'Reset ITLOG tokens to 0.00000000 for % users', affected_rows;
    
    -- Return success
    RETURN TRUE;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE LOG 'Error resetting ITLOG tokens: % - %', SQLSTATE, SQLERRM;
        RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to safely reset all balances
CREATE OR REPLACE FUNCTION reset_all_balances()
RETURNS BOOLEAN AS $$
DECLARE
    affected_rows INTEGER := 0;
BEGIN
    -- Update all profiles with explicit column reference
    UPDATE public.profiles SET 
        php_balance = 0.00, 
        coins = 0, 
        itlog_tokens = 0.00000000,
        updated_at = NOW()
    WHERE php_balance != 0.00 OR coins != 0 OR itlog_tokens != 0.00000000;
    
    GET DIAGNOSTICS affected_rows = ROW_COUNT;
    
    -- Log the operation
    RAISE LOG 'Reset all balances (PHP, coins, ITLOG) to 0 for % users', affected_rows;
    
    -- Return success
    RETURN TRUE;
    
EXCEPTION
    WHEN OTHERS THEN
        RAISE LOG 'Error resetting all balances: % - %', SQLSTATE, SQLERRM;
        RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Grant execute permissions to authenticated users (for admin access)
GRANT EXECUTE ON FUNCTION reset_all_php_balances() TO authenticated;
GRANT EXECUTE ON FUNCTION reset_all_coins() TO authenticated;
GRANT EXECUTE ON FUNCTION reset_all_itlog_tokens() TO authenticated;
GRANT EXECUTE ON FUNCTION reset_all_balances() TO authenticated;

-- Grant execute permissions to service role (for admin operations)
GRANT EXECUTE ON FUNCTION reset_all_php_balances() TO service_role;
GRANT EXECUTE ON FUNCTION reset_all_coins() TO service_role;
GRANT EXECUTE ON FUNCTION reset_all_itlog_tokens() TO service_role;
GRANT EXECUTE ON FUNCTION reset_all_balances() TO service_role;

-- Grant execute permissions to anon role (in case needed)
GRANT EXECUTE ON FUNCTION reset_all_php_balances() TO anon;
GRANT EXECUTE ON FUNCTION reset_all_coins() TO anon;
GRANT EXECUTE ON FUNCTION reset_all_itlog_tokens() TO anon;
GRANT EXECUTE ON FUNCTION reset_all_balances() TO anon;
