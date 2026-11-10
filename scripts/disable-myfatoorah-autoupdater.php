<?php

/**
 * Disable the MyFatoorah library's built-in auto-updater.
 *
 * vendor/myfatoorah/library/autoload.php downloads PHP source from a remote
 * server and overwrites this package's own files at runtime. That turns a
 * third-party service (or anyone able to intercept it) into a remote code
 * execution channel, so the updater is neutralised after every install.
 *
 * Run automatically from composer's post-autoload-dump hook.
 */

$file = dirname(__DIR__) . '/vendor/myfatoorah/library/autoload.php';

if (!is_file($file)) {
    exit(0);
}

$contents = file_get_contents($file);
$marker = '// MyFatoorah auto-updater disabled by scripts/disable-myfatoorah-autoupdater.php';

if (str_contains($contents, $marker)) {
    exit(0);
}

$replacement = "<?php\n\n{$marker}\nreturn;\n";

$patched = preg_replace('/^<\?php\s*/', $replacement, $contents, 1, $count);

if ($count !== 1) {
    fwrite(STDERR, "Could not patch {$file}: unexpected file format.\n");
    exit(1);
}

file_put_contents($file, $patched);
