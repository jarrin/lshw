<?php
// config.php - Database configuratie
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hardware_inventory');
define('XML_DIR', './xml_files/');
define('UPLOAD_DIR', './uploads/');

// Zorg ervoor dat directories bestaan
if (!is_dir(XML_DIR)) mkdir(XML_DIR, 0777, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

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

// Initialization
$conn = initDatabase();

// Verwerk requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['xml_file'])) {
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        // Zet error reporting naar slient
        error_reporting(0);
        
        foreach ($_FILES['xml_file']['tmp_name'] as $idx => $file) {
            $name = basename($_FILES['xml_file']['name'][$idx]);
            $tmpDest = XML_DIR . 'temp_' . uniqid() . '_' . $name;
            
            if (move_uploaded_file($file, $tmpDest)) {
                if (importXMLToDatabase($tmpDest, $conn)) {
                    $success_count++;
                    @unlink($tmpDest);
                } else {
                    $error_count++;
                    $errors[] = $name . ': Database error';
                    @unlink($tmpDest);
                }
            } else {
                $error_count++;
                $errors[] = $name . ': Upload failed';
            }
        }
        
        error_reporting(E_ALL);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $error_count === 0,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors,
            'message' => $success_count . ' bestanden succesvol geïmporteerd'
        ]);
        exit;
    }
}

// Verwerk export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    exportToExcel($conn);
}

// Web interface
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Inventarisatie System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        header { 
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white; 
            padding: 30px 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        header h1 { font-size: 32px; margin-bottom: 5px; }
        header p { opacity: 0.9; font-size: 14px; }
        
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 30px; 
        }
        .stat-box { 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        .stat-number { font-size: 32px; font-weight: bold; color: #3498db; }
        .stat-label { color: #7f8c8d; font-size: 14px; margin-top: 8px; }
        
        .controls { 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-start;
        }
        
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: 600;
            transition: all 0.3s; 
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3); }
        
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3); }
        
        .upload-area { 
            flex: 1;
            min-width: 300px;
            border: 2px dashed #3498db; 
            border-radius: 8px; 
            padding: 40px; 
            text-align: center; 
            cursor: pointer; 
            transition: all 0.3s;
            background: #fafafa;
        }
        .upload-area:hover { background: #f0f8ff; border-color: #2980b9; }
        .upload-area.dragover { background: #d5e8f7; border-color: #2980b9; }
        .upload-area p { margin: 0; color: #555; }
        
        .table-wrapper { 
            background: white; 
            border-radius: 8px; 
            padding: 25px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .table-wrapper h2 { margin-bottom: 20px; color: #2c3e50; }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th { 
            background: #34495e; 
            color: white; 
            padding: 15px; 
            text-align: left; 
            font-weight: 600;
            border-bottom: 2px solid #2c3e50;
            position: sticky;
            top: 0;
        }
        td { 
            padding: 12px 15px; 
            border-bottom: 1px solid #ecf0f1; 
            font-size: 13px;
        }
        tr:hover { background: #f8f9fa; }
        
        code { 
            background: #f4f4f4; 
            padding: 3px 6px; 
            border-radius: 3px; 
            font-family: 'Courier New', monospace;
            color: #d63031;
        }
        
        .no-data { 
            text-align: center; 
            padding: 40px; 
            color: #7f8c8d; 
        }
        
        #fileInput { display: none; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #ecf0f1;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #27ae60;
            width: 0%;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🖥️ Hardware Inventarisatie System</h1>
            <p>Windau Diensten - Professionele XML Import & Database Beheer</p>
        </header>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php 
                    $result = $conn->query("SELECT COUNT(*) as count FROM computers");
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                ?></div>
                <div class="stat-label">Computers in database</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php 
                    $result = $conn->query("SELECT COUNT(DISTINCT manufacturer) as count FROM computers WHERE manufacturer != 'UNKNOWN'");
                    $row = $result->fetch_assoc();
                    echo $row['count'];
                ?></div>
                <div class="stat-label">Verschillende merken</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php 
                    $result = $conn->query("SELECT AVG(CAST(REPLACE(REPLACE(memory_total, ' GB', ''), '0 GB', '0') AS DECIMAL)) as avg_mem FROM computers");
                    $row = $result->fetch_assoc();
                    echo round($row['avg_mem'], 1) . ' GB';
                ?></div>
                <div class="stat-label">Gemiddeld geheugen</div>
            </div>
        </div>
        
        <div class="controls">
            <div class="upload-area" id="uploadArea">
                <p>📁 Sleep XML-bestanden hier naartoe<br><small>of klik om te selecteren (.xml, .txt, etc)</small></p>
                <input type="file" id="fileInput" multiple>
            </div>
            <a href="?action=export" class="btn btn-success">📊 Exporteren naar Excel</a>
        </div>
        
        <div id="alertBox"></div>
        
        <div class="table-wrapper">
            <h2>📋 Geïmporteerde Computers</h2>
            <table>
                <thead>
                    <tr>
                        <th>Serienummer</th>
                        <th>Merk</th>
                        <th>Type</th>
                        <th>CPU</th>
                        <th>RAM</th>
                        <th>GPU</th>
                        <th>Netwerk</th>
                        <th>Import Datum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $result = $conn->query("SELECT * FROM computers ORDER BY import_date DESC LIMIT 100");
                        if ($result->num_rows === 0) {
                            echo '<tr><td colspan="8" class="no-data">Nog geen computers in de database. Upload XML-bestanden om te beginnen.</td></tr>';
                        } else {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td><code>" . htmlspecialchars(substr($row['serial_number'], 0, 20)) . "</code></td>";
                                echo "<td>" . htmlspecialchars($row['manufacturer']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['computer_type']) . "</td>";
                                echo "<td>" . $row['cpu_count'] . "x " . htmlspecialchars(substr($row['cpu_model'], 0, 20)) . "</td>";
                                echo "<td>" . htmlspecialchars($row['memory_total']) . "</td>";
                                echo "<td>" . htmlspecialchars(substr($row['gpu_list'], 0, 20)) . "</td>";
                                echo "<td>" . htmlspecialchars(substr($row['network_interfaces'], 0, 20)) . "</td>";
                                echo "<td>" . date('d-m-Y H:i', strtotime($row['import_date'])) . "</td>";
                                echo "</tr>";
                            }
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const alertBox = document.getElementById('alertBox');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
        
        function handleFiles(files) {
            const validFiles = Array.from(files).filter(f => {
                // Accepteer alle bestanden, zal in PHP gecheckt worden op XML inhoud
                return true;
            });
            
            if (validFiles.length === 0) {
                showAlert('Geen bestanden gevonden', 'error');
                return;
            }
            
            uploadFiles(validFiles);
        }
        
        function uploadFiles(files) {
            const formData = new FormData();
            
            files.forEach(file => {
                formData.append('xml_file[]', file);
            });
            
            showAlert('⏳ Bestanden worden geüpload...', 'info');
            
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showAlert('✓ ' + data.success_count + ' bestanden succesvol geïmporteerd', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        let msg = '✗ Fout bij importeren\n';
                        if (data.errors) {
                            msg += data.errors.join('\n');
                        }
                        showAlert(msg, 'error');
                    }
                })
                .catch(e => {
                    showAlert('✗ Verbindingsfout: ' + e.message, 'error');
                });
        }
        
        function showAlert(message, type) {
            const className = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-error' : 'alert-info';
            alertBox.innerHTML = `<div class="alert ${className}">${message}</div>`;
        }
    </script>
</body>
</html>