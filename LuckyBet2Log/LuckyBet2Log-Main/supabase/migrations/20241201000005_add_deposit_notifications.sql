
-- Create deposit_notifications table
CREATE TABLE deposit_notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  deposit_id UUID NOT NULL REFERENCES deposits(id) ON DELETE CASCADE,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create index for better performance
CREATE INDEX idx_deposit_notifications_user_id ON deposit_notifications(user_id);
CREATE INDEX idx_deposit_notifications_is_read ON deposit_notifications(is_read);

-- Enable RLS
ALTER TABLE deposit_notifications ENABLE ROW LEVEL SECURITY;

-- Create RLS policies
CREATE POLICY "Users can view their own deposit notifications" ON deposit_notifications
  FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Users can update their own deposit notifications" ON deposit_notifications
  FOR UPDATE USING (auth.uid() = user_id);

-- Allow admins to insert notifications
CREATE POLICY "Admins can insert deposit notifications" ON deposit_notifications
  FOR INSERT WITH CHECK (true);
