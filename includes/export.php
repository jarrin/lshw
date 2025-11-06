<?php
// ---------- includes/export.php ----------

/**
 * Export computers + alle child componenten naar Excel (native PHP, geen dependencies)
 * Elke component-type krijgt zijn eigen tabblad
 */
function export_computers_xlsx_native(mysqli $conn)
{
    // Clear any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Check if we have data
    $check = $conn->query("SELECT COUNT(*) as cnt FROM computers");
    $row = $check->fetch_assoc();
    if ($row['cnt'] == 0) {
        header('Location: index.php?error=no_data');
        exit;
    }

    // Set headers for Excel download
    $filename = 'hardware_inventory_' . date('Y-m-d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create XLSX file structure in memory
    $xlsx = create_xlsx_with_tabs($conn);
    echo $xlsx;
    exit;
}

/**
 * Native PHP XLSX creator - creates multi-tab workbook
 */
function create_xlsx_with_tabs(mysqli $conn)
{
    // Define sheets with their queries and headers
    $sheets = [
        'Computers' => [
            'query' => "SELECT 
                id,
                serial_number,
                manufacturer,
                computer_model,
                computer_type,
                cpu_count,
                cpu_model,
                cpu_speed,
                memory_total,
                motherboard,
                bios_version,
                import_date,
                updated_date
                FROM computers 
                ORDER BY import_date DESC",
            'headers' => ['ID', 'Serienummer', 'Fabrikant', 'Model', 'Type', 'CPU Aantal', 'CPU Model', 'CPU Snelheid', 'Totaal RAM', 'Moederbord', 'BIOS Versie', 'Import Datum', 'Update Datum']
        ],
        'RAM Modules' => [
            'query' => "SELECT 
                r.id,
                r.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                r.slot,
                r.size_gb,
                r.type,
                r.speed_mhz,
                r.manufacturer,
                r.form_factor,
                r.serial_number,
                r.part_number
                FROM ram_modules r
                LEFT JOIN computers c ON r.computer_id = c.id
                ORDER BY c.serial_number, r.slot",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Slot', 'Grootte (GB)', 'Type', 'Snelheid (MHz)', 'RAM Fabrikant', 'Form Factor', 'Serienummer', 'Part Number']
        ],
        'Storage Devices' => [
            'query' => "SELECT 
                s.id,
                s.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                s.model,
                s.vendor,
                s.size_gb,
                s.type,
                s.interface,
                s.serial,
                CASE WHEN s.is_ssd = 1 THEN 'Ja' WHEN s.is_ssd = 0 THEN 'Nee' ELSE '' END as is_ssd,
                s.media_type
                FROM storage_devices s
                LEFT JOIN computers c ON s.computer_id = c.id
                ORDER BY c.serial_number, s.size_gb DESC",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Model', 'Vendor', 'Grootte (GB)', 'Type', 'Interface', 'Serial', 'Is SSD', 'Media Type']
        ],
        'Network Interfaces' => [
            'query' => "SELECT 
                n.id,
                n.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                n.name,
                n.description,
                n.mac_address,
                n.speed_mbps,
                n.vendor,
                n.model,
                n.interface_type,
                n.pci_id
                FROM network_interfaces n
                LEFT JOIN computers c ON n.computer_id = c.id
                ORDER BY c.serial_number, n.name",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Naam', 'Beschrijving', 'MAC Adres', 'Snelheid (Mbps)', 'Vendor', 'Model', 'Interface Type', 'PCI ID']
        ],
        'GPUs' => [
            'query' => "SELECT 
                g.id,
                g.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                g.vendor,
                g.model,
                g.pci_id,
                g.memory_gb
                FROM gpus g
                LEFT JOIN computers c ON g.computer_id = c.id
                ORDER BY c.serial_number",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Vendor', 'Model', 'PCI ID', 'Geheugen (GB)']
        ],
        'Optical Drives' => [
            'query' => "SELECT 
                d.id,
                d.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                d.type,
                d.vendor,
                d.model,
                d.description,
                d.capabilities,
                d.interface
                FROM drives d
                LEFT JOIN computers c ON d.computer_id = c.id
                ORDER BY c.serial_number",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Type', 'Vendor', 'Model', 'Beschrijving', 'Mogelijkheden', 'Interface']
        ],
        'Batteries' => [
            'query' => "SELECT 
                b.id,
                b.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                b.type,
                b.capacity_wh,
                b.manufacturer,
                b.product,
                b.serial_number,
                b.voltage_now_mv,
                b.voltage_min_design_mv,
                b.voltage_max_design_mv,
                b.status
                FROM batteries b
                LEFT JOIN computers c ON b.computer_id = c.id
                ORDER BY c.serial_number",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Type', 'Capaciteit (Wh)', 'Fabrikant', 'Product', 'Serienummer', 'Voltage Nu (mV)', 'Voltage Min (mV)', 'Voltage Max (mV)', 'Status']
        ],
        'Sound Cards' => [
            'query' => "SELECT 
                s.id,
                s.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                s.vendor,
                s.model,
                s.description,
                s.pci_id
                FROM sound_cards s
                LEFT JOIN computers c ON s.computer_id = c.id
                ORDER BY c.serial_number",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Vendor', 'Model', 'Beschrijving', 'PCI ID']
        ],
        'Storage Controllers' => [
            'query' => "SELECT 
                sc.id,
                sc.computer_id,
                c.serial_number,
                c.manufacturer as computer_manufacturer,
                sc.vendor,
                sc.model,
                sc.description,
                sc.type,
                sc.pci_id
                FROM storage_controllers sc
                LEFT JOIN computers c ON sc.computer_id = c.id
                ORDER BY c.serial_number",
            'headers' => ['ID', 'Computer ID', 'Computer Serial', 'Computer Fabrikant', 'Vendor', 'Model', 'Beschrijving', 'Type', 'PCI ID']
        ]
    ];

    // Build sheet data
    $sheetData = [];
    $sheetIndex = 1;
    foreach ($sheets as $sheetName => $config) {
        $result = $conn->query($config['query']);
        if (!$result) {
            continue; // Skip on error
        }

        $rows = [];
        $rows[] = $config['headers']; // Add headers

        while ($row = $result->fetch_assoc()) {
            $rows[] = array_values($row);
        }

        $sheetData[] = [
            'name' => $sheetName,
            'rows' => $rows,
            'index' => $sheetIndex++
        ];
    }

    return build_xlsx_file($sheetData);
}

/**
 * Build actual XLSX file (ZIP with XML structure)
 */
function build_xlsx_file(array $sheetData)
{
    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die('Cannot create XLSX file');
    }

    // [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
    
    foreach ($sheetData as $sheet) {
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $sheet['index'] . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    
    $contentTypes .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
    $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $contentTypes .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
    $contentTypes .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
    $contentTypes .= '</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
    $rels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
    $rels .= '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>';
    $rels .= '</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    // xl/_rels/workbook.xml.rels
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    
    foreach ($sheetData as $sheet) {
        $wbRels .= '<Relationship Id="rId' . $sheet['index'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheet['index'] . '.xml"/>';
    }
    
    $nextRid = count($sheetData) + 1;
    $wbRels .= '<Relationship Id="rId' . $nextRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
    $wbRels .= '<Relationship Id="rId' . ($nextRid + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $wbRels .= '</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

    // Build shared strings
    $sharedStrings = [];
    $sharedStringsMap = [];
    
    foreach ($sheetData as &$sheet) {
        foreach ($sheet['rows'] as &$row) {
            foreach ($row as &$cell) {
                if (!is_numeric($cell) && $cell !== null && $cell !== '') {
                    $cellStr = (string)$cell;
                    if (!isset($sharedStringsMap[$cellStr])) {
                        $sharedStringsMap[$cellStr] = count($sharedStrings);
                        $sharedStrings[] = $cellStr;
                    }
                }
            }
        }
    }

    // xl/sharedStrings.xml
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
    foreach ($sharedStrings as $str) {
        $ssXml .= '<si><t>' . xmlspecialchars($str) . '</t></si>';
    }
    $ssXml .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);

    // xl/styles.xml (basic styling with bold headers)
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $styles .= '<fonts count="2">';
    $styles .= '<font><sz val="11"/><name val="Calibri"/></font>';
    $styles .= '<font><b/><sz val="11"/><name val="Calibri"/></font>';
    $styles .= '</fonts>';
    $styles .= '<fills count="2">';
    $styles .= '<fill><patternFill patternType="none"/></fill>';
    $styles .= '<fill><patternFill patternType="gray125"/></fill>';
    $styles .= '</fills>';
    $styles .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
    $styles .= '<cellXfs count="2">';
    $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>';
    $styles .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/>';
    $styles .= '</cellXfs>';
    $styles .= '</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);

    // xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $workbook .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $workbook .= '<sheets>';
    
    foreach ($sheetData as $sheet) {
        $workbook .= '<sheet name="' . xmlspecialchars($sheet['name']) . '" sheetId="' . $sheet['index'] . '" r:id="rId' . $sheet['index'] . '"/>';
    }
    
    $workbook .= '</sheets>';
    $workbook .= '</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    // Create worksheets
    foreach ($sheetData as $sheet) {
        $ws = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $ws .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $ws .= '<sheetData>';
        
        $rowNum = 1;
        foreach ($sheet['rows'] as $rowData) {
            $ws .= '<row r="' . $rowNum . '">';
            $colNum = 0;
            
            foreach ($rowData as $cellValue) {
                $colLetter = num_to_col($colNum);
                $cellRef = $colLetter . $rowNum;
                
                // Apply bold style to header row
                $styleId = ($rowNum == 1) ? '1' : '0';
                
                if (is_numeric($cellValue) && $cellValue !== null && $cellValue !== '') {
                    // Numeric cell
                    $ws .= '<c r="' . $cellRef . '" s="' . $styleId . '"><v>' . xmlspecialchars($cellValue) . '</v></c>';
                } elseif ($cellValue !== null && $cellValue !== '') {
                    // String cell (via shared strings)
                    $strIndex = $sharedStringsMap[(string)$cellValue];
                    $ws .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="s"><v>' . $strIndex . '</v></c>';
                } else {
                    // Empty cell
                    $ws .= '<c r="' . $cellRef . '" s="' . $styleId . '"/>';
                }
                
                $colNum++;
            }
            
            $ws .= '</row>';
            $rowNum++;
        }
        
        $ws .= '</sheetData>';
        $ws .= '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet' . $sheet['index'] . '.xml', $ws);
    }

    // docProps/core.xml
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $core .= '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
    $core .= '<dc:creator>Hardware Inventory System</dc:creator>';
    $core .= '<cp:lastModifiedBy>Hardware Inventory System</cp:lastModifiedBy>';
    $core .= '<dcterms:created xsi:type="dcterms:W3CDTF">' . date('Y-m-d\TH:i:s\Z') . '</dcterms:created>';
    $core .= '<dcterms:modified xsi:type="dcterms:W3CDTF">' . date('Y-m-d\TH:i:s\Z') . '</dcterms:modified>';
    $core .= '</cp:coreProperties>';
    $zip->addFromString('docProps/core.xml', $core);

    // docProps/app.xml
    $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $app .= '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">';
    $app .= '<Application>Hardware Inventory System</Application>';
    $app .= '</Properties>';
    $zip->addFromString('docProps/app.xml', $app);

    $zip->close();

    // Read and return file contents
    $content = file_get_contents($tempFile);
    unlink($tempFile);
    
    return $content;
}

/**
 * Convert column number to Excel column letter (0=A, 1=B, etc.)
 */
function num_to_col($num)
{
    $letter = '';
    while ($num >= 0) {
        $letter = chr($num % 26 + 65) . $letter;
        $num = floor($num / 26) - 1;
    }
    return $letter;
}

/**
 * XML-safe character escaping
 */
function xmlspecialchars($str)
{
    return htmlspecialchars((string)$str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Export to CSV (single file with all computers - basic version)
 */
function export_computers_csv(mysqli $conn)
{
    // Clear any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Check if we have data
    $result = $conn->query("SELECT COUNT(*) as cnt FROM computers");
    $row = $result->fetch_assoc();
    if ($row['cnt'] == 0) {
        header('Location: index.php?error=no_data');
        exit;
    }

    // Set headers for CSV download
    $filename = 'hardware_inventory_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output stream
    $output = fopen('php://output', 'w');

    // UTF-8 BOM
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    fputcsv($output, [
        'Computer ID',
        'Serienummer',
        'Fabrikant',
        'Model',
        'Type',
        'CPU Aantal',
        'CPU Model',
        'CPU Snelheid',
        'Totaal RAM',
        'Moederbord',
        'BIOS Versie',
        'RAM Modules',
        'Storage Devices',
        'Network Interfaces',
        'GPUs',
        'Batteries',
        'Sound Cards',
        'Storage Controllers',
        'Optical Drives',
        'Import Datum'
    ], ';');

    // Get all computers with their components
    $query = "SELECT * FROM computers ORDER BY import_date DESC";
    $result = $conn->query($query);

    while ($computer = $result->fetch_assoc()) {
        $id = $computer['id'];

        // Get RAM modules
        $ramResult = $conn->query("SELECT CONCAT(size_gb, 'GB ', type, ' ', speed_mhz, 'MHz (', slot, ')') as ram_info FROM ram_modules WHERE computer_id = $id");
        $ramList = [];
        while ($ram = $ramResult->fetch_assoc()) {
            $ramList[] = $ram['ram_info'];
        }

        // Get storage devices
        $storageResult = $conn->query("SELECT CONCAT(vendor, ' ', model, ' ', size_gb, 'GB (', interface, ')') as storage_info FROM storage_devices WHERE computer_id = $id");
        $storageList = [];
        while ($storage = $storageResult->fetch_assoc()) {
            $storageList[] = $storage['storage_info'];
        }

        // Get NICs
        $nicResult = $conn->query("SELECT CONCAT(vendor, ' ', model, ' (', mac_address, ')') as nic_info FROM network_interfaces WHERE computer_id = $id");
        $nicList = [];
        while ($nic = $nicResult->fetch_assoc()) {
            $nicList[] = $nic['nic_info'];
        }

        // Get GPUs
        $gpuResult = $conn->query("SELECT CONCAT(vendor, ' ', model) as gpu_info FROM gpus WHERE computer_id = $id");
        $gpuList = [];
        while ($gpu = $gpuResult->fetch_assoc()) {
            $gpuList[] = $gpu['gpu_info'];
        }

        // Get batteries
        $batteryResult = $conn->query("SELECT CONCAT(manufacturer, ' ', product, ' ', capacity_wh, 'Wh') as battery_info FROM batteries WHERE computer_id = $id");
        $batteryList = [];
        while ($battery = $batteryResult->fetch_assoc()) {
            $batteryList[] = $battery['battery_info'];
        }

        // Get sound cards
        $soundResult = $conn->query("SELECT CONCAT(vendor, ' ', model) as sound_info FROM sound_cards WHERE computer_id = $id");
        $soundList = [];
        while ($sound = $soundResult->fetch_assoc()) {
            $soundList[] = $sound['sound_info'];
        }

        // Get storage controllers
        $controllerResult = $conn->query("SELECT CONCAT(vendor, ' ', model) as controller_info FROM storage_controllers WHERE computer_id = $id");
        $controllerList = [];
        while ($controller = $controllerResult->fetch_assoc()) {
            $controllerList[] = $controller['controller_info'];
        }

        // Get optical drives
        $driveResult = $conn->query("SELECT CONCAT(vendor, ' ', model, ' (', type, ')') as drive_info FROM drives WHERE computer_id = $id");
        $driveList = [];
        while ($drive = $driveResult->fetch_assoc()) {
            $driveList[] = $drive['drive_info'];
        }

        // Write row
        fputcsv($output, [
            $computer['id'],
            $computer['serial_number'],
            $computer['manufacturer'],
            $computer['computer_model'],
            $computer['computer_type'],
            $computer['cpu_count'],
            $computer['cpu_model'],
            $computer['cpu_speed'],
            $computer['memory_total'],
            $computer['motherboard'],
            $computer['bios_version'],
            implode('; ', $ramList),
            implode('; ', $storageList),
            implode('; ', $nicList),
            implode('; ', $gpuList),
            implode('; ', $batteryList),
            implode('; ', $soundList),
            implode('; ', $controllerList),
            implode('; ', $driveList),
            $computer['import_date']
        ], ';');
    }

    fclose($output);
    exit;
}