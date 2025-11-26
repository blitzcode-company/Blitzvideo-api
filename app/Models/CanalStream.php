<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CanalStream extends Pivot
{
    protected $table      = 'canal_stream';
    protected $primaryKey = ['canal_id', 'stream_id'];
    public $incrementing  = false;
}
