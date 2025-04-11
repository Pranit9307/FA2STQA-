-- Add bio column to users table
ALTER TABLE users
ADD COLUMN bio TEXT DEFAULT NULL AFTER email; 