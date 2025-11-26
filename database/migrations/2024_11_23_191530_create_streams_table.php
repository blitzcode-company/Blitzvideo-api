<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStreamsTable extends Migration
{

    public function up()
    {
        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->unique()->constrained()->onDelete('cascade');
            $table->dateTime('stream_programado')->nullable();
            $table->unsignedBigInteger('max_viewers')->default(0);
            $table->unsignedBigInteger('total_viewers')->default(0);
            $table->boolean('activo')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('streams');
    }
}