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
        Schema::create('student_id_prefixes', function (Blueprint $table) {
            $table->id();
            $table->char('prefix', 3)->unique();
            $table->unsignedSmallInteger('enrollment_year');
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE student_id_prefixes ADD CONSTRAINT chk_student_id_prefixes_format CHECK (prefix REGEXP '^[0-9]{2}0$')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_id_prefixes');
    }
};

