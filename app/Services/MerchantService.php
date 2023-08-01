<?php

namespace App\Services;

use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;

class MerchantService
{
    /**
     * Register a new user and associated merchant.
     * Hint: Use the password field to store the API key.
     * Hint: Be sure to set the correct user type according to the constants in the User model.
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return Merchant
     */
    public function register(array $data): Merchant
    {
        $user = User::create([
            'domain' => $data['domain'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['api_key'],
            'type' => User::TYPE_MERCHANT,
        ]);

        $merchant = Merchant::create([
            'domain' => $data['domain'],
            'display_name' => $data['name'],
            'user_id' => $user->id,
        ]);

        return $merchant->load('user');
    }

    /**
     * Update the user
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return void
     */
    public function updateMerchant(User $user, array $data)
    {

        $user->update([
            'domain' => $data['domain'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['api_key'],
        ]);

        return $user->merchant()->update([
            'domain' => $data['domain'],
            'display_name' => $data['name'],
        ]);
    }

    /**
     * Find a merchant by their email.
     * Hint: You'll need to look up the user first.
     *
     * @param string $email
     * @return Merchant|null
     */
    public function findMerchantByEmail(string $email): ?Merchant
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user->merchant;
        }
        return null;
    }

    /**
     * Pay out all of an affiliate's orders.
     * Hint: You'll need to dispatch the job for each unpaid order.
     *
     * @param Affiliate $affiliate
     * @return void
     */
    public function payout(Affiliate $affiliate)
    {

        $unpaidOrders = Order::where('affiliate_id', $affiliate->id)
            ->where('payout_status', Order::STATUS_UNPAID)
            ->get();

        foreach ($unpaidOrders as $order) {
            PayoutOrderJob::dispatch($order);
        }
    }

    /**
     * Get useful order statistics for the specified merchant within the date range.
     *
     * @param Merchant $merchant
     * @param Carbon $fromDate
     * @param Carbon $toDate
     * @return array
     */
    public function getOrderStatistics(Merchant $merchant, Carbon $fromDate, Carbon $toDate): array
    {
        $totalOrders = $merchant->orders()
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->count();

        $revenue = $merchant->orders()
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('subtotal');

        $commissionOwed = $merchant->orders()
            ->where('payout_status', Order::STATUS_UNPAID)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('commission_owed');

        $commissionOwedNoAffliated = $merchant->orders()
            ->where('payout_status', Order::STATUS_UNPAID)
            ->whereNull('affiliate_id')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('commission_owed');
            
        return [
            'count' => $totalOrders,
            'commissions_owed' => $commissionOwed - $commissionOwedNoAffliated,
            'revenue' => $revenue,
        ];
    }
}
