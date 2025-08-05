<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeGustaTable extends Migration
{
    public function up()
    {
        Schema::create('me_gusta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('comentario_id')->constrained('comentarios')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['usuario_id', 'comentario_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('me_gusta');
    }
}
