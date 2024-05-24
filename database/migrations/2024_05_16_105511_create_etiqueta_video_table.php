<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtiquetaVideoTable extends Migration
{
    public function up()
    {
        Schema::create('etiqueta_video', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etiqueta_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('etiqueta_video');
    }
}