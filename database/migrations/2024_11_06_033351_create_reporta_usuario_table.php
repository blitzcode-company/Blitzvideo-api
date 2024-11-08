<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportaUsuarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reporta_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_reportante')->constrained('users')->onDelete('cascade');
            $table->foreignId('id_reportado')->constrained('users')->onDelete('cascade');
            $table->boolean('ciberacoso')->default(false);
            $table->boolean('privacidad')->default(false);
            $table->boolean('suplantacion_identidad')->default(false);
            $table->boolean('amenazas')->default(false);
            $table->boolean('incitacion_odio')->default(false);
            $table->boolean('otros')->default(false);
            $table->text('detalle')->nullable();
            $table->enum('estado', ['pendiente', 'resuelto'])->default('pendiente');
            $table->timestamp('revisado_en')->nullable(); 
            $table->softDeletes(); 
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reporta_usuario');
    }
}
