<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComentariosTable extends Migration
{
    public function up()
    {
        Schema::create('comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('video_id')->constrained('videos')->onDelete('cascade');
            $table->unsignedBigInteger('respuesta_id')->nullable();
            $table->text('mensaje');
            $table->boolean('bloqueado')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('respuesta_id')->references('id')->on('comentarios')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comentarios');
    }
}
