
-- Create triggers to notify admins of new withdrawal and deposit requests

-- Function to notify admins of new withdrawal requests
CREATE OR REPLACE FUNCTION notify_admins_of_withdrawal()
RETURNS TRIGGER AS $$
DECLARE
    admin_user RECORD;
    notification_message TEXT;
BEGIN
    -- Only notify on new withdrawal requests (INSERT)
    IF TG_OP = 'INSERT' THEN
        -- Get the username for the notification message
        SELECT username INTO notification_message
        FROM profiles 
        WHERE user_id = NEW.user_id;
        
        notification_message := notification_message || ' has requested a withdrawal of ₱' || NEW.amount::TEXT;
        
        -- Create notifications for all admin users
        FOR admin_user IN 
            SELECT user_id FROM profiles WHERE is_admin = true
        LOOP
            INSERT INTO withdrawal_notifications (user_id, withdrawal_id, message, is_read)
            VALUES (admin_user.user_id, NEW.id, notification_message, false);
        END LOOP;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to notify admins of new deposit requests
CREATE OR REPLACE FUNCTION notify_admins_of_deposit()
RETURNS TRIGGER AS $$
DECLARE
    admin_user RECORD;
    notification_message TEXT;
BEGIN
    -- Only notify on new deposit requests (INSERT)
    IF TG_OP = 'INSERT' THEN
        -- Get the username for the notification message
        SELECT username INTO notification_message
        FROM profiles 
        WHERE user_id = NEW.user_id;
        
        notification_message := notification_message || ' has submitted a deposit of ₱' || NEW.amount::TEXT || ' via ' || NEW.payment_method;
        
        -- Create notifications for all admin users
        FOR admin_user IN 
            SELECT user_id FROM profiles WHERE is_admin = true
        LOOP
            INSERT INTO deposit_notifications (user_id, deposit_id, message, is_read)
            VALUES (admin_user.user_id, NEW.id, notification_message, false);
        END LOOP;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Create triggers
DROP TRIGGER IF EXISTS trigger_notify_admins_withdrawal ON withdrawals;
CREATE TRIGGER trigger_notify_admins_withdrawal
    AFTER INSERT ON withdrawals
    FOR EACH ROW
    EXECUTE FUNCTION notify_admins_of_withdrawal();

DROP TRIGGER IF EXISTS trigger_notify_admins_deposit ON deposits;
CREATE TRIGGER trigger_notify_admins_deposit
    AFTER INSERT ON deposits
    FOR EACH ROW
    EXECUTE FUNCTION notify_admins_of_deposit();
