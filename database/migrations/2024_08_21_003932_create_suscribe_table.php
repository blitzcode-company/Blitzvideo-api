<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuscribeTable extends Migration
{
    public function up()
    {
        Schema::create('suscribe', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('canal_id');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('canal_id')->references('id')->on('canals')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('suscribe');
    }
}
