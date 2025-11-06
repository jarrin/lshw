<?php
// ---------- index.php ----------
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/import.php';
require_once __DIR__ . '/includes/export.php';
require_once __DIR__ . '/includes/helpers.php';


$conn = db();

// Helper: recursively remove directory
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object == '.' || $object == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $object;
        if (is_dir($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

// Upload + import endpoint (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $success = 0;
    $failed = 0;
    $errors = [];

    // Verwerk uploads; accepteer meerdere XML-bestanden of één ZIP met meerdere XMLs.
    set_time_limit(0);
    if (defined('IMPORT_MEMORY_LIMIT')) ini_set('memory_limit', IMPORT_MEMORY_LIMIT);

    foreach ($_FILES['xml_file']['tmp_name'] as $idx => $tmp) {
        $name = basename($_FILES['xml_file']['name'][$idx]);
        if (!is_uploaded_file($tmp)) {
            $failed++; $errors[] = "$name: Upload mislukt";
            continue;
        }

        $dest = XML_DIR . 'temp_' . uniqid() . '_' . preg_replace('/[^\w\.-]+/','_', $name);
        if (!move_uploaded_file($tmp, $dest)) {
            $failed++; $errors[] = "$name: Kon niet verplaatsen";
            continue;
        }

        // Als het een ZIP is: pak uit en verwerk alle XML-bestanden binnenin
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowDuplicates = isset($_POST['allow_duplicates']) && $_POST['allow_duplicates'] === '1';
    if ($ext === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($dest) === TRUE) {
                $tmpDir = XML_DIR . 'unz_' . uniqid() . '/';
                if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
                $countInZip = 0;
                $xmlFound = 0;
                $limit = defined('IMPORT_ZIP_LIMIT') ? IMPORT_ZIP_LIMIT : 500;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $filename = $stat['name'];
                    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') continue;
                    $xmlFound++;
                    if ($countInZip >= $limit) {
                        // Reached configured limit
                        break;
                    }
                    // Maak een veilige, unieke bestandsnaam per ZIP-entry.
                    // Gebruik de volledige entry naam (slashes vervangen) en de huidige index
                    $entrySafe = str_replace(["/","\\"], '_', $filename);
                    $entrySafe = preg_replace('/[^\w\.-]+/', '_', $entrySafe);
                    $safe = $countInZip . '_' . $entrySafe;
                    $outPath = $tmpDir . $safe;
                    copy('zip://' . $dest . '#' . $filename, $outPath);
                    $ok = importXMLToDatabase_relational($outPath, $conn, $allowDuplicates);
                    if ($ok) {
                        $success++;
                        @unlink($outPath);
                    } else {
                        $failed++;
                        $errMsg = isset($GLOBALS['last_import_error']) ? $GLOBALS['last_import_error'] : 'Parse/DB-fout';
                        $msg = "$filename: " . $errMsg;
                        $errors[] = $msg;
                        // Append to server-side import log for later inspection
                        @file_put_contents(UPLOAD_DIR . 'import_errors.log', date('c') . " - " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
                        @unlink($outPath);
                    }
                    $countInZip++;
                }
                // cleanup
                $zip->close();
                @unlink($dest);
                rrmdir($tmpDir);
                if ($xmlFound > $countInZip) {
                    $errors[] = "ZIP bevatte $xmlFound XML-bestanden, maar slechts $countInZip verwerkt (limiet: $limit).";
                }
            } else {
                $failed++; $errors[] = "$name: Kon ZIP niet openen";
                @unlink($dest);
            }
            continue;
        }

        // Anders verwerk als enkel XML-bestand
    $ok = importXMLToDatabase_relational($dest, $conn, $allowDuplicates);
        if ($ok) {
            $success++;
            @unlink($dest);
        } else {
            $failed++;
            $errMsg = isset($GLOBALS['last_import_error']) ? $GLOBALS['last_import_error'] : 'Parse/DB-fout';
            $msg = "$name: " . $errMsg;
            $errors[] = $msg;
            @file_put_contents(UPLOAD_DIR . 'import_errors.log', date('c') . " - " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
            @unlink($dest);
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $failed === 0,
        'success_count' => $success,
        'error_count' => $failed,
        'errors' => $errors,
        'message' => "$success bestanden succesvol geïmporteerd"
    ]);
    exit;
}

// Export endpoints
if (isset($_GET['action']) && $_GET['action'] === 'export_xlsx') {
    export_computers_xlsx_native($conn);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    export_computers_csv($conn);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8" />
    <title>Hardware Inventarisatie System (Relationeel)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
<div class="container">
    <header>
        <h1>🖥️ Hardware Inventarisatie System</h1>
        <p>Windau Diensten - Relationele XML Import & Database Beheer</p>
    </header>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number">
                <?php
                $r = $conn->query("SELECT COUNT(*) c FROM computers");
                echo (int)($r->fetch_assoc()['c'] ?? 0);
                ?>
            </div>
            <div class="stat-label">Computers</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">
                <?php
                $r = $conn->query("SELECT COUNT(*) c FROM storage_devices");
                echo (int)($r->fetch_assoc()['c'] ?? 0);
                ?>
            </div>
            <div class="stat-label">Opslagapparaten</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">
                <?php
                $r = $conn->query("SELECT COUNT(*) c FROM network_interfaces");
                echo (int)($r->fetch_assoc()['c'] ?? 0);
                ?>
            </div>
            <div class="stat-label">Netwerkkaarten</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">
                <?php
                $r = $conn->query("SELECT COUNT(*) c FROM ram_modules");
                echo (int)($r->fetch_assoc()['c'] ?? 0);
                ?>
            </div>
            <div class="stat-label">RAM-modules</div>
        </div>
    </div>

    <div class="controls">
        <div class="upload-area" id="uploadArea">
            <p>📁 Sleep XML-bestanden of een ZIP met XML's hier naartoe<br><small>of klik om te selecteren (.xml, .zip)</small></p>
            <input type="file" id="fileInput" multiple accept=".xml,.zip" />
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="?action=export_xlsx" class="btn btn-success">📊 Excel Export (9 Tabs)</a>
            <a href="?action=export_csv" class="btn btn-primary">📄 CSV Export (single)</a>
        </div>
    </div>

    <div id="alertBox"></div>

    <div class="table-wrapper">
        <h2>📋 Laatste geimporteerde computers</h2>
        <table>
            <thead>
            <tr>
                <th>Serienr</th>
                <th>Fabrikant</th>
                <th>Model</th>
                <th>Type</th>
                <th>CPU</th>
                <th>RAM Tot.</th>
                <th>Import datum</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $res = $conn->query("SELECT * FROM computers ORDER BY import_date DESC LIMIT 100");
            if (!$res || $res->num_rows === 0) {
                echo '<tr><td colspan="7" class="no-data">Nog geen data. Upload XML-bestanden om te beginnen.</td></tr>';
            } else {
                while ($row = $res->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($row['serial_number']) . '</code></td>';
                    echo '<td>' . htmlspecialchars($row['manufacturer'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['computer_model'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['computer_type'] ?? '') . '</td>';
                    echo '<td>' . (int)$row['cpu_count'] . 'x ' . htmlspecialchars($row['cpu_model'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($row['memory_total'] ?? '') . '</td>';
                    echo '<td>' . date('d-m-Y H:i', strtotime($row['import_date'])) . '</td>';
                    echo '</tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
<script src="js/js.js"></script>
</body>
</html>