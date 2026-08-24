<?php

use Database\Seeders\IpaymuVerificationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Workspace review tidak mencemari database developer maupun test.
        // Database hosting memakai APP_ENV=production dan akan diprovisikan.
        if (! app()->environment('production')) {
            return;
        }

        app(IpaymuVerificationSeeder::class)->run();
    }

    public function down(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        app(IpaymuVerificationSeeder::class)->remove();
    }
};
