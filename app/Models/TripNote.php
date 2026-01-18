<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Encore\Admin\Auth\Database\Administrator;

class TripNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'user_id',
        'note',
        'note_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the trip that owns the note
     */
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the user who created the note
     */
    public function user()
    {
        return $this->belongsTo(Administrator::class, 'user_id');
    }

    /**
     * Get the author name
     */
    public function getAuthorNameAttribute()
    {
        return $this->user ? $this->user->name : 'Unknown';
    }

    /**
     * Check if the note was created by a driver
     */
    public function isDriverNote()
    {
        return $this->note_type === 'driver';
    }

    /**
     * Check if the note was created by a passenger
     */
    public function isPassengerNote()
    {
        return $this->note_type === 'passenger';
    }
}
