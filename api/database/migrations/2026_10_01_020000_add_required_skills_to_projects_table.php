<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Required skills as a jsonb array of normalized strings, rather than a
 * skills reference table with pivots on both projects and
 * freelancer_profiles. `freelancer_skills.skill_name` is already free text,
 * so a reference table would mean normalizing existing freelancer rows too —
 * a much larger change for matching this side doesn't yet need.
 *
 * Nullable with no default: a project published before this column existed,
 * or one whose client listed no skills, reads as NULL rather than being
 * backfilled to an empty array it never asserted.
 *
 * The GIN index is what makes the `?skills[]=` containment filter on
 * ListProjectsQuery usable; without it that filter is a sequential scan.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->jsonb('required_skills')->nullable();
        });

        DB::statement('CREATE INDEX projects_required_skills_gin_index ON projects USING gin (required_skills)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS projects_required_skills_gin_index');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('required_skills');
        });
    }
};
