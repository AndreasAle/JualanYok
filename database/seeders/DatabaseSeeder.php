<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PlanSeeder::class,
            PlatformSeeder::class,
            StorefrontTemplateSeeder::class,
        ]);

        // Demo content is guarded so a production deploy never seeds fake
        // stores or the shared demo password.
        if (config('jualanyok.demo.enabled')) {
            $this->call(DemoSeeder::class);
        }
    }
}
