<?php
// FLEUR DE SATIN COLLECTION + PURPLE THEME PATCH
//
//  1. Renames "Blossom Atelier"  ->  "Fleur de Satin Collection"
//  2. Recolors the WHOLE project (every .php / .css / .js / .html / .webmanifest file, all folders)
//     from the pink / rose / peach / orange palette to purple shades.
//
// How the recoloring works: every hex (#f08050, #fce4f0, #fff...) and rgb()/rgba() color is
// converted to HSL and only its HUE is shifted into the purple range. Lightness, saturation and
// transparency are kept, so light tints stay light and dark tones stay dark.
// Left alone on purpose: white / black / greys, greens (success), yellows (warnings), and the
// red used for errors, delete buttons and notification badges.
//
// Originals are copied to _backup_before_purple (same folder structure). Safe to run again.
//
// Run from the project folder:  php purple_theme_patch.php   (or double-click run_purple_theme.bat)

const SKIP_DIRS  = ['vendor', 'node_modules', 'uploads', 'images', 'icons', '.git'];
const SKIP_START = ['_backup', 'satin_only_patch', 'purple_theme_patch', 'run_'];
const COLOR_EXT  = ['php', 'css', 'js', 'html', 'webmanifest'];
const TEXT_EXT   = ['php', 'css', 'js', 'html', 'webmanifest', 'md'];

// ── hue remap: returns the new hue, or null to leave the color as it is ─────────
function remap_hue($h)
{
    if ($h >= 300 && $h < 352) { return 262 + ($h - 300) / 52 * 30; }  // pinks / magentas / roses -> violet
    if ($h >= 9   && $h <= 33) { return 258 + ($h - 9)   / 24 * 14; }  // peach / orange / warm browns -> purple
    return null;                                                        // reds, yellows, greens, blues, purples: keep
}

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
    if ($s == 0) { $v = round($l * 255); return [$v, $v, $v]; }
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    return [
        (int)round(hue_to_rgb($p, $q, $h + 1 / 3) * 255),
        (int)round(hue_to_rgb($p, $q, $h) * 255),
        (int)round(hue_to_rgb($p, $q, $h - 1 / 3) * 255),
    ];
}

// Returns [r,g,b] recolored, or null if this color should not change.
function recolor($r, $g, $b)
{
    [$h, $s, $l] = rgb_to_hsl($r, $g, $b);
    if ($s < 0.08) { return null; }              // greys / white / black
    $nh = remap_hue($h);
    if ($nh === null) { return null; }
    return hsl_to_rgb($nh, $s, $l);
}

function recolor_css($src)
{
    // hex colors: #rgb #rgba #rrggbb #rrggbbaa  (not HTML entities like &#8369; and not #ids)
    $src = preg_replace_callback(
        '/(?<![&\w#])#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})(?![0-9a-zA-Z_-])/',
        function ($m) {
            $x = $m[1];
            $len = strlen($x);
            if ($len <= 4) {                       // expand shorthand
                $full = '';
                foreach (str_split($x) as $c) { $full .= $c . $c; }
                $x = $full;
            }
            $r = hexdec(substr($x, 0, 2)); $g = hexdec(substr($x, 2, 2)); $b = hexdec(substr($x, 4, 2));
            $alpha = strlen($x) === 8 ? substr($x, 6, 2) : '';
            $new = recolor($r, $g, $b);
            if ($new === null) { return $m[0]; }
            return '#' . sprintf('%02x%02x%02x', $new[0], $new[1], $new[2]) . $alpha;
        },
        $src
    );

    // rgb() / rgba()
    $src = preg_replace_callback(
        '/\b(rgba?)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+%?)\s*)?\)/i',
        function ($m) {
            $new = recolor((int)$m[2], (int)$m[3], (int)$m[4]);
            if ($new === null) { return $m[0]; }
            return $m[1] . '(' . $new[0] . ',' . $new[1] . ',' . $new[2] . (isset($m[5]) && $m[5] !== '' ? ',' . $m[5] : '') . ')';
        },
        $src
    );
    return $src;
}

function rename_brand($src)
{
    // hero title written over two lines:  Blossom <br> <span>Atelier</span>
    $src = preg_replace('/Blossom\s*<br\s*\/?>\s*<span>Atelier<\/span>/i', "Fleur de Satin <br>\n    <span>Collection</span>", $src);
    $src = str_replace(
        ['Blossom Atelier', 'BLOSSOM ATELIER', 'blossom atelier', 'explore the atelier'],
        ['Fleur de Satin Collection', 'FLEUR DE SATIN COLLECTION', 'fleur de satin collection', 'explore the collection'],
        $src
    );
    return $src;
}

function walk($dir, &$files)
{
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') { continue; }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (in_array(strtolower($item), SKIP_DIRS, true)) { continue; }
            foreach (SKIP_START as $p) { if (strpos($item, $p) === 0) { continue 2; } }
            walk($path, $files);
        } else {
            foreach (SKIP_START as $p) { if (strpos($item, $p) === 0) { continue 2; } }
            $files[] = $path;
        }
    }
}

$root  = __DIR__;
$files = [];
walk($root, $files);

$backup = $root . '/_backup_before_purple';
$done = 0; $unchanged = 0;

foreach ($files as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, TEXT_EXT, true)) { continue; }

    $src = file_get_contents($file);
    if ($src === false) { continue; }

    $out = rename_brand($src);
    if (in_array($ext, COLOR_EXT, true)) { $out = recolor_css($out); }

    $rel = ltrim(substr($file, strlen($root)), '/\\');
    if ($out === $src) { $unchanged++; continue; }

    $dest = $backup . '/' . $rel;
    if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0777, true); }
    if (!file_exists($dest)) { copy($file, $dest); }
    file_put_contents($file, $out);
    echo "$rel: updated\n";
    $done++;
}

echo "\n$done file(s) updated, $unchanged already fine.\n";
echo "Now press Ctrl+Shift+R in your browser.\n";
