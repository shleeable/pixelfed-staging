<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('media', function (Blueprint $table) {
                $table->index(['processed_at', 'remote_url', 'deleted_at', 'created_at', 'id'], 'media_unoptimized_recent_index');
            });
        } else {
            DB::statement('
                ALTER TABLE `media`
                ADD INDEX `media_unoptimized_recent_index`
                    (`processed_at`, `remote_url`, `deleted_at`, `created_at`, `id`),
                ALGORITHM=INPLACE,
                LOCK=NONE
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex('media_unoptimized_recent_index');
            });
        } else {
            DB::statement('
                ALTER TABLE `media`
                DROP INDEX `media_unoptimized_recent_index`,
                ALGORITHM=INPLACE,
                LOCK=NONE
            ');
        }
    }
};
