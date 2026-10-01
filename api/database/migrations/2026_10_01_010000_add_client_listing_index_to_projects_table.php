<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports ListMyProjectsQuery, which filters on client_id (always), status
 * (optionally) and orders by created_at. `projects` has no index beyond its
 * primary key and the FK indexes Postgres creates for client_id/category_id,
 * so a client's project list would otherwise fall back to sorting every row
 * matching the client.
 *
 * Column order matches the query's selectivity: equality on client_id first,
 * then equality on status, then the ordering column.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['client_id', 'status', 'created_at'], 'projects_client_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_client_status_created_at_index');
        });
    }
};
