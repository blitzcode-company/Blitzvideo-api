<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransaccionTable extends Migration
{
    public function up()
    {
        Schema::create('transaccion', function (Blueprint $table) {
            $table->id();
            $table->string('plan');
            $table->string('metodo_de_pago');
            $table->date('fecha_inicio');
            $table->date('fecha_cancelacion')->nullable();
            $table->string('suscripcion_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transaccion');
    }
}
