<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloodRequest extends Model
{
    use HasFactory;

    protected $table = 'blood_requests';

    protected $fillable = [
        'requested_by',
        'patient_name',
        'blood_group',
        'units_required',
        'hospital_name',
        'hospital_address',
        'city',
        'district',
        'location',
        'contact_number',
        'contact_person_name',
        'required_by_date',
        'urgency_level',
        'additional_notes',
        'status',
        'verified',
        'accepted_by',
        'fulfilled_by'
    ];

    protected function casts(): array
    {
        return [
            'required_by_date' => 'datetime',
            'verified' => 'boolean',
            'units_required' => 'integer',
            'accepted_by' => 'integer',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function fulfiller()
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function accepter()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * Find blood request by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $request = self::with(['requester', 'accepter'])->find($id);
        if ($request) {
            $arr = $request->toArray();
            $arr['requester_name'] = $request->requester->primary_name ?? null;
            $arr['requester_email'] = $request->requester->email ?? null;
            $arr['accepter_name'] = $request->accepter->primary_name ?? null;
            $arr['accepter_email'] = $request->accepter->email ?? null;
            return $arr;
        }
        return null;
    }

    /**
     * Get all requests matching the given filters.
     *
     * @param array $filters
     * @return array
     */
    public static function getAll(array $filters): array
    {
        $query = self::with(['requester', 'accepter']);

        if (!empty($filters['bloodGroup'])) {
            $query->where('blood_group', $filters['bloodGroup']);
        }

        if (!empty($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (!empty($filters['urgencyLevel'])) {
            $query->where('urgency_level', $filters['urgencyLevel']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['verified']) && $filters['verified'] !== '') {
            $query->where('verified', filter_var($filters['verified'], FILTER_VALIDATE_BOOLEAN));
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return $requests->map(function ($req) {
            $arr = $req->toArray();
            $arr['requester_name'] = $req->requester->primary_name ?? null;
            $arr['requester_email'] = $req->requester->email ?? null;
            $arr['requester_picture'] = $req->requester->profile_picture ?? null;
            $arr['accepter_name'] = $req->accepter->primary_name ?? null;
            $arr['accepter_email'] = $req->accepter->email ?? null;
            return $arr;
        })->toArray();
    }

    /**
     * Mark a blood request as fulfilled.
     *
     * @param int $id
     * @param int $fulfilledBy
     * @return bool
     */
    public static function fulfill(int $id, int $fulfilledBy): bool
    {
        $request = self::find($id);
        if ($request) {
            $request->status = 'Fulfilled';
            $request->fulfilled_by = $fulfilledBy;
            return $request->save();
        }
        return false;
    }

    /**
     * Verify a blood request.
     *
     * @param int $id
     * @return bool
     */
    public static function verify(int $id): bool
    {
        $request = self::find($id);
        if ($request) {
            $request->verified = true;
            return $request->save();
        }
        return false;
    }

    /**
     * Delete a blood request.
     *
     * @param int $id
     * @return bool
     */
    public static function deleteRequest(int $id): bool
    {
        $request = self::find($id);
        if ($request) {
            return $request->delete();
        }
        return false;
    }
}
