<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $table = 'complaints';

    // Disable standard Laravel timestamps because the table has only created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'reporter_id',
        'target_id',
        'reason',
        'status'
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /**
     * Create a new complaint.
     *
     * @param array $data
     * @return int Inserted ID
     */
    public static function createComplaint(array $data): int
    {
        $complaint = self::create([
            'reporter_id' => $data['reporter_id'],
            'target_id' => $data['target_id'],
            'reason' => $data['reason'],
            'status' => 'Pending'
        ]);
        return $complaint->id;
    }

    /**
     * Find complaint by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $complaint = self::with(['reporter', 'target'])->find($id);
        if ($complaint) {
            $arr = $complaint->toArray();
            $arr['reporter_name'] = $complaint->reporter->primary_name ?? null;
            $arr['target_name'] = $complaint->target->primary_name ?? null;
            return $arr;
        }
        return null;
    }

    /**
     * Fetch all complaints in the system.
     *
     * @return array
     */
    public static function getAll(): array
    {
        $complaints = self::with(['reporter', 'target'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $complaints->map(function ($complaint) {
            $arr = $complaint->toArray();
            $arr['reporter_name'] = $complaint->reporter->primary_name ?? null;
            $arr['target_name'] = $complaint->target->primary_name ?? null;
            return $arr;
        })->toArray();
    }

    /**
     * Resolve a complaint.
     *
     * @param int $id
     * @return bool
     */
    public static function resolve(int $id): bool
    {
        $complaint = self::find($id);
        if ($complaint) {
            $complaint->status = 'Resolved';
            return $complaint->save();
        }
        return false;
    }
}
