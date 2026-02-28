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
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('subject', 150)->nullable();
            $table->text('message');
            $table->enum('inquiry_status', ['new', 'read', 'resolved', 'closed'])->default('new');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'inquiry_status', 'created_at']);
            $table->index(['sender_user_id', 'created_at']);
            $table->index(['listing_id', 'created_at']);
        });

        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_sender_recipient_diff CHECK (sender_user_id <> recipient_user_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};

