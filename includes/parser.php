<?php
// ---------- includes/parser.php ----------
require_once __DIR__ . '/helpers.php';

/**
 * Parse WipeDrive XML (zoals je voorbeelden) naar een genormaliseerde array:
 * [
 *   'computer' => [...],
 *   'ram_modules' => [[...], ...],
 *   'storage_devices' => [[...], ...],
 *   'network_interfaces' => [[...], ...],
 *   'gpus' => [[...], ...],
 *   'drives' => [[...], ...],
 *   'batteries' => [[...], ...],
 *   'sound_cards' => [[...], ...],
 *   'storage_controllers' => [[...], ...]
 * ]
 */
function parse_wipedrive_xml($filepath) {
    if (!file_exists($filepath)) return false;

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($filepath);
    if (!$xml) return false;

    // Meestal: <Reports><Report>...<Hardware>...</Hardware></Report></Reports>
    $report = $xml->Report ?? null;
    $hardware = $report ? $report->Hardware : ($xml->Hardware ?? null);

    $out = [
        'computer' => [
            'serial_number'   => null,
            'manufacturer'    => null,
            'computer_model'  => null,
            'computer_type'   => null,
            'cpu_count'       => 0,
            'cpu_model'       => null,
            'cpu_speed'       => null,
            'memory_total'    => null,
            'motherboard'     => null,
            'bios_version'    => null,
        ],
        'ram_modules' => [],
        'storage_devices' => [],
        'network_interfaces' => [],
        'gpus' => [],
        'drives' => [],
        'batteries' => [],
        'sound_cards' => [],
        'storage_controllers' => [],
    ];

    if ($hardware) {
        $out['computer']['manufacturer']   = str_or_null($hardware->ComputerVendor);
        $out['computer']['computer_model'] = str_or_null($hardware->ComputerModel);
        $out['computer']['serial_number']  = str_or_null($hardware->ComputerSerial);
        $out['computer']['motherboard']    = str_or_null($hardware->MotherboardModel);
        $out['computer']['computer_type']  = str_or_null($hardware->ChassisType);

        // CPU
        $cpuNodes = $hardware->Processors->Processor ?? null;
        if ($cpuNodes) {
            $cpus = is_array($cpuNodes) ? $cpuNodes : [$cpuNodes];
            $count = 0;
            foreach ($cpus as $cpu) {
                $count++;
                if ($count === 1) {
                    $vendor = str_or_null($cpu->Vendor);
                    $name   = str_or_null($cpu->Name);
                    $out['computer']['cpu_model'] = trim(($vendor ? $vendor . ' ' : '') . ($name ?: ''));
                    $out['computer']['cpu_speed'] = str_or_null($cpu->Speed); // bijv. "2400 MHz"
                }
            }
            $out['computer']['cpu_count'] = $count;
        }

        // RAM total
        $out['computer']['memory_total'] = str_or_null($hardware->RAM->TotalCapacity);

        // BIOS
        if (isset($hardware->Bios)) {
            $out['computer']['bios_version'] = str_or_null($hardware->Bios->Version);
        }

        // RAM sticks
        if (isset($hardware->RAM->Stick)) {
            $sticks = $hardware->RAM->Stick;
            if (!is_array($sticks)) $sticks = [$sticks];
            $slotIndex = 0;
            foreach ($sticks as $s) {
                $slotIndex++;
                $out['ram_modules'][] = [
                    'size_gb'       => parse_capacity_mb_to_gb(str_or_null($s->Capacity)),
                    'type'          => str_or_null($s->Type),
                    'speed_mhz'     => parse_speed_mhz(str_or_null($s->Speed)),
                    'manufacturer'  => str_or_null($s->Vendor),
                    'form_factor'   => str_or_null($s->FormFactor),
                    'serial_number' => str_or_null($s->SerialNumber),
                    'part_number'   => str_or_null($s->PartNumber),
                    'slot'          => 'Slot ' . $slotIndex,
                ];
            }
        }

        // Storage devices (HDD/SSD/NVMe)
        if (isset($hardware->Devices->Device)) {
            $devs = $hardware->Devices->Device;
            if (!is_array($devs)) $devs = [$devs];
            foreach ($devs as $d) {
                $out['storage_devices'][] = [
                    'model'      => str_or_null($d->Product),
                    'vendor'     => str_or_null($d->Vendor),
                    'size_gb'    => parse_size_gb_from_gb_field(str_or_null($d->Gigabytes)),
                    'type'       => str_or_null($d->DriveMediaType),   // SSD / Platter
                    'interface'  => str_or_null($d->Interface),        // SATA/NVMe
                    'serial'     => str_or_null($d->Serial),
                    'is_ssd'     => yesno_to_bool_or_null(str_or_null($d->IsSSD)),
                    'media_type' => str_or_null($d->DriveMediaType),
                ];
            }
        }

        // NICs
        if (isset($hardware->NICs->NIC)) {
            $nics = $hardware->NICs->NIC;
            if (!is_array($nics)) $nics = [$nics];
            foreach ($nics as $nic) {
                $out['network_interfaces'][] = [
                    'name'           => null, // WipeDrive heeft meestal geen logicalname, dus leeg
                    'description'    => str_or_null($nic->Product),
                    'mac_address'    => str_or_null($nic->MACAddress),
                    'speed_mbps'     => parse_speed_to_mbps(str_or_null($nic->Speed)),
                    'vendor'         => str_or_null($nic->Vendor),
                    'model'          => str_or_null($nic->Product),
                    'interface_type' => str_or_null($nic->Interface),
                    'pci_id'         => str_or_null($nic->PciId),
                ];
            }
        }

        // GPUs (DisplayAdapters)
        if (isset($hardware->DisplayAdapters->DisplayAdapter)) {
            $g = $hardware->DisplayAdapters->DisplayAdapter;
            if (!is_array($g)) $g = [$g];
            foreach ($g as $gpu) {
                $out['gpus'][] = [
                    'vendor' => str_or_null($gpu->Vendor),
                    'model'  => str_or_null($gpu->Product),
                    'pci_id' => str_or_null($gpu->PciId),
                    'memory_gb' => null
                ];
            }
        }

        // Sound cards (MultimediaCards)
        if (isset($hardware->MultimediaCards->MultimediaCard)) {
            $m = $hardware->MultimediaCards->MultimediaCard;
            if (!is_array($m)) $m = [$m];
            foreach ($m as $mm) {
                $out['sound_cards'][] = [
                    'vendor'      => str_or_null($mm->Vendor),
                    'model'       => str_or_null($mm->Product),
                    'description' => str_or_null($mm->Product),
                    'pci_id'      => str_or_null($mm->PciId),
                ];
            }
        }

        // Batteries
        if (isset($hardware->Batteries->Battery)) {
            $b = $hardware->Batteries->Battery;
            if (!is_array($b)) $b = [$b];
            foreach ($b as $bat) {
                $out['batteries'][] = [
                    'type'                 => str_or_null($bat->Type),
                    'capacity_wh'          => micro_to_watt_hours(str_or_null($bat->FullCapacity)), // voorkeur FullCapacity
                    'manufacturer'         => str_or_null($bat->Vendor),
                    'product'              => str_or_null($bat->Product),
                    'serial_number'        => str_or_null($bat->SerialNumber),
                    'voltage_now_mv'       => to_int_or_null(str_or_null($bat->VoltageNow)),
                    'voltage_min_design_mv'=> to_int_or_null(str_or_null($bat->VoltageMinDesign)),
                    'voltage_max_design_mv'=> to_int_or_null(str_or_null($bat->VoltageMaxDesign)),
                    'status'               => null,
                ];
            }
        }

        // Storage controllers
        if (isset($hardware->StorageControllers->StorageController)) {
            $sc = $hardware->StorageControllers->StorageController;
            if (!is_array($sc)) $sc = [$sc];
            foreach ($sc as $ctrl) {
                $out['storage_controllers'][] = [
                    'vendor'      => str_or_null($ctrl->Vendor),
                    'model'       => str_or_null($ctrl->Product),
                    'description' => str_or_null($ctrl->Description),
                    'type'        => str_or_null($ctrl->Description), // vaak "SATA controller"
                    'pci_id'      => str_or_null($ctrl->PciId),
                ];
            }
        }

        // Optical Drives
        if (isset($hardware->OpticalDrives->OpticalDrive)) {
            $od = $hardware->OpticalDrives->OpticalDrive;
            if (!is_array($od)) $od = [$od];
            foreach ($od as $drive) {
                $out['drives'][] = [
                    'type'        => str_or_null($drive->Description), // CD/DVD/BD writer
                    'vendor'      => str_or_null($drive->Vendor),
                    'model'       => str_or_null($drive->Product),
                    'description' => str_or_null($drive->Description),
                    'capabilities'=> str_or_null($drive->Capabilities),
                    'interface'   => null
                ];
            }
        }
    }

    // Basic fallback voor onbekende serial
    if (!$out['computer']['serial_number']) {
        $out['computer']['serial_number'] = 'AUTO_' . time() . '_' . bin2hex(random_bytes(4));
    }

    return $out;
}