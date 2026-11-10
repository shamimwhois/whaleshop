<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeedingFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tables populated by the core install (non-demo) seeders.
     */
    private const TRACKED_TABLES = [
        'users',
        'roles',
        'permissions',
        'menus',
        'settings',
        'currencies',
        'product_brands',
        'product_categories',
        'taxes',
        'units',
        'products',
        'product_variations',
        'orders',
        'purchases',
        'product_tags',
    ];

    private function counts(): array
    {
        $counts = [];

        foreach (self::TRACKED_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    private function seeders(): array
    {
        $seeders = [];

        foreach (glob(database_path('seeders/*Seeder.php')) as $file) {
            $class = 'Database\\Seeders\\' . basename($file, '.php');

            // Maintenance seeders (e.g. MenuTruncateTableSeeder) intentionally
            // remove rows, so they are not part of the re-runnable seed flow.
            if ($class === \Database\Seeders\DatabaseSeeder::class
                || $class === \Database\Seeders\MenuTruncateTableSeeder::class) {
                continue;
            }

            $seeders[] = $class;
        }

        sort($seeders);

        return $seeders;
    }

    public function test_full_seed_populates_core_data(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $counts = $this->counts();

        $this->assertGreaterThan(0, $counts['users'], 'Expected seeded users.');
        $this->assertGreaterThan(0, $counts['permissions'], 'Expected seeded permissions.');
        $this->assertGreaterThan(0, $counts['roles'], 'Expected seeded roles.');
        $this->assertGreaterThan(0, $counts['menus'], 'Expected seeded menus.');
        $this->assertGreaterThan(0, $counts['settings'], 'Expected seeded settings.');
    }

    public function test_full_seed_is_idempotent(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $before = $this->counts();

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame($before, $this->counts(), 'Re-running the database seeder changed row counts.');
    }

    public function test_every_individual_seeder_can_be_re_run_without_duplicates(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $before = $this->counts();

        foreach ($this->seeders() as $seeder) {
            try {
                $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])
                    ->assertSuccessful();

                $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])
                    ->assertSuccessful();
            } catch (\Throwable $e) {
                $this->fail(sprintf('Seeder %s is not safe to re-run: %s', $seeder, $e->getMessage()));
            }
        }

        $this->assertSame($before, $this->counts(), 'Re-running individual seeders duplicated or removed rows.');
    }

    public function test_demo_catalogue_is_seeded_when_demo_mode_is_enabled(): void
    {
        if (!filter_var(env('DEMO'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('DEMO mode is disabled; demo catalogue is intentionally skipped.');
        }

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertGreaterThan(0, DB::table('products')->count(), 'Expected demo products.');
        $this->assertGreaterThan(0, DB::table('product_variations')->count(), 'Expected demo variations.');
        $this->assertGreaterThan(0, DB::table('orders')->count(), 'Expected demo orders.');
        $this->assertGreaterThan(0, DB::table('purchases')->count(), 'Expected demo purchases.');
    }
}
