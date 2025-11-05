<?php
// config.php - Database configuratie
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hardware_inventory');
define('XML_DIR', __DIR__ . '/xml_files/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Zorg ervoor dat directories bestaan
if (!is_dir(XML_DIR)) mkdir(XML_DIR, 0777, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
