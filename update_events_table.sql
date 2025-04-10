-- Add image_path column to events table
ALTER TABLE events
ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER location;

-- Add created_by column to events table
ALTER TABLE events
ADD COLUMN created_by INT AFTER is_featured;

-- Add foreign key for created_by
ALTER TABLE events
ADD CONSTRAINT fk_events_created_by
FOREIGN KEY (created_by) REFERENCES users(id)
ON DELETE SET NULL;

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create tags table
CREATE TABLE IF NOT EXISTS tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create event_tags junction table
CREATE TABLE IF NOT EXISTS event_tags (
    event_id INT,
    tag_id INT,
    PRIMARY KEY (event_id, tag_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Add category_id to events table
ALTER TABLE events
ADD COLUMN category_id INT,
ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Insert some default categories
INSERT INTO categories (name, description) VALUES
('Business', 'Professional and corporate events'),
('Social', 'Casual gatherings and meetups'),
('Education', 'Workshops, seminars, and learning events'),
('Sports', 'Athletic competitions and sports-related events'),
('Technology', 'Tech conferences, hackathons, and meetups'),
('Arts', 'Cultural events, exhibitions, and performances'),
('Community', 'Local community gatherings and initiatives');

-- Insert some default tags
INSERT INTO tags (name) VALUES
('Networking'),
('Workshop'),
('Conference'),
('Meetup'),
('Seminar'),
('Exhibition'),
('Competition'),
('Fundraiser'),
('Virtual'),
('In-person');

-- Add price and available spots columns to events table
ALTER TABLE events
ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00 AFTER capacity,
ADD COLUMN available_spots INT DEFAULT NULL AFTER price;

-- Update existing events to set available_spots equal to capacity
UPDATE events SET available_spots = capacity WHERE available_spots IS NULL;

-- Add event_type column to events table
ALTER TABLE events
ADD COLUMN event_type ENUM('free', 'paid', 'donation') DEFAULT 'free' AFTER price; 