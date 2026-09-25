<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'capacity',
        'google_maps_link',
        'image',
    ];

    protected $appends = [
        'google_maps_embed_url',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    public function getGoogleMapsEmbedUrlAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return "https://maps.google.com/maps?q={$this->latitude},{$this->longitude}&z=16&output=embed";
        }
        return null;
    }

    public function getGoogleMapsLinkAttribute()
    {
        return $this->google_maps_link ?? "https://maps.google.com/?q={$this->address}, {$this->city}, {$this->state}, {$this->country}";
    }
}
