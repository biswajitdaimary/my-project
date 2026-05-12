-- Add status column to trainer_availability if it doesn't exist
ALTER TABLE trainer_availability 
  ADD COLUMN IF NOT EXISTS `status` ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
