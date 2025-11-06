<?php
// ---------- includes/export.php ----------
require_once __DIR__ . '/database.php';

// ==================== PURE PHP XLSX EXPORT (GEEN LIBRARIES NODIG) ====================
function export_computers_xlsx_native(mysqli $conn) {
    $filename = 'hardware_inventory_volledig_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Maak een zip file (xlsx is gewoon een zip met XML bestanden)
    $zip = new ZipArchive();
    $temp_file = tempnam(sys_get_temp_dir(), 'xlsx');
    
    if ($zip->open($temp_file, ZipArchive::CREATE) !== TRUE) {
        die("Cannot create XLSX file");
    }
    
    // Basis XML structuur voor Excel
    $zip->addFromString('[Content_Types].xml', get_content_types());
    $zip->addFromString('_rels/.rels', get_rels());
    $zip->addFromString('xl/_rels/workbook.xml.rels', get_workbook_rels());
    $zip->addFromString('xl/workbook.xml', get_workbook());
    $zip->addFromString('xl/styles.xml', get_styles());
    $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"/>');
    
    // Sheet 1: Computers
    $zip->addFromString('xl/worksheets/sheet1.xml', create_computers_sheet($conn));
    
    // Sheet 2: RAM
    $zip->addFromString('xl/worksheets/sheet2.xml', create_ram_sheet($conn));
    
    // Sheet 3: Storage
    $zip->addFromString('xl/worksheets/sheet3.xml', create_storage_sheet($conn));
    
    // Sheet 4: Network
    $zip->addFromString('xl/worksheets/sheet4.xml', create_network_sheet($conn));
    
    // Sheet 5: Batteries
    $zip->addFromString('xl/worksheets/sheet5.xml', create_battery_sheet($conn));
    
    // Sheet 6: GPUs
    $zip->addFromString('xl/worksheets/sheet6.xml', create_gpu_sheet($conn));
    
    // Sheet 7: Sound Cards
    $zip->addFromString('xl/worksheets/sheet7.xml', create_sound_sheet($conn));
    
    // Sheet 8: Storage Controllers
    $zip->addFromString('xl/worksheets/sheet8.xml', create_controller_sheet($conn));
    
    // Sheet 9: Optical Drives
    $zip->addFromString('xl/worksheets/sheet9.xml', create_drives_sheet($conn));
    
    $zip->close();
    
    readfile($temp_file);
    unlink($temp_file);
    exit;
}

// Helper functie om XML te escapen
function xml_escape($str) {
    return htmlspecialchars($str ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// Sheet creator functies
function create_computers_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    // Headers
    $xml .= '<row r="1">';
    $headers = ['ID', 'Serienummer', 'Fabrikant', 'Computer Model', 'Computer Type', 'Form Factor', 
                'CPU Aantal', 'CPU Model', 'CPU Snelheid', 'Geheugen Totaal', 'Moederbord', 
                'BIOS Versie', 'XML Bestandsnaam', 'Import Datum', 'Laatste Update'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i); // A, B, C, etc.
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    // Data
    $res = $conn->query("SELECT * FROM computers ORDER BY import_date DESC");
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['id'],
            $row['serial_number'],
            $row['manufacturer'],
            $row['computer_model'],
            $row['computer_type'],
            $row['form_factor'],
            $row['cpu_count'],
            $row['cpu_model'],
            $row['cpu_speed'],
            $row['memory_total'],
            $row['motherboard'],
            $row['bios_version'],
            $row['xml_file'],
            $row['import_date'],
            $row['updated_date']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            if ($i === 0 || $i === 6) { // ID en CPU count zijn nummers
                $xml .= '<c r="' . $col . $rowNum . '"><v>' . xml_escape($val) . '</v></c>';
            } else {
                $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_ram_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Slot', 'Grootte (GB)', 
                'Type', 'Snelheid (MHz)', 'Fabrikant', 'Form Factor', 'Serienummer', 'Partnummer'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT r.*, c.serial_number as comp_serial, c.computer_model 
            FROM ram_modules r 
            JOIN computers c ON r.computer_id = c.id 
            ORDER BY c.serial_number, r.slot";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['slot'],
            $row['size_gb'],
            $row['type'],
            $row['speed_mhz'],
            $row['manufacturer'],
            $row['form_factor'],
            $row['serial_number'],
            $row['part_number']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            if ($i === 0 || $i === 6) { // IDs en speed zijn nummers
                $xml .= '<c r="' . $col . $rowNum . '"><v>' . xml_escape($val) . '</v></c>';
            } else {
                $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_storage_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    // Kolommen zoals in het Excel-sjabloon (volgorde belangrijk)
    $headers = ['Computer', 'Serienummer PC', 'Computer Model', 'Disk Model', 'Vendor', 
                'Grootte (GB)', 'Type', 'Interface', 'Serienummer Disk', 'SSD/HDD'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT sd.*, c.serial_number as comp_serial, c.computer_model 
            FROM storage_devices sd 
            JOIN computers c ON sd.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['model'],
            $row['vendor'],
            $row['size_gb'],
            $row['type'],
            $row['interface'],
            $row['serial'],
            $row['is_ssd'] ? 'Ja' : 'Nee'
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_network_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Beschrijving', 'Vendor', 
                'Model', 'MAC Adres', 'Snelheid (Mbps)', 'Type'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT ni.*, c.serial_number as comp_serial, c.computer_model 
            FROM network_interfaces ni 
            JOIN computers c ON ni.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['description'],
            $row['vendor'],
            $row['model'],
            $row['mac_address'],
            $row['speed_mbps'],
            $row['interface_type']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_battery_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Type', 'Fabrikant', 
                'Capaciteit (Wh)', 'Serienummer', 'Status'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT b.*, c.serial_number as comp_serial, c.computer_model 
            FROM batteries b 
            JOIN computers c ON b.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['type'],
            $row['manufacturer'],
            $row['capacity_wh'],
            $row['serial_number'],
            $row['status']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_gpu_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Vendor', 'Model', 'PCI ID', 'Geheugen (GB)'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT g.*, c.serial_number as comp_serial, c.computer_model 
            FROM gpus g 
            JOIN computers c ON g.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['vendor'],
            $row['model'],
            $row['pci_id'],
            $row['memory_gb']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_sound_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Vendor', 'Model', 'Beschrijving'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT sc.*, c.serial_number as comp_serial, c.computer_model 
            FROM sound_cards sc 
            JOIN computers c ON sc.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['vendor'],
            $row['model'],
            $row['description']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_controller_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Vendor', 'Model', 'Type'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT stc.*, c.serial_number as comp_serial, c.computer_model 
            FROM storage_controllers stc 
            JOIN computers c ON stc.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['vendor'],
            $row['model'],
            $row['type']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function create_drives_sheet($conn) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';
    
    $xml .= '<row r="1">';
    $headers = ['Computer ID', 'Serienummer', 'Computer Model', 'Type', 'Vendor', 'Model'];
    foreach ($headers as $i => $h) {
        $col = chr(65 + $i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $xml .= '</row>';
    
    $sql = "SELECT d.*, c.serial_number as comp_serial, c.computer_model 
            FROM drives d 
            JOIN computers c ON d.computer_id = c.id 
            ORDER BY c.serial_number";
    $res = $conn->query($sql);
    $rowNum = 2;
    while ($row = $res->fetch_assoc()) {
        $xml .= '<row r="' . $rowNum . '">';
        $data = [
            $row['computer_id'],
            $row['comp_serial'],
            $row['computer_model'],
            $row['type'],
            $row['vendor'],
            $row['model']
        ];
        foreach ($data as $i => $val) {
            $col = chr(65 + $i);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' . xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;
    }
    
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

// XML templates voor Excel structuur
function get_content_types() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet5.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet6.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet7.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet8.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet9.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';
}

function get_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
}

function get_workbook_rels() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/>
<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet5.xml"/>
<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet6.xml"/>
<Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet7.xml"/>
<Relationship Id="rId8" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet8.xml"/>
<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet9.xml"/>
<Relationship Id="rId10" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rId11" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';
}

function get_workbook() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="Computers" sheetId="1" r:id="rId1"/>
<sheet name="RAM Modules" sheetId="2" r:id="rId2"/>
<sheet name="Storage Devices" sheetId="3" r:id="rId3"/>
<sheet name="Network Interfaces" sheetId="4" r:id="rId4"/>
<sheet name="Batteries" sheetId="5" r:id="rId5"/>
<sheet name="GPUs" sheetId="6" r:id="rId6"/>
<sheet name="Sound Cards" sheetId="7" r:id="rId7"/>
<sheet name="Storage Controllers" sheetId="8" r:id="rId8"/>
<sheet name="Optical Drives" sheetId="9" r:id="rId9"/>
</sheets>
</workbook>';
}

function get_styles() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
</fonts>
<fills count="2">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
</fills>
<borders count="1">
<border><left/><right/><top/><bottom/><diagonal/></border>
</borders>
<cellXfs count="2">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/>
</cellXfs>
</styleSheet>';
}

// ==================== CSV EXPORT (FALLBACK) ====================
function export_computers_csv(mysqli $conn) {
    $res = $conn->query("SELECT * FROM computers ORDER BY import_date DESC");
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hardware_inventory_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    
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
        'Laatste Update'
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
                $row['updated_date'] ?? ''
            ], ';');
        }
    }
    fclose($out);
    exit;
}