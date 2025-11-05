<?php
require_once __DIR__ . '/config.php';

// Database initialisatie
function initDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Database aanmaken
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);
    
    // Tabel aanmaken met alle vereiste velden
    $sql = "CREATE TABLE IF NOT EXISTS computers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(255) UNIQUE,
        manufacturer VARCHAR(255),
        computer_type VARCHAR(255),
        cpu_count INT DEFAULT 0,
        cpu_model VARCHAR(255),
        cpu_speed VARCHAR(255),
        memory_total VARCHAR(255),
        memory_type VARCHAR(255),
        gpu_list LONGTEXT,
        sound_card VARCHAR(255),
        storage_controller LONGTEXT,
        network_interfaces LONGTEXT,
        optical_drive VARCHAR(10),
        motherboard VARCHAR(255),
        bios_version VARCHAR(255),
        xml_file VARCHAR(255),
        import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (serial_number),
        INDEX (manufacturer),
        INDEX (import_date)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    $conn->query($sql);
    return $conn;
}

// XML parseren en gegevens extraheren
function parseXMLFile($filepath) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($filepath);
    
    if (!$xml) {
        return false;
    }
    
    $data = [
        'serial_number' => 'UNKNOWN',
        'manufacturer' => 'UNKNOWN',
        'computer_type' => 'UNKNOWN',
        'cpu_count' => 0,
        'cpu_model' => 'UNKNOWN',
        'cpu_speed' => 'UNKNOWN',
        'memory_total' => 'UNKNOWN',
        'memory_type' => 'UNKNOWN',
        'gpu_list' => 'UNKNOWN',
        'sound_card' => 'UNKNOWN',
        'storage_controller' => 'UNKNOWN',
        'network_interfaces' => 'UNKNOWN',
        'optical_drive' => 'Nee',
        'motherboard' => 'UNKNOWN',
        'bios_version' => 'UNKNOWN'
    ];
    
    // Serienummer uit root node ID
    $nodeId = (string)$xml->attributes()->id;
    if ($nodeId) {
        $data['serial_number'] = $nodeId;
    }
    
    // Systeem informatie
    $data['computer_type'] = 'Computer';
    
    // Memory - System memory node
    $memNode = $xml->xpath('//node[@id="memory"]');
    if ($memNode) {
        $size = (string)$memNode[0]->size;
        if ($size) {
            $sizeBytes = (int)$size;
            $sizeGB = round($sizeBytes / (1024 * 1024 * 1024), 2);
            $data['memory_total'] = $sizeGB . ' GB';
        }
    }
    
    // Processor
    $cpus = $xml->xpath('//node[@class="processor"]');
    $data['cpu_count'] = count($cpus);
    
    if ($cpus) {
        $cpu = $cpus[0];
        $product = (string)$cpu->product;
        $vendor = (string)$cpu->vendor;
        
        if ($product && $vendor) {
            $data['cpu_model'] = $vendor . ' ' . $product;
        } elseif ($product) {
            $data['cpu_model'] = $product;
        }
        
        // CPU Speed uit version
        $version = (string)$cpu->version;
        if ($version) {
            $data['cpu_speed'] = $version;
        }
    }
    
    // GPU / Display
    $gpus = [];
    $displayNodes = $xml->xpath('//node[@class="display"]');
    foreach ($displayNodes as $gpu) {
        $product = (string)$gpu->product;
        $vendor = (string)$gpu->vendor;
        
        if ($product && $vendor) {
            $gpus[] = $vendor . ' ' . $product;
        } elseif ($product) {
            $gpus[] = $product;
        }
    }
    if (count($gpus) > 0) {
        $data['gpu_list'] = implode(' | ', $gpus);
    }
    
    // Sound / Multimedia
    $sound = $xml->xpath('//node[@class="multimedia"]');
    if ($sound) {
        $product = (string)$sound[0]->product;
        $vendor = (string)$sound[0]->vendor;
        
        if ($product && $vendor) {
            $data['sound_card'] = $vendor . ' ' . $product;
        } elseif ($product) {
            $data['sound_card'] = $product;
        }
    }
    
    // Storage controller
    $storage = [];
    $storageNodes = $xml->xpath('//node[@class="storage"]');
    foreach ($storageNodes as $stor) {
        $product = (string)$stor->product;
        $vendor = (string)$stor->vendor;
        
        if ($product && $vendor) {
            $storage[] = $vendor . ' ' . $product;
        } elseif ($product) {
            $storage[] = $product;
        }
    }
    if (count($storage) > 0) {
        $data['storage_controller'] = implode(' | ', $storage);
    }
    
    // Network interfaces
    $networks = [];
    $netNodes = $xml->xpath('//node[@class="network"]');
    foreach ($netNodes as $net) {
        $product = (string)$net->product;
        $desc = (string)$net->description;
        $logicalname = (string)$net->logicalname;
        
        $netInfo = $product ? $product : $desc;
        if ($logicalname) {
            $netInfo .= ' (' . $logicalname . ')';
        }
        if ($netInfo) {
            $networks[] = $netInfo;
        }
    }
    if (count($networks) > 0) {
        $data['network_interfaces'] = implode(' | ', $networks);
    }
    
    // Motherboard
    $core = $xml->xpath('//node[@id="core"]');
    if ($core) {
        $desc = (string)$core[0]->description;
        if ($desc) {
            $data['motherboard'] = $desc;
        }
    }
    
    $data['optical_drive'] = 'Nee';
    
    return $data;
}

// Gegevens importeren in database
function importXMLToDatabase($filepath, $conn) {
    $data = parseXMLFile($filepath);
    
    if (!$data) {
        return false;
    }
    
    // Als serienummer nog steeds UNKNOWN is, genereer er eentje
    if ($data['serial_number'] === 'UNKNOWN') {
        $data['serial_number'] = 'AUTO_' . time() . '_' . bin2hex(random_bytes(4));
    }
    
    // Bereid de SQL statement voor
    $sql = "INSERT INTO computers (serial_number, manufacturer, computer_type, cpu_count, cpu_model, 
            cpu_speed, memory_total, memory_type, gpu_list, sound_card, storage_controller, 
            network_interfaces, optical_drive, motherboard, bios_version, xml_file) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            manufacturer=VALUES(manufacturer), computer_type=VALUES(computer_type),
            cpu_count=VALUES(cpu_count), cpu_model=VALUES(cpu_model),
            cpu_speed=VALUES(cpu_speed), memory_total=VALUES(memory_total),
            memory_type=VALUES(memory_type), gpu_list=VALUES(gpu_list),
            sound_card=VALUES(sound_card), storage_controller=VALUES(storage_controller),
            network_interfaces=VALUES(network_interfaces), optical_drive=VALUES(optical_drive),
            motherboard=VALUES(motherboard), bios_version=VALUES(bios_version),
            xml_file=VALUES(xml_file), updated_date=CURRENT_TIMESTAMP";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("sssissssssssssss",
        $data['serial_number'],
        $data['manufacturer'],
        $data['computer_type'],
        $data['cpu_count'],
        $data['cpu_model'],
        $data['cpu_speed'],
        $data['memory_total'],
        $data['memory_type'],
        $data['gpu_list'],
        $data['sound_card'],
        $data['storage_controller'],
        $data['network_interfaces'],
        $data['optical_drive'],
        $data['motherboard'],
        $data['bios_version'],
        $filepath
    );
    
    return $stmt->execute();
}

// Export naar Excel
function exportToExcel($conn) {
    $result = $conn->query("SELECT * FROM computers ORDER BY import_date DESC");
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    // Headers instellen voor CSV (Excel kan dit beter lezen)
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hardware_inventory_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output stream
    $output = fopen('php://output', 'w');
    
    // Headers schrijven
    fputcsv($output, [
        'Serienummer',
        'Fabrikant',
        'Type Computer',
        'Aantal CPU\'s',
        'CPU Model',
        'CPU Snelheid',
        'Geheugen',
        'Geheugen Type',
        'GPU',
        'Geluidskaart',
        'Storage Controller',
        'Netwerkkaarten',
        'Optical Drive',
        'Moederbord',
        'BIOS Versie',
        'Import Datum'
    ], ';');
    
    // Data schrijven
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['serial_number'],
            $row['manufacturer'],
            $row['computer_type'],
            $row['cpu_count'],
            $row['cpu_model'],
            $row['cpu_speed'],
            $row['memory_total'],
            $row['memory_type'],
            $row['gpu_list'],
            $row['sound_card'],
            $row['storage_controller'],
            $row['network_interfaces'],
            $row['optical_drive'],
            $row['motherboard'],
            $row['bios_version'],
            $row['import_date']
        ], ';');
    }
    
    fclose($output);
    exit;
}
