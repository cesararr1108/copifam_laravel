<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique()->comment('Código interno Copifam');
            $table->string('ean', 500)->nullable()->comment('EAN(s) separados por espacio');
            $table->string('descripcion', 500)->nullable();
            $table->decimal('precio_copifam', 14, 2)->nullable()->comment('Precio lista 1 en v_lista_precios_iva');
            $table->decimal('precio_rebaja',  14, 2)->nullable()->comment('La Rebaja Virtual');
            $table->decimal('precio_cruz',    14, 2)->nullable()->comment('Cruz Verde');
            $table->decimal('precio_pasteur', 14, 2)->nullable()->comment('Farmacias Pasteur');
            $table->timestamp('scraped_at')->nullable()->comment('Última vez que se actualizaron los precios externos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping');
    }
};
