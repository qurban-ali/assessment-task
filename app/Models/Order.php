<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


/**
 * @property int $id
 * @property Merchant $merchant
 * @property Affiliate $affiliate
 * @property float $subtotal
 * @property float $commission_owed
 * @property string $payout_status
 */
class Order extends Model
{
    use HasFactory;

    const STATUS_UNPAID = 'unpaid';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'merchant_id',
        'affiliate_id',
        'external_order_id',
        'subtotal',
        'commission_owed',
        'payout_status',
        // 'customer_email', //there is not filed in database 
        'created_at'
    ];

    public function externalOrderId(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($value === null) {
                    $value = Str::uuid()->toString();
                } else {
                    $existingOrder = Order::where('external_order_id', $value)->first();

                    while ($existingOrder !== null) {
                        $value = Str::uuid()->toString();
                        $existingOrder = Order::where('external_order_id', $value)->first();
                    }
                }

                return $value;
            }
        );
    }


    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
