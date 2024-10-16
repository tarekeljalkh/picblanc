<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

        // Fields that can be mass assigned
        protected $fillable = ['name', 'email', 'phone', 'address', 'deposit_card'];

        // Relationships
        public function invoices()
        {
            return $this->hasMany(Invoice::class);
        }

        public function hasRentals()
        {
            return $this->invoices()->exists();
        }

}
