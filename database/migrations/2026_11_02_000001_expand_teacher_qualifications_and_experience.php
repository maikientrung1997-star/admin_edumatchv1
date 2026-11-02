<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->longText('headline')->nullable()->change();
            if (! Schema::hasColumn('teacher_profiles', 'experience')) {
                $table->longText('experience')->nullable()->after('bio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->string('headline', 200)->nullable()->change();
            if (Schema::hasColumn('teacher_profiles', 'experience')) {
                $table->dropColumn('experience');
            }
        });
    }
};
