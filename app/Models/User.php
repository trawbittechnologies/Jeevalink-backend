<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'password',
        'full_name',
        'email',
        'mobile',
        'password_hash',
        'role',
        'blood_group',
        'city',
        'district',
        'weight',
        'date_of_birth',
        'last_donated_date',
        'profile_picture',
        'available_for_donation',
        'reward_points',
        'lives_saved',
        'total_donations',
        'status',
        'expo_push_token',
        'pincode',
        'full_address',
        'dob',
        'id_proof_front',
        'id_proof_back',
        'is_verified',
        'fcm_token',
        'latitude',
        'longitude',
        'notification_enabled',
        'eligibility_status',
        'eligibility_checked_at',
        'sex',
    ];

    /**
     * Auto-populate the base `name` column from `full_name` on SQLite
     * (the initial Laravel migration requires name NOT NULL).
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user) {
            $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
            if ($isSqlite) {
                if (empty($user->name)) {
                    $user->name = $user->full_name ?? 'User';
                }
                if (empty($user->password) && !empty($user->password_hash)) {
                    $user->password = $user->password_hash;
                }
            }
        });

        static::updating(function (self $user) {
            $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
            if ($isSqlite) {
                if (empty($user->name) && !empty($user->full_name)) {
                    $user->name = $user->full_name;
                }
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the password name for authentication.
     */
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'last_donated_date' => 'date:Y-m-d',
            'available_for_donation' => 'boolean',
            'reward_points' => 'integer',
            'lives_saved' => 'integer',
            'total_donations' => 'integer',
            'dob' => 'date:Y-m-d',
            'is_verified' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'notification_enabled' => 'boolean',
            'eligibility_checked_at' => 'datetime',
        ];
    }

    /**
     * Find a user by email.
     *
     * @param string $email
     * @return array|null
     */
    public static function findByEmail(string $email): ?array
    {
        $user = self::where('email', $email)->first();
        return $user ? $user->toArray() : null;
    }

    /**
     * Find a user by mobile number.
     *
     * @param string $mobile
     * @return array|null
     */
    public static function findByMobile(string $mobile): ?array
    {
        $user = self::where('mobile', $mobile)->first();
        return $user ? $user->toArray() : null;
    }

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $user = self::find($id);
        return $user ? $user->toArray() : null;
    }

    /**
     * Update user profile data.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function updateProfile(int $id, array $data): bool
    {
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        
        $allowedFields = [
            'full_name', 'blood_group', 'city', 'district', 
            'weight', 'dob', 'last_donated_date', 
            'profile_picture', 'email', 'mobile', 'sex', 'pincode'
        ];

        $updates = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }
        
        // Map frontend 'address' to database 'full_address'
        if (array_key_exists('address', $data)) {
            $updates['full_address'] = $data['address'];
        }
        if (array_key_exists('full_address', $data)) {
            $updates['full_address'] = $data['full_address'];
        }

        if (empty($updates)) {
            return false;
        }

        return $user->update($updates);
    }

    /**
     * Toggle availability for donation.
     *
     * @param int $id
     * @return bool
     */
    public static function toggleAvailability(int $id): bool
    {
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $user->available_for_donation = !$user->available_for_donation;
        return $user->save();
    }

    /**
     * Update Expo push notification token.
     *
     * @param int $id
     * @param string|null $token
     * @return bool
     */
    public static function updatePushToken(int $id, ?string $token): bool
    {
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $user->fcm_token = $token;
        return $user->save();
    }

    /**
     * Update user status (Active, Suspended, etc.).
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public static function updateStatus(int $id, string $status): bool
    {
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $user->status = $status;
        return $user->save();
    }

    /**
     * Increment a numeric statistic column.
     *
     * @param int $id
     * @param string $field
     * @param int $amount
     * @return bool
     */
    public static function incrementStats(int $id, string $field, int $amount = 1): bool
    {
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        $allowed = ['reward_points', 'lives_saved', 'total_donations'];
        if (!in_array($field, $allowed, true)) {
            return false;
        }
        $user->increment($field, $amount);
        return true;
    }

    /**
     * Search compatible donors.
     *
     * @param array $filters
     * @param int|null $excludeId
     * @return array
     */
    public static function searchDonors(array $filters, ?int $excludeId = null): array
    {
        $query = self::where('available_for_donation', true)
            ->where('status', 'Active');

        if (!empty($filters['bloodGroup'])) {
            $query->where('blood_group', $filters['bloodGroup']);
        }

        if (!empty($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get()->toArray();
    }

    /**
     * Fetch nearby available matching donors.
     */
    public static function getNearbyDonors($latitude, $longitude, $radius, $bloodGroup, $district = null, $excludeId = null)
    {
        $query = self::where('available_for_donation', true)
            ->where('status', 'Active')
            ->where('notification_enabled', true)
            ->whereNotNull('fcm_token');

        if ($bloodGroup && $bloodGroup !== 'N/A') {
            $query->where('blood_group', $bloodGroup);
        }

        if ($district) {
            $query->where('district', $district);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';

        if ($latitude && $longitude && !$isSqlite) {
            $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
            $query->selectRaw("*, $haversine AS distance", [$latitude, $longitude, $latitude]);
            if ($radius) {
                $query->havingRaw("$haversine <= ?", [$latitude, $longitude, $latitude, $radius]);
            }
            $query->orderBy('distance', 'asc');
            return $query->get();
        } else {
            $donors = $query->get();
            if ($latitude && $longitude) {
                $donors = $donors->map(function ($donor) use ($latitude, $longitude) {
                    if ($donor->latitude && $donor->longitude) {
                        $theta = $longitude - $donor->longitude;
                        $dist = sin(deg2rad($latitude)) * sin(deg2rad($donor->latitude)) +  cos(deg2rad($latitude)) * cos(deg2rad($donor->latitude)) * cos(deg2rad($theta));
                        $dist = acos(min(max($dist, -1.0), 1.0));
                        $dist = rad2deg($dist);
                        $miles = $dist * 60 * 1.1515;
                        $donor->distance = round($miles * 1.609344, 2);
                    } else {
                        $donor->distance = round(rand(10, 50) / 10, 1);
                    }
                    return $donor;
                });

                if ($radius) {
                    $donors = $donors->filter(function ($d) use ($radius) {
                        return $d->distance <= $radius;
                    });
                }
                return $donors->sortBy('distance')->values();
            }
            return $donors;
        }
    }

    /**
     * Fetch all users in the system (Admin dashboard).
     *
     * @return array
     */
    public static function getAll(): array
    {
        return self::orderBy('created_at', 'desc')->get()->toArray();
    }
}
