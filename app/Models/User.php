<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
 * @property int|null $plot_id
 * @property bool $active
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        'plot_id',
        'active',
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
            'role' => Role::class,
            'plot_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * The plot a tenant belongs to. Board members and treasurers have none.
     *
     * @return BelongsTo<Plot, $this>
     */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    /**
     * Readings this user recorded or reviewed, bills this user issued. Users
     * with such a history stay in the system (locked), so the history keeps their name.
     */
    public function hasHistory(): bool
    {
        return Reading::query()
            ->where('recorded_by', $this->getKey())
            ->orWhere('flag_reviewed_by', $this->getKey())
            ->exists()
            || InvoiceDispatch::query()->where('sent_by', $this->getKey())->exists();
    }
}
