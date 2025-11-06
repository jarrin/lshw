<?php
// ---------- includes/import.php ----------
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/parser.php';

function upsert_computer(mysqli $conn, array $c, string $filepath)
{
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    $sql = "INSERT INTO computers
            (serial_number, manufacturer, computer_model, computer_type, cpu_count, cpu_model, cpu_speed, memory_total, motherboard, bios_version, xml_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              manufacturer=VALUES(manufacturer),
              computer_model=VALUES(computer_model),
              computer_type=VALUES(computer_type),
              cpu_count=VALUES(cpu_count),
              cpu_model=VALUES(cpu_model),
              cpu_speed=VALUES(cpu_speed),
              memory_total=VALUES(memory_total),
              motherboard=VALUES(motherboard),
              bios_version=VALUES(bios_version),
              xml_file=VALUES(xml_file),
              updated_date=CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $stmt->bind_param(
        "ssssissssss",
        $c['serial_number'],
        $c['manufacturer'],
        $c['computer_model'],
        $c['computer_type'],
        $c['cpu_count'],
        $c['cpu_model'],
        $c['cpu_speed'],
        $c['memory_total'],
        $c['motherboard'],
        $c['bios_version'],
        $filepath
    );
    if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

    // Haal ID op
    $id = null;
    $q = $conn->prepare("SELECT id FROM computers WHERE serial_number = ?");
    $q->bind_param("s", $c['serial_number']);
    $q->execute();
    $q->bind_result($id);
    $q->fetch();
    $q->close();
    if (!$id) throw new Exception('Kon computer_id niet ophalen');
    return (int)$id;
}

function replace_children(mysqli $conn, int $computer_id, array $data)
{
    // Eerst alle kindtabellen leeg voor deze computer, daarna opnieuw invoegen
    $tables = [
        'ram_modules',
        'storage_devices',
        'network_interfaces',
        'gpus',
        'drives',
        'batteries',
        'sound_cards',
        'storage_controllers'
    ];
    foreach ($tables as $t) {
        $del = $conn->prepare("DELETE FROM {$t} WHERE computer_id = ?");
        $del->bind_param("i", $computer_id);
        $del->execute();
        $del->close();
    }

    // RAM
    if (!empty($data['ram_modules'])) {
        $stmt = $conn->prepare("INSERT INTO ram_modules
            (computer_id, size_gb, type, speed_mhz, manufacturer, form_factor, serial_number, part_number, slot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['ram_modules'] as $r) {
            $size = $r['size_gb'];
            $type = $r['type'];
            $speed = $r['speed_mhz'];
            $mf = $r['manufacturer'];
            $ff = $r['form_factor'];
            $sn = $r['serial_number'];
            $pn = $r['part_number'];
            $slot = $r['slot'];
            $stmt->bind_param("idsssssss", $computer_id, $size, $type, $speed, $mf, $ff, $sn, $pn, $slot);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Storage devices
    if (!empty($data['storage_devices'])) {
        $stmt = $conn->prepare("INSERT INTO storage_devices
            (computer_id, model, vendor, size_gb, type, interface, serial, is_ssd, media_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['storage_devices'] as $d) {
            $stmt->bind_param(
                "issdsssis",
                $computer_id,
                $model,
                $vendor,
                $size_gb,
                $type,
                $interface,
                $serial,
                $is_ssd,
                $media_type
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    // NICs
    if (!empty($data['network_interfaces'])) {
        $stmt = $conn->prepare("INSERT INTO network_interfaces
            (computer_id, name, description, mac_address, speed_mbps, vendor, model, interface_type, pci_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['network_interfaces'] as $n) {
            $stmt->bind_param(
                "isssissss",
                $computer_id,
                $n['name'],
                $n['description'],
                $n['mac_address'],
                $n['speed_mbps'],
                $n['vendor'],
                $n['model'],
                $n['interface_type'],
                $n['pci_id']
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    // GPUs
    if (!empty($data['gpus'])) {
        $stmt = $conn->prepare("INSERT INTO gpus
            (computer_id, vendor, model, pci_id, memory_gb)
            VALUES (?, ?, ?, ?, ?)");
        foreach ($data['gpus'] as $g) {
            $stmt->bind_param("isssd", $computer_id, $g['vendor'], $g['model'], $g['pci_id'], $g['memory_gb']);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Optical drives
    if (!empty($data['drives'])) {
        $stmt = $conn->prepare("INSERT INTO drives
            (computer_id, type, vendor, model, description, capabilities, interface)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['drives'] as $dr) {
            // Normalize fields and avoid undefined index notices
            $type = $dr['type'] ?? null;
            $vendor = $dr['vendor'] ?? null;
            $model = $dr['model'] ?? null;
            $description = $dr['description'] ?? null;
            $capabilities = $dr['capabilities'] ?? null;
            $interface = $dr['interface'] ?? null;

            $ok = $stmt->bind_param(
                "issssss",
                $computer_id,
                $type,
                $vendor,
                $model,
                $description,
                $capabilities,
                $interface
            );
            if ($ok === false) {
                $GLOBALS['last_import_error'] = 'bind_param failed for drives: ' . $stmt->error;
                continue;
            }
            if (!$stmt->execute()) {
                $GLOBALS['last_import_error'] = 'Execute failed (drives): ' . $stmt->error;
                continue;
            }
        }
        $stmt->close();
    }

    // Batteries
    if (!empty($data['batteries'])) {
        $stmt = $conn->prepare("INSERT INTO batteries
        (computer_id, type, capacity_wh, manufacturer, product, serial_number, voltage_now_mv, voltage_min_design_mv, voltage_max_design_mv, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['batteries'] as $b) {
            $type = $b['type'] ?? null;
            $cap = $b['capacity_wh'] ?? null;
            $man = $b['manufacturer'] ?? null;
            $prod = $b['product'] ?? null;
            $sn = $b['serial_number'] ?? null;
            $vn = $b['voltage_now_mv'] ?? null;
            $vmin = $b['voltage_min_design_mv'] ?? null;
            $vmax = $b['voltage_max_design_mv'] ?? null;
            $status = $b['status'] ?? null;

            $stmt->bind_param(
                "isdssssiis",
                $computer_id,
                $type,
                $cap,
                $man,
                $prod,
                $sn,
                $vn,
                $vmin,
                $vmax,
                $status
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    // Sound cards
    if (!empty($data['sound_cards'])) {
        $stmt = $conn->prepare("INSERT INTO sound_cards
            (computer_id, vendor, model, description, pci_id)
            VALUES (?, ?, ?, ?, ?)");
        foreach ($data['sound_cards'] as $s) {
            $stmt->bind_param("issss", $computer_id, $s['vendor'], $s['model'], $s['description'], $s['pci_id']);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Storage controllers
    if (!empty($data['storage_controllers'])) {
        $stmt = $conn->prepare("INSERT INTO storage_controllers
            (computer_id, vendor, model, description, type, pci_id)
            VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['storage_controllers'] as $sc) {
            $stmt->bind_param("isssss", $computer_id, $sc['vendor'], $sc['model'], $sc['description'], $sc['type'], $sc['pci_id']);
            $stmt->execute();
        }
        $stmt->close();
    }
}

function importXMLToDatabase_relational($filepath, mysqli $conn, bool $allowDuplicates = false)
{
    $parsed = parse_wipedrive_xml($filepath);
    if ($parsed === false) {
        $GLOBALS['last_import_error'] = $GLOBALS['last_parse_error'] ?? 'Parse failed or invalid XML';
        return false;
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn->begin_transaction();
    try {
        $computer_id = upsert_computer($conn, $parsed['computer'], $filepath);
        replace_children($conn, $computer_id, $parsed);
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        // Sla foutmelding op zodat caller deze kan loggen en verder kunnen gaan met batch
        $GLOBALS['last_import_error'] = 'Importfout: ' . $e->getMessage();
        return false;
    }
}
