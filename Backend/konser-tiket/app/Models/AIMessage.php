<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'message',
    ];

    public function conversation()
    {
        return $this->belongsTo(AIConversation::class, 'conversation_id');
    }

    public function feedback()
    {
        return $this->hasOne(AIFeedback::class, 'message_id');
    }
}
