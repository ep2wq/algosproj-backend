<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // The first import only. After that the database is the source of truth (content is
        // edited in the admin area), so a later run must not touch it. `php artisan content:sync`
        // adds new seed content and empty Arabic fields without overwriting anything.
        if (Module::exists()) {
            $this->command?->warn('Content not imported: modules already exist. Edit content in the admin area.');

            return;
        }

        // The same import as content:sync (ContentImport), English and Arabic.
        DB::transaction(fn () => (new ContentImport)->run());
    }
}
