<?php

use App\Services\StaffDirectory;
use Illuminate\Database\Migrations\Migration;

// Writes the staff title presets again so the new site.edit permission exists after `php artisan migrate`.
return new class extends Migration
{
    public function up(): void
    {
        StaffDirectory::sync();
    }

    public function down(): void
    {
    }
};
