<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function paymentMethodAccess()
    {
        return $this->belongsTo(PaymentMethodAccess::class);
    }
}
