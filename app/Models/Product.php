<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // Relationship to get invoice items for the product
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'product_id');
    }

    // Calculate the total rented quantity (active invoices only)
    public function rentedQuantity()
    {
        return $this->invoiceItems()
            ->whereHas('invoice', function ($query) {
                $query->where('status', 'active'); // Only consider active rentals
            })
            ->sum('quantity');
    }

    // Get the detailed rentals including customer and dates
    public function rentals()
    {
        return $this->invoiceItems()->whereHas('invoice', function ($query) {
            $query->where('status', 'active');
        });
    }
}
