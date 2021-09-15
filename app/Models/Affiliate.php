<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property User $user
 * @property Merchant $merchant
 * @property float $commission_rate
 */
class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'merchant_id',
        'commission_rate',
        'discount_code'
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
