<?php
require_once __DIR__ . '/config.php';

// Database initialisatie
function initDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Database selecteren
    $conn->select_db(DB_NAME);
    
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
        'computer_type' => 'Computer',
        'motherboard' => 'UNKNOWN',
        'bios_version' => 'UNKNOWN',
        'cpus' => [],
        'ram' => [],
        'gpus' => [],
        'nics' => [],
        'sound_cards' => [],
        'storage_controllers' => []
    ];
    
    // Serienummer uit root node ID
    $nodeId = (string)$xml->attributes()->id;
    if ($nodeId) {
        $data['serial_number'] = $nodeId;
    }
    
    // Processor(s)
    $cpus = $xml->xpath('//node[@class="processor"]');
    foreach ($cpus as $cpu) {
        $product = (string)$cpu->product;
        $vendor = (string)$cpu->vendor;
        $version = (string)$cpu->version;
        
        $cpuData = [
            'cpu_model' => 'UNKNOWN',
            'cpu_speed' => 'UNKNOWN',
            'manufacturer' => $vendor ? $vendor : 'UNKNOWN'
        ];
        
        if ($product && $vendor) {
            $cpuData['cpu_model'] = $vendor . ' ' . $product;
        } elseif ($product) {
            $cpuData['cpu_model'] = $product;
        }
        
        if ($version) {
            $cpuData['cpu_speed'] = $version;
        }
        
        $data['cpus'][] = $cpuData;
    }
    
    // Memory - Parse bank nodes voor individuele RAM modules
    $memBanks = $xml->xpath('//node[@id="memory"]//node[@class="memory"]');
    $slot = 1;
    
    if ($memBanks && count($memBanks) > 0) {
        foreach ($memBanks as $bank) {
            $size = (string)$bank->size;
            $description = (string)$bank->description;
            
            if ($size) {
                $sizeBytes = (int)$size;
                $sizeGB = round($sizeBytes / (1024 * 1024 * 1024), 2);
                
                $ramData = [
                    'slot_number' => $slot++,
                    'capacity' => $sizeGB . ' GB',
                    'memory_type' => 'UNKNOWN'
                ];
                
                // Extract memory type from description (DDR3, DDR4, etc)
                if (preg_match('/DDR\d+/i', $description, $matches)) {
                    $ramData['memory_type'] = strtoupper($matches[0]);
                }
                
                $data['ram'][] = $ramData;
            }
        }
    } else {
        // Fallback: gebruik totale system memory als er geen banks gevonden zijn
        $memNode = $xml->xpath('//node[@id="memory"]');
        if ($memNode && isset($memNode[0])) {
            $size = (string)$memNode[0]->size;
            if ($size) {
                $sizeBytes = (int)$size;
                $sizeGB = round($sizeBytes / (1024 * 1024 * 1024), 2);
                $data['ram'][] = [
                    'slot_number' => 1,
                    'capacity' => $sizeGB . ' GB',
                    'memory_type' => 'UNKNOWN'
                ];
            }
        }
    }
    
    // GPU / Display
    $displayNodes = $xml->xpath('//node[@class="display"]');
    foreach ($displayNodes as $gpu) {
        $product = (string)$gpu->product;
        $vendor = (string)$gpu->vendor;
        
        $gpuData = [
            'gpu_name' => 'UNKNOWN',
            'manufacturer' => $vendor ? $vendor : 'UNKNOWN'
        ];
        
        if ($product && $vendor) {
            $gpuData['gpu_name'] = $vendor . ' ' . $product;
        } elseif ($product) {
            $gpuData['gpu_name'] = $product;
        }
        
        $data['gpus'][] = $gpuData;
    }
    
    // Sound / Multimedia
    $soundNodes = $xml->xpath('//node[@class="multimedia"]');
    foreach ($soundNodes as $sound) {
        $product = (string)$sound->product;
        $vendor = (string)$sound->vendor;
        
        $soundData = [
            'sound_card_name' => 'UNKNOWN',
            'manufacturer' => $vendor ? $vendor : 'UNKNOWN'
        ];
        
        if ($product && $vendor) {
            $soundData['sound_card_name'] = $vendor . ' ' . $product;
        } elseif ($product) {
            $soundData['sound_card_name'] = $product;
        }
        
        $data['sound_cards'][] = $soundData;
    }
    
    // Storage controllers
    $storageNodes = $xml->xpath('//node[@class="storage"]');
    foreach ($storageNodes as $stor) {
        $product = (string)$stor->product;
        $vendor = (string)$stor->vendor;
        
        $storData = [
            'controller_name' => 'UNKNOWN',
            'manufacturer' => $vendor ? $vendor : 'UNKNOWN'
        ];
        
        if ($product && $vendor) {
            $storData['controller_name'] = $vendor . ' ' . $product;
        } elseif ($product) {
            $storData['controller_name'] = $product;
        }
        
        $data['storage_controllers'][] = $storData;
    }
    
    // Network interfaces
    $netNodes = $xml->xpath('//node[@class="network"]');
    foreach ($netNodes as $net) {
        $product = (string)$net->product;
        $desc = (string)$net->description;
        $logicalname = (string)$net->logicalname;
        $serial = (string)$net->serial;
        
        $nicData = [
            'interface_name' => $logicalname ? $logicalname : 'UNKNOWN',
            'mac_address' => $serial ? $serial : null,
            'connection_type' => 'Ethernet'
        ];
        
        // Detect WiFi
        if (stripos($desc, 'wireless') !== false || stripos($product, 'wi-fi') !== false) {
            $nicData['connection_type'] = 'WiFi';
        }
        
        $data['nics'][] = $nicData;
    }
    
    // Motherboard
    $core = $xml->xpath('//node[@id="core"]');
    if ($core && isset($core[0])) {
        $desc = (string)$core[0]->description;
        if ($desc) {
            $data['motherboard'] = $desc;
        }
    }
    
    return $data;
}

// Gegevens importeren in database (GENORMALISEERDE VERSIE)
function importXMLToDatabase($filepath, $conn) {
    $data = parseXMLFile($filepath);
    
    if (!$data) {
        error_log("Failed to parse XML: $filepath");
        return false;
    }
    
    // Als serienummer nog steeds UNKNOWN is, genereer er eentje
    if ($data['serial_number'] === 'UNKNOWN') {
        $data['serial_number'] = 'AUTO_' . time() . '_' . bin2hex(random_bytes(4));
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. INSERT/UPDATE computer in computers_base
        $sql = "INSERT INTO computers_base (serial_number, manufacturer, computer_type, motherboard, bios_version, xml_file) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                manufacturer=VALUES(manufacturer), 
                computer_type=VALUES(computer_type),
                motherboard=VALUES(motherboard), 
                bios_version=VALUES(bios_version),
                xml_file=VALUES(xml_file), 
                updated_date=CURRENT_TIMESTAMP";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssss",
            $data['serial_number'],
            $data['manufacturer'],
            $data['computer_type'],
            $data['motherboard'],
            $data['bios_version'],
            $filepath
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Get computer_id
        $computer_id = $conn->insert_id;
        if ($computer_id == 0) {
            // Als het een UPDATE was, haal de ID op
            $result = $conn->query("SELECT id FROM computers_base WHERE serial_number = '" . $conn->real_escape_string($data['serial_number']) . "'");
            if ($result && $row = $result->fetch_assoc()) {
                $computer_id = $row['id'];
            } else {
                throw new Exception("Could not get computer_id");
            }
        }
        
        // Verwijder oude component data voor deze computer (voor re-import)
        $conn->query("DELETE FROM cpu WHERE computer_id = $computer_id");
        $conn->query("DELETE FROM ram WHERE computer_id = $computer_id");
        $conn->query("DELETE FROM gpu WHERE computer_id = $computer_id");
        $conn->query("DELETE FROM nic WHERE computer_id = $computer_id");
        $conn->query("DELETE FROM sound_card WHERE computer_id = $computer_id");
        $conn->query("DELETE FROM storage_controller WHERE computer_id = $computer_id");
        
        // 2. INSERT CPU(s)
        if (count($data['cpus']) > 0) {
            $sql = "INSERT INTO cpu (computer_id, cpu_model, cpu_speed, manufacturer) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($data['cpus'] as $cpu) {
                $stmt->bind_param("isss", $computer_id, $cpu['cpu_model'], $cpu['cpu_speed'], $cpu['manufacturer']);
                $stmt->execute();
            }
        }
        
        // 3. INSERT RAM module(s)
        if (count($data['ram']) > 0) {
            $sql = "INSERT INTO ram (computer_id, slot_number, capacity, memory_type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($data['ram'] as $ram) {
                $stmt->bind_param("iiss", $computer_id, $ram['slot_number'], $ram['capacity'], $ram['memory_type']);
                $stmt->execute();
            }
        }
        
        // 4. INSERT GPU(s)
        if (count($data['gpus']) > 0) {
            $sql = "INSERT INTO gpu (computer_id, gpu_name, manufacturer) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($data['gpus'] as $gpu) {
                $stmt->bind_param("iss", $computer_id, $gpu['gpu_name'], $gpu['manufacturer']);
                $stmt->execute();
            }
        }
        
        // 5. INSERT NIC(s)
        if (count($data['nics']) > 0) {
            $sql = "INSERT INTO nic (computer_id, interface_name, mac_address, connection_type) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($data['nics'] as $nic) {
                $stmt->bind_param("isss", $computer_id, $nic['interface_name'], $nic['mac_address'], $nic['connection_type']);
                $stmt->execute();
            }
        }
        
        // 6. INSERT Sound Card(s)
        if (count($data['sound_cards']) > 0) {
            $sql = "INSERT INTO sound_card (computer_id, sound_card_name, manufacturer) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($data['sound_cards'] as $sound) {
                $stmt->bind_param("iss", $computer_id, $sound['sound_card_name'], $sound['manufacturer']);
                $stmt->execute();
            }
        }
        
        // 7. INSERT Storage Controller(s)
        if (count($data['storage_controllers']) > 0) {
            $sql = "INSERT INTO storage_controller (computer_id, controller_name, manufacturer) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($data['storage_controllers'] as $controller) {
                $stmt->bind_param("iss", $computer_id, $controller['controller_name'], $controller['manufacturer']);
                $stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback bij error
        $conn->rollback();
        error_log("Import error: " . $e->getMessage());
        return false;
    }
}

// Export naar Excel - GEFIXTE VERSIE
function exportToExcel($conn) {
    // Controleer of er data is
    $result = $conn->query("SELECT * FROM computers ORDER BY import_date DESC");
    
    if (!$result || $result->num_rows === 0) {
        // Geen data - redirect terug naar index
        header('Location: index.php');
        exit;
    }
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers instellen voor CSV download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hardware_inventory_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output stream
    $output = fopen('php://output', 'w');
    
    // BOM voor UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers schrijven
    fputcsv($output, [
        'Serienummer',
        'Fabrikant',
        'Type Computer',
        'Aantal CPUs',
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
            $row['serial_number'] ?? '',
            $row['manufacturer'] ?? '',
            $row['computer_type'] ?? '',
            $row['cpu_count'] ?? '0',
            $row['cpu_model'] ?? '',
            $row['cpu_speed'] ?? '',
            $row['memory_total'] ?? '',
            $row['memory_type'] ?? '',
            $row['gpu_list'] ?? '',
            $row['sound_card'] ?? '',
            $row['storage_controller'] ?? '',
            $row['network_interfaces'] ?? '',
            $row['optical_drive'] ?? '',
            $row['motherboard'] ?? '',
            $row['bios_version'] ?? '',
            $row['import_date'] ?? ''
        ], ';');
    }
    
    fclose($output);
    exit;
}