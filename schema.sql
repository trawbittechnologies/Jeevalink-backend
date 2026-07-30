-- Jeevalink PostgreSQL Schema Definition

-- Drop tables if they exist to start fresh
DROP TABLE IF EXISTS complaints CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS blood_requests CASCADE;
DROP TABLE IF EXISTS password_reset_tokens CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Drop custom types if they exist
DROP TYPE IF EXISTS user_role;
DROP TYPE IF EXISTS user_status;
DROP TYPE IF EXISTS urgency_level;
DROP TYPE IF EXISTS request_status;
DROP TYPE IF EXISTS notification_type;
DROP TYPE IF EXISTS complaint_status;

-- Create custom enum types
CREATE TYPE user_role AS ENUM ('technical_admin', 'super_admin', 'block_admin', 'volunteer', 'unit_squad', 'user');
CREATE TYPE user_status AS ENUM ('Active', 'Pending Approval', 'Suspended', 'Rejected');
CREATE TYPE urgency_level AS ENUM ('Normal', 'Urgent', 'Emergency SOS');
CREATE TYPE request_status AS ENUM ('Pending', 'Fulfilled');
CREATE TYPE notification_type AS ENUM ('SOS', 'Reward', 'Match', 'Fulfilled', 'Warning');
CREATE TYPE complaint_status AS ENUM ('Pending', 'Resolved');

-- --------------------------------------------------------
-- Table structure for table users
-- --------------------------------------------------------
CREATE TABLE users (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  mobile VARCHAR(20) NOT NULL UNIQUE,
  secondary_contact_name VARCHAR(255) DEFAULT NULL,
  secondary_phone VARCHAR(20) DEFAULT NULL,
  whatsapp_number VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role user_role NOT NULL DEFAULT 'user',
  blood_group VARCHAR(5) NOT NULL DEFAULT 'N/A',
  city VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  organization_name VARCHAR(255) DEFAULT NULL,
  volunteer_type VARCHAR(100) DEFAULT NULL,
  pin_code VARCHAR(10) DEFAULT NULL,
  pincode VARCHAR(10) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  remarks TEXT DEFAULT NULL,
  full_address TEXT DEFAULT NULL,
  weight INT DEFAULT NULL,
  date_of_birth DATE DEFAULT NULL,
  dob DATE DEFAULT NULL,
  last_donated_date DATE DEFAULT NULL,
  profile_picture TEXT DEFAULT NULL,
  id_proof_front TEXT DEFAULT NULL,
  id_proof_back TEXT DEFAULT NULL,
  sex VARCHAR(20) DEFAULT NULL,
  available_for_donation BOOLEAN NOT NULL DEFAULT TRUE,
  reward_points INT NOT NULL DEFAULT 100,
  lives_saved INT NOT NULL DEFAULT 0,
  total_donations INT NOT NULL DEFAULT 0,
  status user_status NOT NULL DEFAULT 'Active',
  eligibility_status VARCHAR(50) DEFAULT 'Pending Check',
  eligibility_checked_at TIMESTAMP DEFAULT NULL,
  is_verified BOOLEAN NOT NULL DEFAULT FALSE,
  expo_push_token TEXT DEFAULT NULL,
  fcm_token TEXT DEFAULT NULL,
  latitude DECIMAL(10,8) DEFAULT NULL,
  longitude DECIMAL(11,8) DEFAULT NULL,
  notification_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_blood_group ON users (blood_group);
CREATE INDEX idx_users_district ON users (district);
CREATE INDEX idx_users_city ON users (city);
CREATE INDEX idx_users_status ON users (status);

-- --------------------------------------------------------
-- Table structure for table password_reset_tokens
-- --------------------------------------------------------
CREATE TABLE password_reset_tokens (
  email VARCHAR(255) PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table structure for table notification_logs
-- --------------------------------------------------------
CREATE TABLE notification_logs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  fcm_token TEXT,
  title VARCHAR(255),
  body TEXT,
  data JSONB,
  status VARCHAR(30) DEFAULT 'pending',
  fcm_message_id TEXT,
  error_message TEXT,
  attempt INT DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notification_logs_user ON notification_logs (user_id);
CREATE INDEX idx_notification_logs_status ON notification_logs (status);

-- --------------------------------------------------------
-- Table structure for table blood_requests
-- --------------------------------------------------------
CREATE TABLE blood_requests (
  id BIGSERIAL PRIMARY KEY,
  requested_by BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  patient_name VARCHAR(255) NOT NULL,
  blood_group VARCHAR(5) NOT NULL,
  units_required INT NOT NULL DEFAULT 1,
  hospital_name VARCHAR(255) NOT NULL,
  hospital_address TEXT DEFAULT NULL,
  city VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  location TEXT DEFAULT NULL,
  contact_number VARCHAR(20) NOT NULL,
  contact_person_name VARCHAR(255) DEFAULT NULL,
  required_by_date TIMESTAMP NOT NULL,
  urgency_level urgency_level NOT NULL DEFAULT 'Normal',
  additional_notes TEXT DEFAULT NULL,
  status request_status NOT NULL DEFAULT 'Pending',
  verified BOOLEAN NOT NULL DEFAULT FALSE,
  accepted_by BIGINT DEFAULT NULL REFERENCES users (id) ON DELETE SET NULL,
  fulfilled_by BIGINT DEFAULT NULL REFERENCES users (id) ON DELETE SET NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_requests_blood_group ON blood_requests (blood_group);
CREATE INDEX idx_requests_district ON blood_requests (district);
CREATE INDEX idx_requests_city ON blood_requests (city);
CREATE INDEX idx_requests_urgency ON blood_requests (urgency_level);
CREATE INDEX idx_requests_status ON blood_requests (status);

-- --------------------------------------------------------
-- Table structure for table notifications
-- --------------------------------------------------------
CREATE TABLE notifications (
  id BIGSERIAL PRIMARY KEY,
  recipient_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type notification_type NOT NULL,
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_recipient ON notifications (recipient_id);
CREATE INDEX idx_notifications_read ON notifications (is_read);

-- --------------------------------------------------------
-- Table structure for table complaints
-- --------------------------------------------------------
CREATE TABLE complaints (
  id BIGSERIAL PRIMARY KEY,
  reporter_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  target_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  reason TEXT NOT NULL,
  status complaint_status NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_complaints_status ON complaints (status);

-- --------------------------------------------------------
-- Triggers for updating the updated_at timestamp automatically
-- --------------------------------------------------------
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at 
    BEFORE UPDATE ON users 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_blood_requests_updated_at 
    BEFORE UPDATE ON blood_requests 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();
