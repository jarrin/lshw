<?php
// ---------- includes/database.php ----------
require_once __DIR__ . '/../config.php';

function db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        die('DB connection failed: ' . $conn->connect_error);
    }

    // Create DB + select
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);

    // Create tables
    $schema = [];

    $schema[] = "
    CREATE TABLE IF NOT EXISTS computers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(255) UNIQUE,
        manufacturer VARCHAR(255),
        computer_model VARCHAR(255),
        computer_type VARCHAR(255),
        cpu_count INT DEFAULT 0,
        cpu_model VARCHAR(255),
        cpu_speed VARCHAR(255),
        memory_total VARCHAR(255),
        motherboard VARCHAR(255),
        bios_version VARCHAR(255),
        xml_file VARCHAR(255),
        import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (serial_number),
        INDEX (manufacturer),
        INDEX (import_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS ram_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        size_gb DECIMAL(6,2) NULL,
        type VARCHAR(50) NULL,
        speed_mhz INT NULL,
        manufacturer VARCHAR(255) NULL,
        form_factor VARCHAR(50) NULL,
        serial_number VARCHAR(255) NULL,
        part_number VARCHAR(255) NULL,
        slot VARCHAR(50) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS storage_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        model VARCHAR(255) NULL,
        vendor VARCHAR(255) NULL,
        size_gb DECIMAL(10,2) NULL,
        type VARCHAR(50) NULL,            -- HDD/SSD/NVMe
        interface VARCHAR(50) NULL,       -- SATA/NVMe/USB
        serial VARCHAR(255) NULL,
        is_ssd TINYINT(1) DEFAULT NULL,
        media_type VARCHAR(50) NULL,      -- SSD/Platter e.d. (DriveMediaType)
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS network_interfaces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        name VARCHAR(255) NULL,
        description VARCHAR(255) NULL,
        mac_address VARCHAR(50) NULL,
        speed_mbps INT NULL,
        vendor VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        interface_type VARCHAR(50) NULL,  -- Wireless/Ethernet
        pci_id VARCHAR(50) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS gpus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        vendor VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        pci_id VARCHAR(50) NULL,
        memory_gb DECIMAL(6,2) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS drives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        type VARCHAR(50) NULL,            -- CD/DVD/BD
        vendor VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        description VARCHAR(255) NULL,
        capabilities TEXT NULL,
        interface VARCHAR(50) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS batteries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        type VARCHAR(255) NULL,           -- Li-ion, Li-poly, ...
        capacity_wh DECIMAL(10,2) NULL,
        manufacturer VARCHAR(255) NULL,
        product VARCHAR(255) NULL,
        serial_number VARCHAR(255) NULL,
        voltage_now_mv INT NULL,
        voltage_min_design_mv INT NULL,
        voltage_max_design_mv INT NULL,
        status VARCHAR(50) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS sound_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        vendor VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        description VARCHAR(255) NULL,
        pci_id VARCHAR(50) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $schema[] = "
    CREATE TABLE IF NOT EXISTS storage_controllers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        computer_id INT NOT NULL,
        vendor VARCHAR(255) NULL,
        model VARCHAR(255) NULL,
        description VARCHAR(255) NULL,
        type VARCHAR(50) NULL,
        pci_id VARCHAR(50) NULL,
        FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($schema as $sql) {
        if (!$conn->query($sql)) {
            die('Schema error: ' . $conn->error);
        }
    }

    // Ensure legacy/missing columns exist (migration safety)
    // Some installs might have an older 'drives' table without 'capabilities' column.
    $colCheck = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string(DB_NAME) . "' AND TABLE_NAME = 'drives' AND COLUMN_NAME = 'capabilities'");
    if ($colCheck && $colCheck->num_rows === 0) {
        // Add the column if it doesn't exist
        $conn->query("ALTER TABLE drives ADD COLUMN capabilities TEXT NULL");
    }

    return $conn;
}