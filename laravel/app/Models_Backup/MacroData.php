<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacroData extends Model
{
    use HasFactory;

    protected $table = 'macro_data';
    protected $fillable = ['date','symbol','value','source'];
    protected $casts = ['date' => 'date'];
}
