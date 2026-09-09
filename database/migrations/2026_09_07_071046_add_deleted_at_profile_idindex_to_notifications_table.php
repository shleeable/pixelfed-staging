<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                ALTER TABLE `notifications`
                ADD INDEX `notifications_profile_deleted_id_index`
                    (`profile_id`, `deleted_at`, `id`),
                ALGORITHM=INPLACE,
                LOCK=NONE
            ');
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['profile_id', 'deleted_at', 'id'], 'notifications_profile_deleted_id_index');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                ALTER TABLE `notifications`
                DROP INDEX `notifications_profile_deleted_id_index`,
                ALGORITHM=INPLACE,
                LOCK=NONE
            ');
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_profile_deleted_id_index');
            });
        }
    }
};
