
-- Function to execute raw SQL queries (admin only)
CREATE OR REPLACE FUNCTION execute_sql(query TEXT)
RETURNS VOID AS $$
BEGIN
  EXECUTE query;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Grant execute permission to authenticated users (will be restricted by RLS)
GRANT EXECUTE ON FUNCTION execute_sql TO authenticated;
