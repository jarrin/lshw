<?php
// ---------- includes/helpers.php ----------

function str_or_null($s) {
    if (!isset($s)) return null;
    $s = trim((string)$s);
    return $s === '' ? null : $s;
}

function to_int_or_null($s) {
    $s = str_or_null($s);
    if ($s === null) return null;
    $n = filter_var($s, FILTER_SANITIZE_NUMBER_INT);
    return ($n === '' ? null : (int)$n);
}

function to_float_or_null($s) {
    $s = str_or_null($s);
    if ($s === null) return null;
    $s = str_replace(',', '.', $s);
    $n = filter_var($s, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    return ($n === '' ? null : (float)$n);
}

function parse_capacity_mb_to_gb($text) {
    // "8192 MB" -> 8.00
    if (!$text) return null;
    if (preg_match('/([\d\.]+)\s*MB/i', $text, $m)) {
        return round(((float)$m[1]) / 1024, 2);
    }
    if (preg_match('/([\d\.]+)\s*GB/i', $text, $m)) {
        return round((float)$m[1], 2);
    }
    return null;
}

function parse_speed_mhz($text) {
    // "1600 MT/s" -> 1600   , "2400 MHz" -> 2400
    if (!$text) return null;
    if (preg_match('/([\d\.]+)\s*(MT\/s|MHz)/i', $text, $m)) {
        return (int)round((float)$m[1]);
    }
    return null;
}

function parse_size_gb_from_gb_field($text) {
    // "180.05" or "320.07" -> 180.05/320.07
    if (!$text) return null;
    $f = to_float_or_null($text);
    return $f !== null ? round($f, 2) : null;
}

function yesno_to_bool_or_null($text) {
    if (!isset($text)) return null;
    $t = strtolower(trim($text));
    if ($t === 'yes') return 1;
    if ($t === 'no') return 0;
    return null;
}

function parse_speed_to_mbps($text) {
    // e.g. "1Gbit/s" -> 1000 ; "100Mbit/s" -> 100 ; "Unavailable" or "" -> null
    $t = strtolower(trim((string)$text));
    if ($t === '' || $t === 'unavailable') return null;
    if (preg_match('/([\d\.]+)\s*gbit\/s/i', $t, $m)) return (int)round(((float)$m[1]) * 1000);
    if (preg_match('/([\d\.]+)\s*mbit\/s/i', $t, $m)) return (int)round((float)$m[1]);
    return null;
}

function micro_to_watt_hours($n) {
    // in WipeDrive komen battery capacities vaak in micro-Wh.
    // 3,486,000 -> 3.49 Wh
    if ($n === null || $n === '' || !is_numeric($n)) return null;
    $f = (float)$n;
    if ($f > 100000) return round($f / 1000000, 2); // aannemen micro-Wh
    // Als waarde al klein is, laat m staan (Wh)
    return round($f, 2);
}