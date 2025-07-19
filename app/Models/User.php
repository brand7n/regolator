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
use Carbon\Carbon;
use Illuminate\Support\Facades\{Crypt, Log};
use Illuminate\Support\Str;

class User extends Authenticatable
{
    //use HasApiTokens;
    use HasFactory;
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
     * @var array<int, string>
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
     * @var array<int, string>
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
     * @var array<int, string>
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
     * @var array
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
}
