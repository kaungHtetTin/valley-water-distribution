<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['app', 'config', 'database', 'lang', 'resources', 'routes', 'scripts', 'tests'];
$extensions = ['css', 'html', 'js', 'jsx', 'json', 'md', 'php', 'ts', 'tsx'];
$mojibakeMarkers = [
    "\u{00C2}",
    "\u{00C3}",
    "\u{00E2}\u{20AC}",
    "\u{00E1}\u{20AC}",
    "\u{00EF}\u{00BB}\u{00BF}",
    "\u{FFFD}",
];
$failures = [];

foreach ($directories as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.$directory;
    if (! is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            $failures[] = "{$relative}: invalid UTF-8";

            continue;
        }

        foreach ($mojibakeMarkers as $marker) {
            if (str_contains($contents, $marker)) {
                $failures[] = "{$relative}: suspected mojibake marker ".json_encode($marker, JSON_UNESCAPED_UNICODE);
                break;
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Source encoding check failed:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

fwrite(STDOUT, "Source encoding check passed.\n");
