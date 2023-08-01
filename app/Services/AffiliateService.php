<?php

namespace App\Services;

use App\Exceptions\AffiliateCreateException;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AffiliateService
{
    public function __construct(
        protected ApiService $apiService
    ) {
    }

    /**
     * Create a new affiliate for the merchant with the given commission rate.
     *
     * @param  Merchant $merchant
     * @param  string $email
     * @param  string $name
     * @param  float $commissionRate
     * @return Affiliate
     */
    public function register(Merchant $merchant, string $email, string $name, float $commissionRate, bool $sent_email = true, string $discount_code = null): Affiliate
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'email' => $email,
                    'name' => $name,
                    'type' => User::TYPE_AFFILIATE,
                    'password' => bcrypt('password'),
                ]);
            }

            $affiliate = Affiliate::where('merchant_id', $merchant->id)->where('user_id', $user->id)->first();

            if (!$affiliate) {
                if ($discount_code == null)
                    $discount_code = $this->apiService->createDiscountCode($merchant)['code'];
                

                $affiliate = Affiliate::create([
                    'merchant_id' => $merchant->id,
                    'user_id' => $user->id,
                    'commission_rate' => $commissionRate,
                    'discount_code' => $discount_code,
                ]);
            }


            if ($sent_email)
                Mail::to($user->email)->send(new AffiliateCreated($affiliate));

            return $affiliate;
        } catch (\Exception $e) {
            throw new AffiliateCreateException("Failed to create affiliate.");
        }
    }
}
