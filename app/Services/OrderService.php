<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;


class OrderService
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {
    }

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param  array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        $affiliate = Affiliate::whereHas('user', function ($query) use ($data) {
            $query->where('email', $data['customer_email']);
        })->first();

        if (!$affiliate) {

            $affiliate = $this->affiliateService->register(
                Merchant::where('domain', $data['merchant_domain'])->first(),
                $data['customer_email'],
                $data['customer_name'],
                0.1,
                false,
                $data['discount_code']
            );

        }

        $existingOrder = Order::where([
            'external_order_id' => $data['order_id']
        ])->first();
        
        if ($existingOrder)
            return $existingOrder;

        $order = Order::create([
            'merchant_id' => $affiliate->merchant_id,
            'affiliate_id' => $affiliate->id,
            'external_order_id' => @$data['order_id'] ?? Str::uuid()->toString(),
            'subtotal' => $data['subtotal_price'],
            'payout_status' => Order::STATUS_UNPAID,
            'commission_owed' => $data['subtotal_price'] * $affiliate->commission_rate,
        ]);

        return $order;
    }
}
