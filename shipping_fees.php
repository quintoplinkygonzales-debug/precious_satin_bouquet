<?php
/**
 * Precious Satin Bouquet — Shipping Fee Configuration
 *
 * Fee is determined by MUNICIPALITY for all areas.
 * JASAAN is the only municipality with barangay-level breakdown.
 */

// ── Dropdown groups for register/profile pages ───────────────────────────────
$BARANGAY_GROUPS = [

    'Jasaan' => [
        'Bobontugan'      => 0,
        'Jampason'        => 10,
        'Lower Jasaan'    => 10,
        'Upper Jasaan'    => 10,
        'Kimaya'          => 15,
        'San Antonio'     => 15,
        'Danao'           => 20,
        'Luz Banzon'      => 20,
        'Solana'          => 20,
        'Aplaya'          => 20,
        'Corrales'        => 20,
        'Ignacio S. Cruz' => 20,
        'Natubo'          => 20,
        'San Isidro'      => 20,
        'San Nicolas'     => 20,
        'San Pedro'       => 20,
        'Sta. Cruz'       => 20,
        'Poblacion'       => 20,
    ],

    'Villanueva' => [
        'Balacanas'   => 25,
        'Dayawan'     => 25,
        'Imelda'      => 25,
        'Katipunan'   => 25,
        'Kimaya'      => 25,
        'Looc'        => 25,
        'Poblacion 1' => 25,
        'Poblacion 2' => 25,
        'Poblacion 3' => 25,
        'San Martin'  => 25,
        'Tambobong'   => 25,
    ],

    'Balingasag' => [
        'Balagnan'              => 25,
        'Baliwagan'             => 25,
        'Barangay 1 (Poblacion)'=> 25,
        'Barangay 2 (Poblacion)'=> 25,
        'Barangay 3 (Poblacion)'=> 25,
        'Barangay 4 (Poblacion)'=> 25,
        'Barangay 5 (Poblacion)'=> 25,
        'Barangay 6 (Poblacion)'=> 25,
        'Binitinan'             => 25,
        'Blanco'                => 25,
        'Calawag'               => 25,
        'Camuayan'              => 25,
        'Cogon'                 => 25,
        'Dansuli'               => 25,
        'Dumarait'              => 25,
        'Hermano'               => 25,
        'Kibanban'              => 25,
        'Linabu'                => 25,
        'Linggangao'            => 25,
        'Mambayaan'             => 25,
        'Mandangoa'             => 25,
        'Napaliran'             => 25,
        'Quezon'                => 25,
        'Rosario'               => 25,
        'Samay'                 => 25,
        'San Francisco'         => 25,
        'San Isidro'            => 25,
        'San Juan'              => 25,
        'Talusan'               => 25,
        'Waterfall'             => 25,
    ],

    'Bugo' => [
        'Bugo' => 30,
    ],

    'Lagonglong' => [
        'Banglay'    => 30,
        'Dampil'     => 30,
        'Gaston'     => 30,
        'Kabulawan'  => 30,
        'Kauswagan'  => 30,
        'Lumbo'      => 30,
        'Manaol'     => 30,
        'Poblacion'  => 30,
        'Tabok'      => 30,
        'Umagos'     => 30,
    ],

    'Tagoloan' => [
        'Baluarte'       => 35,
        'Casinglot'      => 35,
        'Gracia'         => 35,
        'Mohon'          => 35,
        'Natumolan'      => 35,
        'Poblacion'      => 35,
        'Rosario'        => 35,
        'Santa Ana'      => 35,
        'Santa Cruz'     => 35,
        'Sugbongcogon'   => 35,
    ],

    'Puerto' => [
        'Puerto' => 40,
    ],

    'Salay' => [
        'Alipuaton'    => 40,
        'Ampenican'    => 40,
        'Bunal'        => 40,
        'Casulog'      => 40,
        'Dinagsaan'    => 40,
        'Guinalaban'   => 40,
        'Ili-ilihon'   => 40,
        'Inobulan'     => 40,
        'Looc'         => 40,
        'Matampa'      => 40,
        'Membuli'      => 40,
        'Poblacion'    => 40,
        'Salagsag'     => 40,
        'Salay River I'  => 40,
        'Salay River II' => 40,
        'Saray'        => 40,
        'Tinagaan'     => 40,
        'Yungod'       => 40,
    ],
];

// ── Municipality → flat fee ──────────────────────────────────────────────────
$MUNICIPALITY_FEES = [
    'villanueva' => 25,
    'balingasag' => 25,
    'bugo'       => 30,
    'lagonglong' => 30,
    'tagoloan'   => 35,
    'puerto'     => 40,
    'salay'      => 40,
];

// ── Jasaan barangay-specific fees ────────────────────────────────────────────
$JASAAN_BARANGAY_FEES = [
    'bobontugan'      => 0,
    'jampason'        => 10,
    'lower jasaan'    => 10,
    'upper jasaan'    => 10,
    'kimaya'          => 15,
    'san antonio'     => 15,
    'danao'           => 20,
    'luz banzon'      => 20,
    'luzbanson'       => 20,
    'solana'          => 20,
    'aplaya'          => 20,
    'corrales'        => 20,
    'ignacio s. cruz' => 20,
    'natubo'          => 20,
    'san isidro'      => 20,
    'san nicolas'     => 20,
    'san pedro'       => 20,
    'sta. cruz'       => 20,
    'sta cruz'        => 20,
    'poblacion'       => 20,
    'jasaan'          => 20,  // catch-all for any Jasaan address
];

/**
 * getShippingFee()
 *
 * Accepts a barangay name, municipality name, or full address string.
 *
 * Priority:
 *   1. Split by comma — exact municipality match on any part
 *   2. Split by comma — exact Jasaan barangay match on any part
 *   3. Word-boundary scan — municipality anywhere in the full string
 *   4. Word-boundary scan — Jasaan barangay anywhere in the full string
 *
 * @param  string $input  barangay, municipality, or full address
 * @return array  ['fee' => int, 'label' => string, 'found' => bool]
 */
function getShippingFee(string $input): array {
    global $MUNICIPALITY_FEES, $JASAAN_BARANGAY_FEES;

    $input = trim($input);
    if ($input === '') {
        return ['fee' => 0, 'label' => '', 'found' => false];
    }

    $lower = strtolower($input);
    $parts = array_map('trim', explode(',', $lower));

    // Step 1 — exact match per comma-separated part
    foreach ($parts as $part) {
        if (isset($MUNICIPALITY_FEES[$part])) {
            return ['fee' => $MUNICIPALITY_FEES[$part], 'label' => ucwords($part), 'found' => true];
        }
        if (isset($JASAAN_BARANGAY_FEES[$part])) {
            return ['fee' => $JASAAN_BARANGAY_FEES[$part], 'label' => ucwords($part), 'found' => true];
        }
    }

    // Step 2 — word-boundary scan, municipalities first (longer keys first)
    $muns = $MUNICIPALITY_FEES;
    uksort($muns, fn($a, $b) => strlen($b) - strlen($a));
    foreach ($muns as $mun => $fee) {
        if (preg_match('/\b' . preg_quote($mun, '/') . '\b/i', $lower)) {
            return ['fee' => $fee, 'label' => ucwords($mun), 'found' => true];
        }
    }

    // Step 3 — word-boundary scan, Jasaan barangays (longer keys first)
    $jasaan = $JASAAN_BARANGAY_FEES;
    uksort($jasaan, fn($a, $b) => strlen($b) - strlen($a));
    foreach ($jasaan as $bgy => $fee) {
        if (preg_match('/\b' . preg_quote($bgy, '/') . '\b/i', $lower)) {
            return ['fee' => $fee, 'label' => ucwords($bgy), 'found' => true];
        }
    }

    return ['fee' => 0, 'label' => '', 'found' => false];
}

/**
 * formatFee() — display helper
 */
function formatFee(int $fee): string {
    return $fee === 0 ? '🎉 Free' : '₱' . number_format($fee, 2);
}