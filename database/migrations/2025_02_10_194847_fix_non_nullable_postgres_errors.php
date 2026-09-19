<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * database/migrations/2018_08_12_042648_update_status_table_change_caption_to_text.php
     * calls `->change()` without `->nullable()`, which restates the column definition and
     * silently makes `statuses.caption` / `statuses.rendered` NOT NULL. Upstream still
     * writes null into `caption` (DirectMessageController), which then fails on PostgreSQL,
     * where inserting null into a NOT NULL column is an error rather than a coercion.
     */
    public function up(): void
    {
        Schema::table('statuses', function ($table) {
            $table->text('caption')->nullable()->change();
            $table->text('rendered')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
