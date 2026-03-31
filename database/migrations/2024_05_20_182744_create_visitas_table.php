<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVisitasTable extends Migration
{
    public function up()
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('segundos_vistos')->default(0)->after('video_id');
            $table->unsignedInteger('duracion_video')->nullable()->after('segundos_vistos');
            $table->boolean('view_valida')->default(false)->after('duracion_video');
            $table->boolean('completado')->default(false)->after('view_valida');
            $table->timestamp('ultimo_heartbeat')->nullable()->after('completado');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('visitas');
    }
}
