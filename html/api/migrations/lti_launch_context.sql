-- LTI Launch Context Table
-- Stores information needed to send grades back to external LMS platforms

CREATE TABLE IF NOT EXISTS `lti_launch_context` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `consumer_id` INT NOT NULL,
  `outcome_service_url` VARCHAR(500) NOT NULL,
  `result_sourcedid` VARCHAR(500) NOT NULL,
  `consumer_secret` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `user_course_unique` (`user_id`, `course_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`consumer_id`) REFERENCES `lti_consumers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add score column to completed_lessons if it doesn't exist
ALTER TABLE `completed_lessons` 
ADD COLUMN IF NOT EXISTS `score` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Score from 0-1 or 0-100';
