<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'email',
        'password',
        'external_user_id',
        'work_capacity_person_days',
        'last_read_release_version',
    ];

    public const DEFAULT_MONTHLY_PERSON_DAYS = 20.0;
    public const BUSINESS_HIDDEN_NAMES = [
        'Codex UI Check',
        'Codex Session User',
    ];
    public const BUSINESS_HIDDEN_EMAILS = [
        'codex-ui-check@example.com',
        'codex-session@example.com',
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
            'work_capacity_person_days' => 'float',
            'last_read_release_version' => 'string',
        ];
    }

    public function resolveWorkCapacityPersonDays(?float $fallback = null): float
    {
        $resolvedFallback = $fallback ?? (float) config('app.person_days_per_person_month', self::DEFAULT_MONTHLY_PERSON_DAYS);

        if ($this->work_capacity_person_days === null) {
            return round(max(0, $resolvedFallback), 1);
        }

        return round(max(0, (float) $this->work_capacity_person_days), 1);
    }

    public function scopeVisibleForBusiness(Builder $query): Builder
    {
        return $query
            ->whereNotIn('name', self::BUSINESS_HIDDEN_NAMES)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('email')
                    ->orWhereNotIn('email', self::BUSINESS_HIDDEN_EMAILS);
            });
    }

    public static function isHiddenFromBusiness(?string $name, ?string $email = null): bool
    {
        $normalizedName = trim((string) $name);
        $normalizedEmail = strtolower(trim((string) $email));

        return in_array($normalizedName, self::BUSINESS_HIDDEN_NAMES, true)
            || ($normalizedEmail !== '' && in_array($normalizedEmail, self::BUSINESS_HIDDEN_EMAILS, true));
    }
}
