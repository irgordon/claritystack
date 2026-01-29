CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Users & Auth
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255), -- Nullable for social login/magic links
    role VARCHAR(50) DEFAULT 'client', -- admin, client
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE auth_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE download_tokens (
    id SERIAL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    project_id UUID NOT NULL, -- FK added below
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Configuration
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    business_name VARCHAR(255),
    public_config JSONB DEFAULT '{}', -- Branding, Timeouts
    private_config JSONB DEFAULT '{}', -- API Keys (Encrypted)
    is_installed BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE email_templates (
    id SERIAL PRIMARY KEY,
    key_name VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(255),
    body_content TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Projects & Files
CREATE TABLE packages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    description TEXT,
    price_cents INT NOT NULL,
    features JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'draft',
    client_email VARCHAR(255) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    stripe_payment_intent VARCHAR(100),
    booking_date DATE,
    package_snapshot JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE photos (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    project_id UUID REFERENCES projects(id) ON DELETE CASCADE,
    original_filename VARCHAR(255),
    system_filename VARCHAR(255),
    thumb_path VARCHAR(255),
    metadata JSONB DEFAULT '{}', -- EXIF Data
    file_size_bytes BIGINT,
    mime_type VARCHAR(50),
    file_hash CHAR(64),
    status VARCHAR(20) DEFAULT 'pending', -- approved, rejected
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. CMS & Logs
CREATE TABLE pages (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(255),
    meta_description TEXT,
    blocks JSONB DEFAULT '[]',
    is_published BOOLEAN DEFAULT FALSE,
    og_image_url VARCHAR(255),
    priority FLOAT DEFAULT 0.5,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE system_logs (
    id SERIAL PRIMARY KEY,
    severity VARCHAR(20),
    category VARCHAR(50),
    message TEXT,
    context JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
