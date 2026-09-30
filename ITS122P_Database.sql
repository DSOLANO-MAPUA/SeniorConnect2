DROP TABLE IF EXISTS EVENT_RESOURCE;
DROP TABLE IF EXISTS AUDIT_LOG;
DROP TABLE IF EXISTS REGISTRATION;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS ANNOUNCEMENT;
DROP TABLE IF EXISTS RESOURCE;
DROP TABLE IF EXISTS LOCATION;
DROP TABLE IF EXISTS CATEGORY;
DROP TABLE IF EXISTS USER;

CREATE DATABASE ITS122P_Database;

USE ITS122P_Database;

CREATE TABLE users (
    user_id       SERIAL       PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    phone         VARCHAR(20)  NOT NULL UNIQUE,
    role          ENUM('admin', 'staff', 'attendee') DEFAULT 'attendee',
    -- plain 4-digit login PIN (set via /api/auth/register). CHAR, not INT, so a PIN like 0123 keeps its leading zero.
    pin_code      CHAR(4),
    is_online     BOOLEAN      DEFAULT FALSE,
    last_login_at TIMESTAMP    NULL
);

CREATE TABLE categories (
    category_id SERIAL       PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT
);

CREATE TABLE locations (
    location_id SERIAL       PRIMARY KEY,
    address     VARCHAR(255),
    zip         VARCHAR(20),
    map_link    VARCHAR(255)
);

CREATE TABLE resources (
    resource_id SERIAL       PRIMARY KEY,
	-- Removed location_id, thought about it looked pretty redundant
    name        VARCHAR(255) NOT NULL,
    type        VARCHAR(50),
    status            VARCHAR(50)
);

CREATE TABLE announcements (
    announcement_id SERIAL PRIMARY KEY,
    posted_by BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    time_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    schedule TIMESTAMP NULL,

    FOREIGN KEY (posted_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL
);

CREATE TABLE events (
    event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    created_by BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    location_id BIGINT UNSIGNED NULL,

    title VARCHAR(255) NOT NULL,
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    capacity INTEGER,

    FOREIGN KEY (created_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL,

    FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
        ON DELETE SET NULL,

    FOREIGN KEY (location_id)
        REFERENCES locations(location_id)
        ON DELETE SET NULL
);

CREATE TABLE registrations (
    registration_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NOT NULL,

    event_id BIGINT UNSIGNED NOT NULL,

    registration_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status VARCHAR(50),

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NULL,

    details TEXT,

    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
);


CREATE TABLE event_resources (
    event_resource_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_id BIGINT UNSIGNED NOT NULL,

    resource_id BIGINT UNSIGNED NOT NULL,

    quantity INTEGER,

    FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE,

    FOREIGN KEY (resource_id)
        REFERENCES resources(resource_id)
        ON DELETE CASCADE
);

-- NOTE: pin_code is stored as a plain 4-digit value (see /api/auth/register),
-- so the seed accounts below can log in right away with PIN 1234.
-- Login uses Full Name + Phone Number + PIN.

INSERT INTO users (
    name,
    phone,
    role,
    pin_code
)
VALUES (
    'Test Admin',
    '09123456789',
    'admin',
    '1234'
);

INSERT INTO announcements (
    posted_by,
    title,
    content
)
VALUES (
    1,
    'Test Announcement',
    'This is a test announcement.'
);

INSERT INTO users (
    name,
    phone,
    role,
    pin_code
)
VALUES (
    'John Test',
    '09123456780',
    'attendee',
    '1234'
);

INSERT INTO categories (
    name,
    description
)
VALUES (
    'Workshop',
    'Educational workshops and training sessions'
);

INSERT INTO locations (
    address,
    zip,
    map_link
)
VALUES (
    'Makati City Hall',
    '1200',
    'https://maps.google.com/'
);

INSERT INTO resources (
    name,
    type,
    status
)
VALUES (
    'Projector',
    'Equipment',
    'Available'
);

INSERT INTO events (
    created_by,
    category_id,
    location_id,
    title,
    start_time,
    end_time,
    capacity
)
VALUES (
    1,
    1,
    1,
    'Test Workshop',
    '2026-09-15 09:00:00',
    '2026-09-15 12:00:00',
    50
);

INSERT INTO registrations (
    user_id,
    event_id,
    status
)
VALUES (
    2,
    1,
    'Registered'
);

INSERT INTO audit_logs (
    user_id,
    details
)
VALUES (
    1,
    'Created the Test Workshop event.'
);

INSERT INTO event_resources (
    event_id,
    resource_id,
    quantity
)
VALUES (
    1,
    1,
    2
);

SELECT * FROM users;

show tables;