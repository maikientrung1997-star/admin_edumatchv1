<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'cover_image_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('cover_image_path')->nullable()->after('avatar_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'cover_image_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('cover_image_path');
            });
        }
    }
};
