<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Crypt, Log};
use Illuminate\Support\Str;
use Filament\Models\Contracts\FilamentUser;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property string|null $profile_photo_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $kennel
 * @property string|null $nerd_name
 * @property string|null $shirt_size
 * @property string|null $short_bus
 * @property string|null $comment
 * @property int|null $invited_by_id
 * @property string|null $phone
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read string $profile_photo_url
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereKennel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNerdName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShirtSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereShortBus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */

class User extends Authenticatable implements FilamentUser
{
    //use HasApiTokens;
    //use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    //use TwoFactorAuthenticatable;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
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

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'shirt_size' => 'MD',
        'short_bus' => 'N',
    ];

    // fake attribute since it's not in the database anymore
    public ?Carbon $rego_paid_at = null;

    public function orders() : HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getQuickLogin(int $expiresInHours = 0) : string
    {
        $user_data = json_encode([
            'hash' => $this->password,
            'id' => $this->id,
        ]);
        if ($expiresInHours > 0) {
            $user_data['expires'] = Carbon::now()->addHours($expiresInHours)->toIso8601String();
        }
        Log::debug("quick login: " . $user_data);
        return Crypt::encryptString($user_data);
    }

    public static function fromQuickLogin(string $quick_login) : ?User
    {
        try {
            $user_data = json_decode(Crypt::decryptString($quick_login), true);
            if (isset($user_data['expires']) && Carbon::parse($user_data['expires'])->isPast()) {
                Log::warning("quick login expired", $user_data);
                return null;
            }
            $user = User::where('id', $user_data['id'])->first();
            if ($user->password === $user_data['hash']) {
                return $user;
            }
        } catch (\Exception $e) {
            Log::error("error processing quick login: " . $e->getMessage());
        }
        return null;
    }

    public static function add(string $email, string $name) : User
    {
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $actual_password = Str::random(40);
        $user->password = $actual_password;
        $user->save();
        return $user;
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->id === 9;
    }
}
