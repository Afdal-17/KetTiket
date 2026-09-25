<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIFeedback extends Model
{
    use HasFactory;

    protected $table = 'ai_feedback';

    protected $fillable = [
        'message_id',
        'rating',
        'comment',
    ];

    public function message()
    {
        return $this->belongsTo(AIMessage::class, 'message_id');
    }
}
