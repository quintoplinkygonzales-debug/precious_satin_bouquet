<?php
// Precious Satin Bouquet: rebrand + PWA injector.
// Usage:  php pwa_setup.php [projectDir] [--apply]     (no --apply = dry run, nothing is changed)
$apply = in_array('--apply', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), fn($a) => substr($a, 0, 2) !== '--'));
$root  = rtrim($args[0] ?? '.', "/\\");
$brand = 'Precious Satin Bouquet';
$skipDirs  = ['vendor', 'node_modules', '_backup_before_pwa', '.git', 'icons'];
$skipFiles = ['pwa_head.php', 'pwa_setup.php', 'sw.js', 'offline.html'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$renamed = $injected = 0; $notes = [];

foreach ($it as $f) {
    $path = $f->getPathname();
    $rel  = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    if (array_intersect(explode('/', $rel), $skipDirs) || in_array(basename($path), $skipFiles, true)) continue;
    $ext = strtolower($f->getExtension());
    if (!in_array($ext, ['php', 'html', 'htm', 'md', 'css', 'js', 'txt', 'sql'], true)) continue;

    $src = file_get_contents($path); $out = $src;
    if ($ext !== 'sql') {
        $out = preg_replace('/serene\s+blooms\s*(?:–|—|-)\s*blossom\s+atelier/i', $brand, $out);
        $out = preg_replace_callback('/serene\s*blooms/i', function ($m) use ($brand) {
            $letters = preg_replace('/\s/', '', $m[0]);
            if (ctype_upper($letters)) return strtoupper($brand);
            return preg_match('/\s/', $m[0]) ? $brand : 'PreciousSatinBouquet';
        }, $out);
        // Old hardcoded folder path in URLs -> relative (fixes the 404 on /serene-blooms/...)
        $out = preg_replace('/(["\'`])\/?serene-blooms\//i', '$1', $out);
        // script.js: stop the TypeError on pages that have no loader element
        if (basename($path) === 'script.js' && strpos($out, 'if (!intro) return;') === false) {
            $out = str_replace('const intro = document.querySelector(".intro.loader");',
                "const intro = document.querySelector(\".intro.loader\");\n  if (!intro) return;", $out);
        }
        if ($ext === 'php' && strpos($rel, '/') === false && stripos($out, '</head>') !== false && strpos($out, 'pwa_head.php') === false) {
            $out = preg_replace('/<\/head>/i', "<?php include __DIR__ . '/pwa_head.php'; ?>\n</head>", $out, 1);
            $injected++;
        }
    }
    // Left untouched on purpose: database/session identifiers and "Blossom Atelier".
    $slug = preg_match_all('/serene[_-]blooms/i', $out); $atel = preg_match_all('/blossom\s+atelier/i', $out);
    if ($ext === 'sql') $slug += preg_match_all('/serene\s+blooms/i', $out);
    if ($slug || $atel) $notes[] = sprintf("  %-32s serene_blooms x%d, 'Blossom Atelier' x%d", $rel, $slug, $atel);

    if ($out !== $src) {
        $renamed++;
        if ($apply) {
            $bak = "$root/_backup_before_pwa/$rel";
            if (!is_dir(dirname($bak))) mkdir(dirname($bak), 0777, true);
            copy($path, $bak);
            file_put_contents($path, $out);
        }
    }
}
echo ($apply ? "APPLIED" : "DRY RUN (add --apply to write changes)"), "\n";
echo "Files changed: $renamed | PWA tags injected into: $injected page(s)\n";
if ($notes) { echo "Left unchanged, review by hand (DB name / config / leftover brand text):\n", implode("\n", $notes), "\n"; }
