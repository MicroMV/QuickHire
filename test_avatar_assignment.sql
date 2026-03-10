-- Test script to manually assign an existing avatar to a user
-- This will help us test if the avatar display is working

-- Update user ID 2 (the programmer) with an existing avatar
UPDATE jobseeker_profiles 
SET profile_picture_url = 'uploads/avatars/avatar_433f6cbfc6b6c79e.jpg' 
WHERE user_id = 2;

-- Update user ID 1 (the employer) with an existing avatar  
UPDATE employer_profiles 
SET profile_picture_url = 'uploads/avatars/avatar_95865d20da997c8b.jpg' 
WHERE user_id = 1;

-- Check the results
SELECT user_id, profile_picture_url FROM jobseeker_profiles WHERE user_id = 2;
SELECT user_id, profile_picture_url FROM employer_profiles WHERE user_id = 1;