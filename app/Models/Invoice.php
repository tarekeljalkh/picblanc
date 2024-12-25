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
        'deposit',
        'paid_amount',
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

    public function customItems()
    {
        return $this->hasMany(CustomItem::class);
    }



    public function additionalItems()
    {
        return $this->hasMany(AdditionalItem::class, 'invoice_id');
    }


    public function returnDetails()
    {
        return $this->hasMany(ReturnDetail::class, 'invoice_id');
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
        return ($this->subtotal * $this->total_discount) / 100;
    }

    // Calculate the final total
    // Final total dynamically calculated, including adjustments
    public function getTotalPriceAttribute()
    {
        $subtotal = $this->subtotal; // Base total of original items
        $additionalItemsTotal = $this->added_cost; // Total of additional items
        $returnedCost = $this->returned_cost; // Cost of returned items
        $discountAmount = $this->discount_amount; // Discount based on subtotal

        // Final Total Calculation
        return ($subtotal + $additionalItemsTotal - $returnedCost) - $discountAmount;
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
        // Subtotal for original items
        $itemSubtotal = $this->items->sum(function ($item) {
            return $item->price * $item->quantity * ($item->days ?? 1);
        });

        // Subtotal for additional items
        $additionalItemSubtotal = $this->additionalItems->sum(function ($item) {
            return $item->price * $item->quantity * ($item->days ?? 1);
        });

        // Total adjustments
        $subtotal = $itemSubtotal + $additionalItemSubtotal;

        $discountAmount = ($subtotal * ($this->total_discount ?? 0)) / 100;

        // Total amount is calculated dynamically but not saved
        return [
            'subtotal' => $subtotal,
            'discountAmount' => $discountAmount,
            'finalTotal' => $subtotal - $discountAmount,
        ];
    }

    // public function recalculateTotals()
    // {
    //     $totals = $this->calculateTotals();

    //     $this->total_amount = $totals['total'];
    //     $this->save();
    // }

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

    public function getBalanceDueAttribute()
    {
        $finalTotal = $this->total_price; // Dynamically calculated final total
        $paidAmount = $this->deposit + $this->paid_amount; // Total paid so far

        // Calculate Balance Due
        return max(0, $finalTotal - $paidAmount);
    }

    public function calculateTotals()
    {
        $isSeasonal = $this->category->name === 'season';

        // Subtotal: Includes all items (regular items, custom items)
        $subtotalForDiscount = $this->items->sum(function ($item) use ($isSeasonal) {
            return $isSeasonal
                ? $item->price * $item->quantity
                : $item->price * $item->quantity * ($item->days ?? 1);
        }) + $this->customItems->sum(function ($customItem) {
            return $customItem->price * $customItem->quantity;
        });

        // Subtotal: Includes all items (regular items, custom items, additional items)
        $totalSubtotal = $subtotalForDiscount + $this->additionalItems->sum(function ($additionalItem) {
            return $additionalItem->price * $additionalItem->quantity;
        });

        // Discount: Applied only to the items eligible for discount (regular items + custom items)
        $discountPercentage = $this->total_discount ?? 0;
        $discountAmount = ($subtotalForDiscount * $discountPercentage) / 100;

        // Returned Items Cost: Cost for used days of returned items
        $returnedItemsCost = $this->returnDetails->sum(function ($return) {
            $pricePerDay = $return->invoiceItem
                ? $return->invoiceItem->price
                : ($return->additionalItem ? $return->additionalItem->price : 0);

            return $return->days_used * $return->returned_quantity * $pricePerDay;
        });

        // Refund for Unused Days
        $refundForUnusedDays = $this->returnDetails->sum(function ($return) {
            $totalDays = $return->invoiceItem
                ? ($return->invoiceItem->days ?? 1)
                : ($return->additionalItem->days ?? 1);

            $remainingDays = $totalDays - $return->days_used;

            $pricePerDay = $return->invoiceItem
                ? $return->invoiceItem->price
                : ($return->additionalItem ? $return->additionalItem->price : 0);

            return max(0, $remainingDays) * $return->returned_quantity * $pricePerDay;
        });

        // Total after discount and before deductions
        $totalAfterDiscount = $totalSubtotal - $discountAmount;

        // Final Total: Subtract refunds and returned items cost
        $finalTotal = $totalAfterDiscount - $refundForUnusedDays;

        // Balance Due: Amount still owed
        $totalPaid = $this->deposit + $this->paid_amount;
        $balanceDue = max(0, $finalTotal - $totalPaid);

        return [
            'subtotal' => round($totalSubtotal, 2), // All items subtotal
            'subtotalForDiscount' => round($subtotalForDiscount, 2), // Discounted items subtotal
            'discountAmount' => round($discountAmount, 2), // Discount amount
            'returnedItemsCost' => round($returnedItemsCost, 2),
            'refundForUnusedDays' => round($refundForUnusedDays, 2),
            'finalTotal' => round($finalTotal, 2),
            'balanceDue' => round($balanceDue, 2),
        ];
    }

    public function checkAndUpdateStatus()
    {
        $totalAmount = $this->total_amount + $this->additionalItems->sum('total_price');
        $paidAmount = $this->paid_amount;

        if ($totalAmount <= $paidAmount) {
            $this->status = 'active';
        } elseif ($this->rental_end_date && $this->rental_end_date < now() && $paidAmount < $totalAmount) {
            $this->status = 'overdue';
        } else {
            $this->status = 'draft';
        }

        $this->save();
    }


    public function getPaymentStatusAttribute()
    {
        $balanceDue = $this->balance_due;

        if ($balanceDue <= 0) {
            return 'fully_paid';
        }

        if ($balanceDue > 0 && ($this->paid_amount + $this->deposit) > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }


    public function getTotalWithAdditionalAttribute()
    {
        return $this->total_amount + $this->added_cost - $this->returned_cost;
    }
}
