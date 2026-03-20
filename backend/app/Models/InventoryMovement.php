<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'reason',
        'created_by',
    ];

    /**
     * Movement type constants.
     */
    const TYPE_PURCHASE = 'purchase';
    const TYPE_RESTOCK = 'restock';
    const TYPE_SALE = 'sale';
    const TYPE_RETURN = 'return';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_DAMAGE = 'damage';
    const TYPE_RESERVATION = 'reservation';
    const TYPE_RELEASE = 'release';
    const TYPE_PURCHASE_ORDER = 'purchase_order';

    /**
     * Get the product for this movement.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who made this movement.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}