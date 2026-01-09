-- SQLite Database Schema for Smart Parking Desktop App
-- This schema is used when the desktop app runs with SQLite instead of MySQL

-- Create camera_detection_logs table
CREATE TABLE IF NOT EXISTS camera_detection_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    camera_detection_id INTEGER NOT NULL,
    gate_id INTEGER,
    numberplate VARCHAR(50) NOT NULL,
    originalplate VARCHAR(50),
    detection_timestamp TIMESTAMP NOT NULL,
    utc_time TIMESTAMP,
    located_plate BOOLEAN DEFAULT 0,
    global_confidence DECIMAL(5,2),
    average_char_height DECIMAL(8,2),
    process_time INTEGER,
    plate_format INTEGER,
    country INTEGER,
    country_str VARCHAR(100),
    vehicle_left INTEGER DEFAULT 0,
    vehicle_top INTEGER DEFAULT 0,
    vehicle_right INTEGER DEFAULT 0,
    vehicle_bottom INTEGER DEFAULT 0,
    result_left INTEGER DEFAULT 0,
    result_top INTEGER DEFAULT 0,
    result_right INTEGER DEFAULT 0,
    result_bottom INTEGER DEFAULT 0,
    speed DECIMAL(8,2),
    lane_id INTEGER,
    direction INTEGER COMMENT '0=entry, 1=exit',
    make INTEGER,
    model INTEGER,
    color INTEGER,
    make_str VARCHAR(100),
    model_str VARCHAR(100),
    color_str VARCHAR(100),
    veclass_str VARCHAR(100),
    image_path VARCHAR(500),
    image_retail_path VARCHAR(500),
    width INTEGER,
    height INTEGER,
    list_id INTEGER,
    name_list_id INTEGER,
    evidences TEXT,
    br_ocurr INTEGER,
    br_time INTEGER,
    raw_data JSON,
    processed BOOLEAN DEFAULT 0,
    processed_at TIMESTAMP,
    processing_notes TEXT,
    processing_status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, pending_vehicle_type, processed, failed, manual_processing, completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_camera_detection_logs_numberplate ON camera_detection_logs(numberplate);
CREATE INDEX IF NOT EXISTS idx_camera_detection_logs_gate_id ON camera_detection_logs(gate_id);
CREATE INDEX IF NOT EXISTS idx_camera_detection_logs_detection_timestamp ON camera_detection_logs(detection_timestamp);
CREATE INDEX IF NOT EXISTS idx_camera_detection_logs_processing_status ON camera_detection_logs(processing_status);
CREATE INDEX IF NOT EXISTS idx_camera_detection_logs_processed ON camera_detection_logs(processed);
CREATE INDEX IF NOT EXISTS idx_camera_detection_logs_camera_detection_id ON camera_detection_logs(camera_detection_id);

-- Create local_vehicle_detections table (for desktop app specific tracking)
CREATE TABLE IF NOT EXISTS local_vehicle_detections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    camera_detection_log_id INTEGER NOT NULL,
    gate_id INTEGER,
    plate_number VARCHAR(50) NOT NULL,
    detection_timestamp TIMESTAMP NOT NULL,
    processing_status VARCHAR(50) DEFAULT 'new' COMMENT 'new, processing, completed, failed, archived',
    vehicle_found BOOLEAN DEFAULT 0,
    vehicle_id INTEGER,
    body_type_selected BOOLEAN DEFAULT 0,
    body_type_id INTEGER,
    operator_action VARCHAR(50) COMMENT 'captured, processed, cancelled, archived',
    operator_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(camera_detection_log_id) REFERENCES camera_detection_logs(id)
);

CREATE INDEX IF NOT EXISTS idx_local_vehicle_detections_status ON local_vehicle_detections(processing_status);
CREATE INDEX IF NOT EXISTS idx_local_vehicle_detections_gate_id ON local_vehicle_detections(gate_id);
CREATE INDEX IF NOT EXISTS idx_local_vehicle_detections_plate ON local_vehicle_detections(plate_number);
CREATE INDEX IF NOT EXISTS idx_local_vehicle_detections_timestamp ON local_vehicle_detections(detection_timestamp);

-- Create desktop_sync_queue table (for syncing with backend when available)
CREATE TABLE IF NOT EXISTS desktop_sync_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type VARCHAR(50) NOT NULL COMMENT 'detection, vehicle_passage, transaction',
    entity_id INTEGER,
    action VARCHAR(50) NOT NULL COMMENT 'create, update, delete',
    data JSON NOT NULL,
    synced BOOLEAN DEFAULT 0,
    sync_attempts INTEGER DEFAULT 0,
    last_sync_attempt TIMESTAMP,
    sync_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_desktop_sync_queue_synced ON desktop_sync_queue(synced);
CREATE INDEX IF NOT EXISTS idx_desktop_sync_queue_entity_type ON desktop_sync_queue(entity_type);
CREATE INDEX IF NOT EXISTS idx_desktop_sync_queue_created_at ON desktop_sync_queue(created_at);

-- Create gate_devices table (local copy)
CREATE TABLE IF NOT EXISTS gate_devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gate_id INTEGER,
    ip_address VARCHAR(50),
    device_type VARCHAR(50) COMMENT 'camera, gate_controller, printer',
    device_name VARCHAR(100),
    is_active BOOLEAN DEFAULT 1,
    last_heartbeat TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_gate_devices_gate_id ON gate_devices(gate_id);
CREATE INDEX IF NOT EXISTS idx_gate_devices_ip_address ON gate_devices(ip_address);
CREATE INDEX IF NOT EXISTS idx_gate_devices_is_active ON gate_devices(is_active);

-- Create detection_analytics table (for local statistics)
CREATE TABLE IF NOT EXISTS detection_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gate_id INTEGER,
    date DATE,
    total_detections INTEGER DEFAULT 0,
    processed_detections INTEGER DEFAULT 0,
    new_vehicles INTEGER DEFAULT 0,
    existing_vehicles INTEGER DEFAULT 0,
    failed_detections INTEGER DEFAULT 0,
    avg_processing_time_ms INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_detection_analytics_unique ON detection_analytics(gate_id, date);

-- Create vehicle_type_cache table (local lookup table)
CREATE TABLE IF NOT EXISTS vehicle_type_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    body_type_id INTEGER UNIQUE,
    body_type_name VARCHAR(100),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_vehicle_type_cache_body_type_id ON vehicle_type_cache(body_type_id);

-- Create local_configuration table (for app-specific settings)
CREATE TABLE IF NOT EXISTS local_configuration (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    data_type VARCHAR(50) COMMENT 'string, integer, boolean, json',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_local_configuration_key ON local_configuration(config_key);

-- Insert default configuration values
INSERT OR IGNORE INTO local_configuration (config_key, config_value, data_type) VALUES
    ('camera_detection_enabled', 'true', 'boolean'),
    ('auto_process_existing_vehicles', 'true', 'boolean'),
    ('polling_interval_ms', '2500', 'integer'),
    ('camera_fetch_interval_ms', '5000', 'integer'),
    ('max_sync_attempts', '5', 'integer'),
    ('detection_retention_days', '30', 'integer'),
    ('offline_mode_enabled', 'false', 'boolean');
