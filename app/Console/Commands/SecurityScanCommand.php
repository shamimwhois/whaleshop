<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class SecurityScanCommand extends Command
{
    protected $signature = 'security:scan
                            {--strict : Exit non-zero when findings exist (for CI)}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Static source scan for dangerous patterns (env() misuse, rand() tokens, raw SQL, shell exec, dynamic class resolution)';

    /** Patterns that are legitimate inside config files. */
    private const CONFIG_ONLY_OK = [
        'env() call in application code',
    ];

    public function handle(): int
    {
        $paths = (array) config('security.scan.paths', ['app', 'config', 'routes', 'bootstrap']);
        $patterns = (array) config('security.scan.patterns', []);
        $excluded = array_map(
            static fn (string $p): string => str_replace('\\', '/', $p),
            (array) config('security.scan.exclude', [])
        );
        $findings = [];

        foreach ($paths as $relative) {
            $dir = base_path($relative);
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['php', 'js', 'vue'], true)) {
                    continue;
                }

                $relativePath = str_replace('\\', '/', ltrim(str_replace(base_path(), '', $file->getPathname()), '/\\'));
                if (in_array($relativePath, $excluded, true)) {
                    continue;
                }
                $isConfig = str_starts_with($relativePath, 'config/');
                $lines = @file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [];

                foreach ($lines as $index => $line) {
                    foreach ($patterns as $label => $regex) {
                        if ($isConfig && in_array($label, self::CONFIG_ONLY_OK, true)) {
                            continue;
                        }
                        if (@preg_match('#'.str_replace('#', '\#', (string) $regex).'#i', $line) === 1) {
                            $findings[] = [
                                'file'    => $relativePath,
                                'line'    => $index + 1,
                                'pattern' => $label,
                                'code'    => mb_substr(trim($line), 0, 120),
                            ];
                        }
                    }
                }
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(['count' => count($findings), 'findings' => $findings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $this->info('Static security scan - '.count($findings).' potential finding(s)');
            if ($findings !== []) {
                $this->table(
                    ['File', 'Line', 'Pattern', 'Code'],
                    array_map(static fn (array $f): array => [$f['file'], $f['line'], $f['pattern'], $f['code']], $findings)
                );
                $this->newLine();
                $this->warn('Review each hit manually: some are legitimate (e.g. config files).');
            }
        }

        return ($this->option('strict') && $findings !== []) ? self::FAILURE : self::SUCCESS;
    }
}
