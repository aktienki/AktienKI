<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisReport extends Model
{
    protected $fillable = [
        'user_id','instrument_id','prediction_id','report_type','symbol','signal_from','signal_to','transition_at','prediction_at','model','status','report_text','report_data','pdf_path','input_tokens','output_tokens','estimated_cost_usd',
    ];

    protected $casts = ['transition_at' => 'datetime', 'prediction_at' => 'datetime', 'report_data' => 'array', 'estimated_cost_usd' => 'decimal:6'];
}
