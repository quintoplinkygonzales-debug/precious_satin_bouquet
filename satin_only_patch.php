<?php
// SATIN-ONLY PATCH (v2): Satin becomes the only category everywhere.
//
//  Customer side : index.php (category buttons + product grid), fetch-products.php, search.php
//  Admin side    : admin_categories.php  (lists only Satin; add / rename / delete disabled)
//                  admin_inventory.php   (category filter, product list, add & edit dropdowns)
//                  add_product.php, edit_product.php (category dropdown)
//
// Nothing is deleted from the database, and originals are copied to _backup_before_satin2.
// Safe to run more than once. Each file is changed only if every required piece is found.
//
// Run from the project folder:  php satin_only_patch2.php   (or double-click run_satin_patch2.bat)

$SATIN = "'Satin'";

function apply_patch($name, $edits)
{
    $file = __DIR__ . '/' . $name;
    $src  = @file_get_contents($file);
    if ($src === false) { echo "$name: not found here, skipped.\n"; return; }

    $out = $src; $changed = 0; $problems = []; $notes = [];

    foreach ($edits as $label => $e) {
        $required = $e['required'] ?? true;
        $type     = $e['type'] ?? 'text';

        if ($type === 'text') {
            if (strpos($out, $e['replace']) !== false) { continue; }          // already applied
            $n = substr_count($out, $e['find']);
            if ($n !== 1) {
                $msg = "'$label' expected 1 match, found $n";
                if ($required) { $problems[] = $msg; } else { $notes[] = $msg . ' (optional, skipped)'; }
                continue;
            }
            $out = str_replace($e['find'], $e['replace'], $out);
            $changed++;
        } else { // regex
            if (strpos($out, $e['done']) !== false) { continue; }              // already applied
            $new = preg_replace($e['pattern'], $e['replace'], $out, 1, $cnt);
            if ($cnt !== 1) {
                $msg = "'$label' not found";
                if ($required) { $problems[] = $msg; } else { $notes[] = $msg . ' (optional, skipped)'; }
                continue;
            }
            $out = $new;
            $changed++;
        }
    }

    if ($problems) {
        echo "$name: NOT changed. " . implode('; ', $problems) . ".\n";
        return;
    }
    if (!$changed) { echo "$name: already patched.\n"; return; }

    $dir = __DIR__ . '/_backup_before_satin2';
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    copy($file, $dir . '/' . $name);
    file_put_contents($file, $out);
    echo "$name: done ($changed change" . ($changed > 1 ? 's' : '') . ").\n";
    foreach ($notes as $n) { echo "    note: $n\n"; }
}

$onlySatinList = 'FROM categories WHERE name = ' . $SATIN . ' ORDER BY name ASC';
$forceSatin    = '$category = ' . $SATIN . '; // Satin-only';
$forceFind     = '$category = $_GET[\'category\'] ?? \'\';';

// ───────────────────────── CUSTOMER SIDE ─────────────────────────
apply_patch('index.php', [
    'category fixed to Satin' => ['find' => $forceFind, 'replace' => $forceSatin],
    'category buttons'        => ['find' => 'FROM categories ORDER BY name ASC', 'replace' => $onlySatinList],
    'All button'              => [
        'type'    => 'regex',
        'pattern' => '/<a href="index\.php\?category=all".*?>All<\/a>/s',
        'replace' => '<!-- All button removed: Satin only -->',
        'done'    => 'All button removed: Satin only',
    ],
]);

apply_patch('fetch-products.php', [
    'category fixed to Satin' => ['find' => $forceFind, 'replace' => $forceSatin],
]);

apply_patch('search.php', [
    'search query' => [
        'find'    => 'WHERE p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?',
        'replace' => 'WHERE c.name = ' . $SATIN . ' AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)',
    ],
]);

// ───────────────────────── ADMIN SIDE ─────────────────────────
apply_patch('admin_categories.php', [
    'block add'    => ['find' => 'if (isset($_POST[\'add_category\']))',    'replace' => 'if (false && isset($_POST[\'add_category\']))'],
    'block rename' => ['find' => 'if (isset($_POST[\'rename_category\']))', 'replace' => 'if (false && isset($_POST[\'rename_category\']))'],
    'block delete' => ['find' => 'if (isset($_POST[\'delete_category\']))', 'replace' => 'if (false && isset($_POST[\'delete_category\']))'],
    'list only Satin' => ['find' => 'GROUP BY c.id, c.name', 'replace' => 'WHERE c.name = ' . $SATIN . ' GROUP BY c.id, c.name'],
    'hide add/rename/delete UI' => [
        'find'    => '</head>',
        'replace' => '<style>.side-panel,.row-actions{display:none!important}.adm-grid{grid-template-columns:1fr!important}</style>' . "\n" . '</head>',
    ],
]);

apply_patch('admin_inventory.php', [
    'category list'    => ['find' => 'FROM categories ORDER BY name ASC', 'replace' => $onlySatinList],
    'fallback list'    => [
        'find'     => "[['id'=>1,'name'=>'Fresh'],['id'=>2,'name'=>'Crochet'],['id'=>3,'name'=>'Fuzzywire'],['id'=>4,'name'=>'Plastic'],['id'=>5,'name'=>'Readymade'],['id'=>6,'name'=>'Satin']]",
        'replace'  => "[['id'=>6,'name'=>'Satin']]",
        'required' => false,
    ],
    'filtered products' => ['find' => 'WHERE c.name=? ORDER BY p.id DESC', 'replace' => 'WHERE c.name=? AND c.name = ' . $SATIN . ' ORDER BY p.id DESC'],
    'all products'      => ['find' => 'ON p.category_id = c.id ORDER BY p.id DESC', 'replace' => 'ON p.category_id = c.id WHERE c.name = ' . $SATIN . ' ORDER BY p.id DESC'],
    'dropdown label'    => [
        'find'     => '$activeLabel = \'All Categories\';',
        'replace'  => '$activeLabel = ' . $SATIN . ';',
        'required' => false,
    ],
    'hide "All" option' => [
        'find'    => '</head>',
        'replace' => '<style>.dropdown-item[data-value="all"],.dropdown-item[data-value="all"]+.dropdown-divider{display:none!important}</style>' . "\n" . '</head>',
    ],
]);

apply_patch('add_product.php', [
    'category dropdown' => ['find' => 'FROM categories ORDER BY name ASC', 'replace' => $onlySatinList],
]);

apply_patch('edit_product.php', [
    'category dropdown' => [
        'type'    => 'regex',
        'pattern' => '/<select id="category" name="category" required>.*?<\/select>/s',
        'replace' => '<select id="category" name="category" required>' . "\n" . '      <option value="Satin" selected>Satin</option>' . "\n" . '    </select>',
        'done'    => '<option value="Satin" selected>Satin</option>',
    ],
]);

echo "\nNow press Ctrl+Shift+R in your browser.\n";