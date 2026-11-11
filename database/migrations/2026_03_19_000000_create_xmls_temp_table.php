<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xmls_temp', function (Blueprint $table) {
            $table->id();
            $table->longText('xml_content')->comment('Contenido XML DIAN');
            $table->longText('pdf_content')->nullable()->comment('Contenido PDF en base64');
            $table->string('email_source')->nullable()->comment('Origen del email');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xmls_temp');
    }
};
