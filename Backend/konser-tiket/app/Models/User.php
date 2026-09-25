<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'date_of_birth',
        'gender',
        'avatar',
        'social_provider',
        'social_id',
        'bio',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
        'preferred_language',
        'timezone',
        'notification_preferences',
        'last_login_at',
        'login_count',
        'profile_visibility',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => 'boolean',
        'date_of_birth' => 'date',
        'last_login_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    public function organizer()
    {
        return $this->hasOne(Organizer::class);
    }

    public function adminProfile()
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function scannerProfile()
    {
        return $this->hasOne(ScannerProfile::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'scanner_id');
    }

    public function aiConversations()
    {
        return $this->hasMany(AIConversation::class);
    }

    public function aiLogs()
    {
        return $this->hasMany(AILog::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
