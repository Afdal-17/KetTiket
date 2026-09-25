<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id',
        'venue_id',
        'location_name',
        'has_seating',
        'title',
        'description',
        'image',
        'start_date',
        'end_date',
        'status',
        'latitude',
        'longitude',
        'google_maps_link',
        'ticket_types_data',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'ticket_types_data' => 'array',
        'has_seating' => 'boolean',
    ];

    protected $appends = [
        'google_maps_embed_url',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getGoogleMapsEmbedUrlAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return "https://maps.google.com/maps?q={$this->latitude},{$this->longitude}&z=16&output=embed";
        }
        return null;
    }

    public function getVenueGoogleMapsLinkAttribute()
    {
        if ($this->venue) {
            return $this->venue->google_maps_link;
        }

        return $this->google_maps_link;
    }
}
