<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionTable extends Migration
{
    public function up()
    {
        Schema::create('notificacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('mensaje');
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_tipo', 50)->nullable();
            $table->timestamps();
        });
        
    }
    public function down()
    {
        Schema::dropIfExists('notificacion');
    }
}
