<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'first_name',
        'last_name',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'postal_code',
        'country',
        'phone',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns the address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get full name.
     */
    public function getFullNameAttribute(): string
    {
        $firstName = trim($this->first_name);
        $lastName = trim($this->last_name);
        
        if (empty($firstName) && empty($lastName)) {
            return '';
        }
        
        if (empty($firstName)) {
            return $lastName;
        }
        
        if (empty($lastName)) {
            return $firstName;
        }
        
        return $firstName . ' ' . $lastName;
    }

    /**
     * Get formatted address.
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->province,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}