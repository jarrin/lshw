<?php
require_once __DIR__ . '/functions.php';

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

if (isset($_GET['action']) && $_GET['action'] === 'export') {
    exportToExcel($conn);
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Inventarisatie System</title>
    <link rel="stylesheet" href="css/styles.css">
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
    
    <script src="js.js"></script>
</body>
</html>