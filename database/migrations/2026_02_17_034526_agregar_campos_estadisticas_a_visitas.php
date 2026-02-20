<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AgregarCamposEstadisticasAVisitas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->unsignedInteger('segundos_vistos')->default(0)->after('video_id');
            $table->unsignedInteger('duracion_video')->nullable()->after('segundos_vistos');
            $table->boolean('view_valida')->default(false)->after('duracion_video');
            $table->boolean('completado')->default(false)->after('view_valida');
            $table->timestamp('ultimo_heartbeat')->nullable()->after('completado');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('visitas', function (Blueprint $table) {
            //
        });
    }
}
