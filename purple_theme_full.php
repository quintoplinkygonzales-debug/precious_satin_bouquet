<?php
// ============================================================================
//  PURPLE THEME - FULL RECOLOR  (whole project: every .php .css .js .html .svg .webmanifest)
// ----------------------------------------------------------------------------
//  Turns the ENTIRE color palette purple: pinks, roses, peaches, oranges, yellows,
//  blues, teals... every colored hex / rgb() / rgba() / hsl() / hsla() value gets its
//  HUE moved into the purple range. Lightness, saturation and transparency are kept,
//  so light tints stay light and dark tones stay dark (the design still looks the same,
//  just purple). White / black / greys are left alone.
//
//  It does NOT rename anything and does NOT touch text, links or PHP logic.
//
//  Run from the project folder (same folder as index.php):
//      php purple_theme_full.php              -> apply the recolor
//      php purple_theme_full.php --dry-run    -> preview only, changes nothing
//      php purple_theme_full.php --restore    -> put the original files back
//
//  Originals are saved once to  _backup_before_purple_full/  (same folder structure).
//  Safe to run more than once: colors that are already purple are not changed again.
//  After running, press Ctrl+Shift+R in the browser to clear the cached CSS.
// ============================================================================

// Set to false if you want errors / delete buttons (red) and success messages (green)
// to become purple too. Default keeps them so users can still tell them apart.
const KEEP_RED_ERRORS    = true;
const KEEP_GREEN_SUCCESS = true;

const SKIP_DIRS  = ['vendor', 'node_modules', 'uploads', 'images', 'icons', '.git'];
const SKIP_START = ['_backup', 'purple_theme', 'satin_only_patch', 'run_'];
const COLOR_EXT  = ['php', 'css', 'js', 'html', 'svg', 'webmanifest'];
const BACKUP_DIR = '_backup_before_purple_full';

$root    = __DIR__;
$dryRun  = in_array('--dry-run', $argv ?? [], true);
$restore = in_array('--restore', $argv ?? [], true);

// ---- colour maths ----------------------------------------------------------
function rgb_to_hsl($r, $g, $b)
{
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b);
    $l = ($max + $min) / 2; $h = 0; $s = 0;
    if ($max != $min) {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if ($max == $r)      { $h = ($g - $b) / $d + ($g < $b ? 6 : 0); }
        elseif ($max == $g)  { $h = ($b - $r) / $d + 2; }
        else                 { $h = ($r - $g) / $d + 4; }
        $h *= 60;
    }
    return [$h, $s, $l];
}

function hue_to_rgb($p, $q, $t)
{
    if ($t < 0) { $t += 1; }
    if ($t > 1) { $t -= 1; }
    if ($t < 1 / 6) { return $p + ($q - $p) * 6 * $t; }
    if ($t < 1 / 2) { return $q; }
    if ($t < 2 / 3) { return $p + ($q - $p) * (2 / 3 - $t) * 6; }
    return $p;
}

function hsl_to_rgb($h, $s, $l)
{
    $h /= 360;
    if ($s == 0) { $v = (int)round($l * 255); return [$v, $v, $v]; }
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    return [
        (int)round(hue_to_rgb($p, $q, $h + 1 / 3) * 255),
        (int)round(hue_to_rgb($p, $q, $h) * 255),
        (int)round(hue_to_rgb($p, $q, $h - 1 / 3) * 255),
    ];
}

// New hue for a colour, or null = leave this colour exactly as it is.
function new_hue($h)
{
    $h = fmod(fmod($h, 360) + 360, 360);
    if ($h >= 250 && $h < 300) { return null; }                      // already purple
    if (KEEP_RED_ERRORS    && ($h < 12 || $h >= 353)) { return null; } // red = errors / delete
    if (KEEP_GREEN_SUCCESS && $h >= 95 && $h <= 165)  { return null; } // green = success
    return 258 + ($h / 360) * 30;                                    // everything else -> 258..288 (violet / purple)
}

function recolor_rgb($r, $g, $b)
{
    [$h, $s, $l] = rgb_to_hsl($r, $g, $b);
    if ($s < 0.08) { return null; }                                  // white / black / grey
    $nh = new_hue($h);
    return $nh === null ? null : hsl_to_rgb($nh, $s, $l);
}

// Recolours one hex value (without the #). Returns the new hex, or null to leave it alone.
function recolor_hex($x, $ext)
{
    // digits-only like #1234 / #000123 is usually an order number in text, not a colour
    if ($ext !== 'css' && !preg_match('/[a-fA-F]/', $x)) { return null; }
    if (strlen($x) <= 4) {
        $full = '';
        foreach (str_split($x) as $c) { $full .= $c . $c; }
        $x = $full;
    }
    $alpha = strlen($x) === 8 ? substr($x, 6, 2) : '';
    $new = recolor_rgb(hexdec(substr($x, 0, 2)), hexdec(substr($x, 2, 2)), hexdec(substr($x, 4, 2)));
    if ($new === null) { return null; }
    return sprintf('%02x%02x%02x', $new[0], $new[1], $new[2]) . $alpha;
}

// ---- text recolouring ------------------------------------------------------
function recolor_text($src, $ext, &$count)
{
    // hex: #rgb #rgba #rrggbb #rrggbbaa
    // Not touched: &#8369; entities, href="#id", url(#id), and CSS/JS ID selectors such as  #add{ ... }  #add, .x{ ... }
    $src = preg_replace_callback(
        '/(?<![&\w#])(?<!href=")(?<!href=\')(?<!url\()#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})(?![0-9a-zA-Z_-])(?!\s*(?:\{|[.:\[>+~]|,[^;{}]*\{))/',
        function ($m) use (&$count, $ext) {
            $r = recolor_hex($m[1], $ext);
            if ($r === null) { return $m[0]; }
            $count++;
            return '#' . $r;
        },
        $src
    );

    // url-encoded hex inside inline SVG data URIs:  fill='%23f08050'
    $src = preg_replace_callback(
        '/%23([0-9a-fA-F]{6}|[0-9a-fA-F]{3})(?![0-9a-zA-Z])/',
        function ($m) use (&$count, $ext) {
            $r = recolor_hex($m[1], $ext);
            if ($r === null) { return $m[0]; }
            $count++;
            return '%23' . $r;
        },
        $src
    );

    // rgb() / rgba()  - comma or space separated, optional alpha
    $src = preg_replace_callback(
        '/\b(rgba?)\(\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*[,\s]\s*(\d{1,3})\s*(?:[,\/]\s*([\d.]+%?)\s*)?\)/i',
        function ($m) use (&$count) {
            $new = recolor_rgb((int)$m[2], (int)$m[3], (int)$m[4]);
            if ($new === null) { return $m[0]; }
            $count++;
            return $m[1] . '(' . $new[0] . ',' . $new[1] . ',' . $new[2] . (isset($m[5]) && $m[5] !== '' ? ',' . $m[5] : '') . ')';
        },
        $src
    );

    // hsl() / hsla()  - only the hue number changes
    $src = preg_replace_callback(
        '/(\bhsla?\(\s*)(-?[\d.]+)(deg)?(\s*[,\s]\s*)([\d.]+)(%\s*[,\s]\s*[\d.]+%)/i',
        function ($m) use (&$count) {
            if ((float)$m[5] < 8) { return $m[0]; }
            $nh = new_hue((float)$m[2]);
            if ($nh === null) { return $m[0]; }
            $count++;
            return $m[1] . round($nh) . ($m[3] ?? '') . $m[4] . $m[5] . $m[6];
        },
        $src
    );
    return $src;
}

// ---- file walking ----------------------------------------------------------
function starts_with_any($name, $list)
{
    foreach ($list as $p) { if (strpos($name, $p) === 0) { return true; } }
    return false;
}

function walk($dir, &$files)
{
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $path = $dir . '/' . $item;
        if (starts_with_any($item, SKIP_START)) { continue; }
        if (is_dir($path)) {
            if (in_array(strtolower($item), SKIP_DIRS, true)) { continue; }
            walk($path, $files);
        } else {
            $files[] = $path;
        }
    }
}

// ---- restore mode ----------------------------------------------------------
if ($restore) {
    $bk = $root . '/' . BACKUP_DIR;
    if (!is_dir($bk)) { exit("No backup folder found (" . BACKUP_DIR . "). Nothing to restore.\n"); }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bk, FilesystemIterator::SKIP_DOTS));
    $n = 0;
    foreach ($it as $f) {
        $rel = ltrim(substr($f->getPathname(), strlen($bk)), '/\\');
        copy($f->getPathname(), $root . '/' . $rel);
        echo "$rel: restored\n";
        $n++;
    }
    exit("\n$n file(s) restored. Press Ctrl+Shift+R in your browser.\n");
}

// ---- main ------------------------------------------------------------------
$files = [];
walk($root, $files);
$changedFiles = 0; $totalColors = 0; $untouched = 0;

foreach ($files as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, COLOR_EXT, true)) { continue; }

    $src = file_get_contents($file);
    if ($src === false) { continue; }

    $count = 0;
    $out = recolor_text($src, $ext, $count);
    $rel = ltrim(substr($file, strlen($root)), '/\\');

    if ($out === $src) { $untouched++; continue; }

    if (!$dryRun) {
        $dest = $root . '/' . BACKUP_DIR . '/' . $rel;
        if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0777, true); }
        if (!file_exists($dest)) { copy($file, $dest); }   // keep the TRUE original, even on re-runs
        file_put_contents($file, $out);
    }
    echo ($dryRun ? "would update" : "updated") . " $rel  ($count colors)\n";
    $changedFiles++; $totalColors += $count;
}

echo "\n" . ($dryRun ? "DRY RUN - nothing was changed.\n" : "");
echo "$changedFiles file(s), $totalColors color value(s) " . ($dryRun ? "would be " : "") . "turned purple. $untouched file(s) had nothing to change.\n";
if (!$dryRun) {
    echo "Originals saved in " . BACKUP_DIR . "/  (undo with: php purple_theme_full.php --restore)\n";
    echo "Now press Ctrl+Shift+R in your browser.\n";
}
