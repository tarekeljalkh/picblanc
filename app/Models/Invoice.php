<?php

namespace App\Models;

use App\Enums\ProductType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'category_id',
        'total_discount',
        'total_amount',
        'amount_per_day',
        'paid',
        'payment_method',
        'status',
        'rental_start_date',
        'rental_end_date',
        'days',
        'note',
    ];

    protected $casts = [
        'rental_start_date' => 'datetime',
        'rental_end_date' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }


    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function additionalItems()
    {
        return $this->hasMany(AdditionalItem::class, 'invoice_id');
    }

    public function returnDetails()
    {
        return $this->hasManyThrough(
            ReturnDetail::class,
            InvoiceItem::class,
            'invoice_id',     // Foreign key on InvoiceItem
            'invoice_item_id' // Foreign key on ReturnDetail
        );
    }

    // Accessors and Calculations

    // Calculate the subtotal (sum of all items' total_price)
    public function getSubtotalAttribute()
    {
        return $this->items->sum('total_price');
    }

    // Calculate the total amount of returns
    public function getReturnedCostAttribute()
    {
        return $this->returnDetails()->sum('cost');
    }

    // Calculate the total amount of added items
    public function getAddedCostAttribute()
    {
        return $this->additionalItems()->sum('total_price');
    }


    // Calculate discount amount
    public function getDiscountAmountAttribute()
    {
        $baseAmount = $this->subtotal + $this->added_cost - $this->returned_cost;
        return ($baseAmount * $this->total_discount) / 100;
    }

    // Calculate the final total
    public function getTotalPriceAttribute()
    {
        $baseAmount = $this->subtotal + $this->added_cost - $this->returned_cost;
        return $baseAmount - $this->discount_amount;
    }

    // Get total returned quantity
    public function getTotalReturnedQuantityAttribute()
    {
        return $this->returnDetails->sum('returned_quantity');
    }

    // Get total added quantities
    public function getTotalAddedQuantityAttribute()
    {
        return $this->additionalItems->sum('quantity');
    }

    // Update and save invoice totals
    public function recalculateTotals()
    {
        $this->total_amount = $this->total_price; // Dynamically calculate total price
        $this->save();
    }

    // Query Scopes for Filtering
    public function scopePaid($query)
    {
        return $query->where('paid', 1);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('paid', 0);
    }

    public function scopeOverdue($query)
    {
        return $query->where('rental_end_date', '<', now())->where('paid', 0);
    }

    public function calculateTotals()
    {
        // Subtotal for main items (standard and fixed)
        $subtotal = $this->items->sum(function ($item) {
            $type = $item->product->type instanceof \App\Enums\ProductType
                ? $item->product->type->value
                : $item->product->type;

            return $type === 'fixed'
                ? $item->price * $item->quantity // Fixed: price * quantity
                : $item->price * $item->quantity * $item->days; // Standard: price * quantity * days
        });

        // Additional costs for additional items
        $additionalCost = $this->additionalItems->sum(function ($item) {
            $type = $item->product->type instanceof \App\Enums\ProductType
                ? $item->product->type->value
                : $item->product->type;

            return $type === 'fixed'
                ? $item->price * $item->quantity // Fixed: price * quantity
                : $item->price * $item->quantity * $item->days; // Standard: price * quantity * days
        });

        // Costs for returned items
        $returnedCost = $this->returnDetails->sum(function ($return) {
            return $return->returned_quantity * $return->invoiceItem->price * $return->days_used;
        });

        // Total before discount
        $totalBeforeDiscount = $subtotal + $additionalCost - $returnedCost;

        // Discount amount
        $discountAmount = ($totalBeforeDiscount * ($this->total_discount ?? 0) / 100);

        // Final total after discount
        $total = $totalBeforeDiscount - $discountAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'additionalCost' => round($additionalCost, 2),
            'returnedCost' => round($returnedCost, 2),
            'discountAmount' => round($discountAmount, 2),
            'total' => round($total, 2),
        ];
    }


    public function checkAndUpdateStatus()
    {
        // Check if all items are returned
        $allReturned = $this->items()->where('status', '!=', 'returned')->doesntExist();

        // Update the invoice status if all items are returned
        if ($allReturned) {
            $this->status = 'returned';
            $this->save();
        }
    }
}
