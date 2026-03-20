<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'search_logs';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'query',
        'results_count',
        'clicked_product_id',
        'searched_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'results_count' => 'integer',
        'searched_at' => 'datetime',
    ];

    /**
     * Get the user who performed the search.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that was clicked.
     */
    public function clickedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'clicked_product_id');
    }
}
