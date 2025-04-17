<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('emp_id')->after('email')->nullable();
            $table->date('d_o_j')->after('emp_id')->nullable();
            $table->string('location')->after('d_o_j')->nullable();
            $table->string('status')->after('location')->default('PROBATION')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['emp_id','d_o_j','location','status']);
        });
    }
};
