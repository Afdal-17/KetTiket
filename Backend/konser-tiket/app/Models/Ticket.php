<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $casts = [
        'scan_requested_at' => 'datetime',
    ];

    protected $fillable = [
        'order_detail_id',
        'barcode',
        'qr_code',
        'status',
        'scan_requested_at',
        'scan_token',
    ];

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class);
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class);
    }
}
