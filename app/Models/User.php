<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'user_code',
        'role',
        'address',
        'email_address',
        'commuter_type',
        'phone_number',
        'age',
        'username',
        'password',
    ];

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
            'password' => 'hashed',
        ];
    }

    public function travelRecords() {
        return $this->hasMany(TravelRecord::class);
    }

    public function queues() {
        return $this->hasOne(Queue::class);
    }

    public function card() {
        return $this->hasOne(Card::class);
    }

    public function vehicles() {
        return $this->hasMany(Vehicle::class);
    }

        public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $latest = static::max('id') ?? 0;
            $user->user_code = 'USR-' . str_pad($latest + 1, 4, '0', STR_PAD_LEFT);
        });
    }
}
