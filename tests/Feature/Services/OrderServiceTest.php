<?php

namespace Tests\Feature\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Merchant $merchant;

    public function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()
            ->for(User::factory())
            ->create();
    }

    protected function getOrderService(): OrderService
    {
        return $this->app->make(OrderService::class);
    }

    public function test_process_order()
    {
        $data = [
            'order_id' => $this->faker->uuid(),
            'subtotal_price' => round(rand(100, 999) / 3, 2),
            'merchant_domain' => $this->merchant->domain,
            'discount_code' => $this->faker->uuid(),
            'customer_email' => $this->faker->email(),
            'customer_name' => $this->faker->name()
        ];

        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::factory()
            ->for($this->merchant)
            ->for(User::factory())
            ->create([
                'discount_code' => $data['discount_code']
            ]);

        $this->mock(AffiliateService::class)
            ->shouldReceive('register')
            ->once()
            ->with(\Mockery::on(fn ($model) => $model->is($this->merchant)), $data['customer_email'], $data['customer_name'], 0.1);

        $this->getOrderService()->processOrder($data);

        $this->assertDatabaseHas('orders', [
            'subtotal' => $data['subtotal_price'],
            'affiliate_id' => $affiliate->id,
            'merchant_id' => $this->merchant->id,
            'commission_owed' => $data['subtotal_price'] * $affiliate->commission_rate,
            'external_order_id' => $data['order_id']
        ]);
    }

    public function test_process_duplicate_order()
    {
        /** @var Order $order */
        $order = Order::factory()
            ->for(Merchant::factory()->for(User::factory()))
            ->create();

        $data = [
            'order_id' => $order->external_order_id,
            'subtotal_price' => round(rand(100, 999) / 3, 2),
            'merchant_domain' => $this->merchant->domain,
            'discount_code' => $this->faker->uuid(),
            'customer_email' => $this->faker->email(),
            'customer_name' => $this->faker->name()
        ];

        $this->getOrderService()->processOrder($data);

        $this->assertDatabaseCount('orders', 1);
    }
}
