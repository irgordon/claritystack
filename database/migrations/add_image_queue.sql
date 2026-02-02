CREATE TABLE image_queue (
    id SERIAL PRIMARY KEY,
    original_path VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NOT NULL,
    width INT DEFAULT 400,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_image_queue_status ON image_queue(status);
