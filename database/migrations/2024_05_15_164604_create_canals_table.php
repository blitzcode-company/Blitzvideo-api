<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCanalsTable extends Migration
{
    public function up()
    {
        Schema::create('canals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('portada')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('canals');
    }
}
