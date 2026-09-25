<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'scanner_id',
        'scan_time',
    ];

    protected $casts = [
        'scan_time' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanner_id');
    }
}
