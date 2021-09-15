<?php

namespace Tests\Feature\Services;

use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Services\MerchantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerchantServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function getMerchantService(): MerchantService
    {
        return $this->app->make(MerchantService::class);
    }

    protected function getDummyData(): array
    {
        return [
            'domain' => $this->faker->domainName(),
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'api_key' => $this->faker->password()
        ];
    }

    public function test_create_merchant()
    {
        $data = $this->getDummyData();

        $this->getMerchantService()->register($data);

        $this->assertDatabaseHas('merchants', [
            'domain' => $data['domain'],
            'display_name' => $data['name']
        ]);

        $this->assertDatabaseHas('users', [
            'password' => $data['api_key'],
            'email' => $data['email'],
            'type' => User::TYPE_MERCHANT
        ]);
    }

    public function test_update_merchant()
    {
        /** @var Merchant $merchant */
        $merchant = Merchant::factory()
            ->for(User::factory())
            ->create();

        $data = $this->getDummyData();

        $this->getMerchantService()->updateMerchant($merchant->user, $data);

        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'domain' => $data['domain'],
            'display_name' => $data['name']
        ]);
    }

    public function test_find_merchant_by_email()
    {
        /** @var Merchant $merchant */
        $merchant = Merchant::factory()
            ->for(User::factory())
            ->create();

        $this->assertTrue($this->getMerchantService()->findMerchantByEmail($merchant->user->email)->is($merchant));
    }

    public function test_find_merchant_by_email_when_none_exists()
    {
        $this->assertNull($this->getMerchantService()->findMerchantByEmail($this->faker->email()));
    }

    public function test_payout()
    {
        /** @var Merchant $merchant */
        $merchant = Merchant::factory()
            ->for(User::factory())
            ->create();

        $affiliate = Affiliate::factory()
            ->for($merchant)
            ->for(User::factory())
            ->create();

        $orders = Order::factory()
            ->for($merchant)
            ->for($affiliate)
            ->count(10)
            ->create();

        // Mark a random order as paid
        $paid = $orders->random();
        $paid->update([
            'payout_status' => Order::STATUS_PAID
        ]);

        Queue::fake();

        $this->getMerchantService()->payout($affiliate);

        foreach ($orders as $order) {
            if ($order->refresh()->payout_status != Order::STATUS_UNPAID) {
                Queue::assertNotPushed(function (PayoutOrderJob $job) use ($order) {
                    return $job->order->is($order);
                });

                continue;
            }

            // Only unpaid orders should be processed
            Queue::assertPushed(function (PayoutOrderJob $job) use ($order) {
                return $job->order->is($order);
            });
        }
    }
}
