<?php

use App\Support\LegalContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (LegalContent::pages() as $page) {
            DB::table('static_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                $page + ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        // Legal content is intentionally retained on rollback. Removing public
        // policies would make a rollback less safe than leaving this copy live.
    }
};
