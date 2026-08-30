<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
        'password',
        'type',
        'last_feed_viewed_at',
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
            'last_feed_viewed_at' => 'datetime',
        ];
    }

    public function userStatus()
    {
        return $this->hasOne(UserStatus::class);
    }

    public function isSuspended(): bool
    {
        return $this->userStatus?->status === 'suspended';
    }

    /**
     * True once a commuter has permanently deleted their own account via
     * UserService::deleteOwnAccount(). Their PII has already been wiped;
     * this flag exists purely to block login/session access, since the
     * row is intentionally kept (not soft-deleted) so historical records
     * belonging to other users can still resolve this user's relation
     * and display it as "Deleted User".
     */
    public function isDeleted(): bool
    {
        return (bool) $this->userStatus?->is_deleted;
    }

    public function postInterests() {
        return $this->hasMany(PostInterest::class);
    }

    public function posts() {
        return $this->hasMany(Post::class);
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

    public function cardReports()
    {
        return $this->hasMany(CardReport::class);
    }

    public function approvedCardReports()
    {
        return $this->hasMany(CardReport::class, 'approved_by');
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

        static::created(function (User $user) {
            $user->userStatus()->create(['status' => 'active']);
        });
    }
}