<?php
// ---------- includes/export.php ----------
require_once __DIR__ . '/database.php';

function export_computers_csv(mysqli $conn) {
    // Query met LEFT JOINs om alle hardware te krijgen
    $sql = "
        SELECT 
            c.id,
            c.serial_number,
            c.manufacturer,
            c.computer_model,
            c.computer_type,
            c.form_factor,
            c.cpu_count,
            c.cpu_model,
            c.cpu_speed,
            c.memory_total,
            c.motherboard,
            c.bios_version,
            c.xml_file,
            c.import_date,
            c.updated_date,
            
            -- RAM details
            GROUP_CONCAT(DISTINCT CONCAT_WS('|', 
                COALESCE(r.slot, ''), 
                COALESCE(r.size_gb, ''), 
                COALESCE(r.type, ''), 
                COALESCE(r.speed_mhz, ''), 
                COALESCE(r.manufacturer, ''),
                COALESCE(r.serial_number, ''),
                COALESCE(r.part_number, '')
            ) SEPARATOR ' || ') as ram_details,
            
            -- Storage details
            GROUP_CONCAT(DISTINCT CONCAT_WS('|',
                COALESCE(sd.model, ''),
                COALESCE(sd.vendor, ''),
                COALESCE(sd.size_gb, ''),
                COALESCE(sd.type, ''),
                COALESCE(sd.interface, ''),
                COALESCE(sd.serial, ''),
                IF(sd.is_ssd = 1, 'SSD', 'HDD')
            ) SEPARATOR ' || ') as storage_details,
            
            -- Network interfaces
            GROUP_CONCAT(DISTINCT CONCAT_WS('|',
                COALESCE(ni.description, ''),
                COALESCE(ni.vendor, ''),
                COALESCE(ni.model, ''),
                COALESCE(ni.mac_address, ''),
                COALESCE(ni.speed_mbps, ''),
                COALESCE(ni.interface_type, '')
            ) SEPARATOR ' || ') as network_details,
            
            -- Battery details
            GROUP_CONCAT(DISTINCT CONCAT_WS('|',
                COALESCE(b.type, ''),
                COALESCE(b.manufacturer, ''),
                COALESCE(b.capacity_wh, ''),
                COALESCE(b.serial_number, ''),
                COALESCE(b.status, '')
            ) SEPARATOR ' || ') as battery_details,
            
            -- GPU details
            GROUP_CONCAT(DISTINCT CONCAT_WS('|',
                COALESCE(g.vendor, ''),
                COALESCE(g.model, ''),
                COALESCE(g.pci_id, ''),
                COALESCE(g.memory_gb, '')
            ) SEPARATOR ' || ') as gpu_details
            
        FROM computers c
        LEFT JOIN ram_modules r ON c.id = r.computer_id
        LEFT JOIN storage_devices sd ON c.id = sd.computer_id
        LEFT JOIN network_interfaces ni ON c.id = ni.computer_id
        LEFT JOIN batteries b ON c.id = b.computer_id
        LEFT JOIN gpus g ON c.id = g.computer_id
        GROUP BY c.id
        ORDER BY c.import_date DESC
    ";
    
    $res = $conn->query($sql);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hardware_inventory_volledig_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    
    // Headers met alle velden
    fputcsv($out, [
        'ID',
        'Serienummer',
        'Fabrikant',
        'Computer Model',
        'Computer Type',
        'Form Factor',
        'CPU Aantal',
        'CPU Model',
        'CPU Snelheid',
        'Geheugen Totaal',
        'Moederbord',
        'BIOS Versie',
        'XML Bestandsnaam',
        'Import Datum',
        'Laatste Update',
        'RAM Details (Slot|Grootte|Type|Snelheid|Fabrikant|Serienummer|Partnummer)',
        'Storage Details (Model|Vendor|Grootte|Type|Interface|Serienummer|SSD/HDD)',
        'Netwerk Details (Beschrijving|Vendor|Model|MAC|Snelheid|Type)',
        'Batterij Details (Type|Fabrikant|Capaciteit|Serienummer|Status)',
        'GPU Details (Vendor|Model|PCI ID|Geheugen)'
    ], ';');

    if ($res && $res->num_rows) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['id'],
                $row['serial_number'] ?? '',
                $row['manufacturer'] ?? '',
                $row['computer_model'] ?? '',
                $row['computer_type'] ?? '',
                $row['form_factor'] ?? '',
                $row['cpu_count'] ?? 0,
                $row['cpu_model'] ?? '',
                $row['cpu_speed'] ?? '',
                $row['memory_total'] ?? '',
                $row['motherboard'] ?? '',
                $row['bios_version'] ?? '',
                $row['xml_file'] ?? '',
                $row['import_date'] ?? '',
                $row['updated_date'] ?? '',
                $row['ram_details'] ?? '',
                $row['storage_details'] ?? '',
                $row['network_details'] ?? '',
                $row['battery_details'] ?? '',
                $row['gpu_details'] ?? ''
            ], ';');
        }
    }
    fclose($out);
    exit;
}