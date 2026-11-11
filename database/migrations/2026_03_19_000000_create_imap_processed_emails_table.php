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
        Schema::create('imap_processed_emails', function (Blueprint $table) {
            $table->id();
            $table->integer('email_id')->unique()->comment('ID del correo en IMAP');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imap_processed_emails');
    }
};
