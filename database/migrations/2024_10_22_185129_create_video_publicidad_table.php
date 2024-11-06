<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoPublicidadTable extends Migration
{
    public function up()
    {
        Schema::create('video_publicidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->foreignId('publicidad_id')->constrained('publicidad')->onDelete('cascade');
            $table->integer('vistos')->default(0);
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('video_publicidad');
    }
}
