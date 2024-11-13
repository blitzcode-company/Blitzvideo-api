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
            $table->enum('referencia_tipo', [
                'new_video',
                'blocked_video',
                'unblocked_video',
                'blocked_user',
                'unblocked_user',
                'new_comment',
                'new_reply',
                'blocked_comment'
            ])->nullable();
            $table->timestamps();
        });
        
    }
    public function down()
    {
        Schema::dropIfExists('notificacion');
    }
}
