<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCanalStreamTable extends Migration
{
    public function up()
    {
        Schema::create('canal_stream', function (Blueprint $table) {
            $table->foreignId('canal_id')->constrained()->onDelete('cascade');
            $table->foreignId('stream_id')->constrained()->onDelete('cascade');
            $table->primary(['canal_id', 'stream_id']); 
        });
    }
    public function down()
    {
        Schema::dropIfExists('canal_stream');
    }
}