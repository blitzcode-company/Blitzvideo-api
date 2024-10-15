<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanPremiumsTable extends Migration
{
    public function up()
    {
        Schema::create('plan_premium', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('metodo_de_pago');
            $table->date('fecha_pago');
            $table->date('fecha_cancelacion')->nullable();
            $table->string('suscripcion_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('plan_premium');
    }
}
