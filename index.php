<?php
// ---------- index.php ----------
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/import.php';
require_once __DIR__ . '/includes/export.php';
require_once __DIR__ . '/config.php';

$conn = db();

// Upload + import endpoint (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $success = 0;
    $failed = 0;
    $errors = [];

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
        $ok = importXMLToDatabase_relational($dest, $conn);
        if ($ok) {
            $success++;
            @unlink($dest);
        } else {
            $failed++; $errors[] = "$name: Parse/DB-fout";
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
            <p>📁 Sleep XML-bestanden hier naartoe<br><small>of klik om te selecteren (.xml)</small></p>
            <input type="file" id="fileInput" multiple accept=".xml" />
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="?action=export_xlsx" class="btn btn-success">📊 Excel Export (9 Tabs)</a>
            <a href="?action=export_csv" class="btn btn-primary">📄 CSV Export</a>
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