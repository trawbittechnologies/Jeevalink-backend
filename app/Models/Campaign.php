<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'campaigns';

    protected $fillable = [
        'user_id',
        'author_name',
        'author_role',
        'title',
        'category',
        'description',
        'venue',
        'event_date',
        'event_time',
        'organizer_name',
        'contact_phone',
        'image_url',
        'district',
        'block',
        'likes_count',
        'shares_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'shares_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
