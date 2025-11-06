<?php
// ---------- config.php ----------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hardware_inventory');

define('BASE_DIR', __DIR__);
define('XML_DIR', BASE_DIR . '/xml_files/');
define('UPLOAD_DIR', BASE_DIR . '/uploads/');

// Bulk import limits
define('IMPORT_ZIP_LIMIT', 650); // max XML files to import from a single ZIP (adjusted to 650)
define('IMPORT_MEMORY_LIMIT', '1024M');

// Zorg dat directories bestaan
if (!is_dir(XML_DIR)) @mkdir(XML_DIR, 0777, true);
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);

// Error reporting kun je hier centraal sturen
// ini_set('display_errors', 0);
// error_reporting(E_ALL);