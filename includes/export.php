<?php
// ---------- includes/export.php ----------
require_once __DIR__ . '/database.php';

function export_computers_csv(mysqli $conn) {
    $res = $conn->query("SELECT * FROM computers ORDER BY import_date DESC");
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hardware_inventory_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Delimiter ; (fijn voor NL/Excel)
    fputcsv($out, [
        'Serienummer','Fabrikant','Model','Type',
        'CPU Aantal','CPU Model','CPU Snelheid',
        'Geheugen Totaal','Moederbord','BIOS Versie',
        'XML Bestandsnaam','Import Datum'
    ], ';');

    if ($res && $res->num_rows) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['serial_number'],
                $row['manufacturer'],
                $row['computer_model'],
                $row['computer_type'],
                $row['cpu_count'],
                $row['cpu_model'],
                $row['cpu_speed'],
                $row['memory_total'],
                $row['motherboard'],
                $row['bios_version'],
                $row['xml_file'],
                $row['import_date']
            ], ';');
        }
    }
    fclose($out);
    exit;
}