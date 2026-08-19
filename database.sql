-- Create captures table for motion capture management
-- This table stores capture records with their status and file availability

CREATE TABLE IF NOT EXISTS `captures` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT 'Name of the capture/animation',
    `theme` varchar(255) NOT NULL COMMENT 'Theme or category for grouping captures',
    `captured` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether the capture has been recorded (0=no, 1=yes)',
    `captured_time` timestamp NULL DEFAULT NULL COMMENT 'When the capture was completed',
    `has_fbx` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether FBX file is available (0=no, 1=yes)',
    `has_glb` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether GLB file is available (0=no, 1=yes)',
    `has_csv` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether CSV file is available (0=no, 1=yes)',
    `has_mp4` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether MP4 file is available (0=no, 1=yes)',
    `video_url` varchar(500) DEFAULT '' COMMENT 'URL to the MP4 video file',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the capture record was created',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When the record was last updated',
    PRIMARY KEY (`id`),
    KEY `idx_theme` (`theme`),
    KEY `idx_captured` (`captured`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Motion capture management table';

-- Insert some sample data for testing (optional)
INSERT INTO `captures` (`name`, `theme`, `captured`, `has_fbx`, `has_glb`, `has_csv`, `has_mp4`, `video_url`) VALUES
('HELLO_WORLD', 'greetings', 1, 1, 1, 0, 1, 'https://example.com/videos/hello_world.mp4'),
('GOODBYE', 'greetings', 0, 0, 0, 0, 0, ''),
('JUMP_ANIMATION', 'actions', 1, 1, 0, 1, 1, 'https://example.com/videos/jump_animation.mp4'),
('WALK_CYCLE', 'actions', 0, 0, 0, 0, 0, ''),
('DANCE_MOVE_01', 'entertainment', 1, 1, 1, 1, 1, 'https://example.com/videos/dance_move_01.mp4');

-- Update captured_time for captured items
UPDATE `captures` SET `captured_time` = NOW() WHERE `captured` = 1 AND `captured_time` IS NULL;

-- Add video_url column to existing tables (migration script)
-- Uncomment the following line if you need to add the column to an existing table:
-- ALTER TABLE `captures` ADD COLUMN `video_url` varchar(500) DEFAULT '' COMMENT 'URL to the MP4 video file' AFTER `has_mp4`;
