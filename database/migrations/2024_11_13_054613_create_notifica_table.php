<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificaTable extends Migration
{
    public function up()
    {
        Schema::create('notifica', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('notificacion_id')->constrained('notificacion')->onDelete('cascade');
            $table->boolean('leido')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifica');
    }
}
