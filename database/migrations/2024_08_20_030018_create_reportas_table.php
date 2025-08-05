<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportasTable extends Migration
{
    public function up()
    {
        Schema::create('reporta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->text('detalle')->nullable();
            $table->boolean('contenido_inapropiado')->default(false);
            $table->boolean('spam')->default(false);
            $table->boolean('contenido_enganoso')->default(false);
            $table->boolean('violacion_derechos_autor')->default(false);
            $table->boolean('incitacion_al_odio')->default(false);
            $table->boolean('violencia_grafica')->default(false);
            $table->boolean('otros')->default(false);
            $table->enum('estado', ['pendiente', 'resuelto'])->default('pendiente');
            $table->timestamp('revisado_en')->nullable(); 
            $table->softDeletes(); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reporta');
    }
}
