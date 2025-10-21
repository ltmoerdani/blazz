<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteToTeamsAndWorkspaces extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add soft delete columns to 'teams' table
        Schema::table('teams', function (Blueprint $table) {
            // Add deleted_at column
            $table->timestamp('deleted_at')->nullable()->after('created_by');
            // Add deleted_by column
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
        });

        // Add soft delete columns to 'workspaces' table
        Schema::table('workspaces', function (Blueprint $table) {
            // Add deleted_at column
            $table->timestamp('deleted_at')->nullable()->after('created_by');
            // Add deleted_by column
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove soft delete columns from 'teams' table
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_by');
            $table->dropColumn('deleted_at');
        });

        // Remove soft delete columns from 'workspaces' table
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn('deleted_by');
            $table->dropColumn('deleted_at');
        });
    }
}
