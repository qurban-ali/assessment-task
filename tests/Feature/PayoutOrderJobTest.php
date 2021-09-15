<?php

namespace Tests\Feature;

use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Services\ApiService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use RuntimeException;
use Tests\TestCase;

class PayoutOrderJobTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Order $order;

    public function setUp(): void
    {
        parent::setUp();

        $this->order = Order::factory()
            ->for($merchant = Merchant::factory()->for(User::factory())->create())
            ->for(Affiliate::factory()->for($merchant)->for(User::factory()))
            ->create();
    }

    public function test_calls_api()
    {
        $this->mock(ApiService::class)
            ->shouldReceive('sendPayout')
            ->once()
            ->with($this->order->affiliate->user->email, $this->order->commission_owed);

        dispatch(new PayoutOrderJob($this->order));

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payout_status' => Order::STATUS_PAID
        ]);
    }

    public function test_rolls_back_if_exception_thrown()
    {
        $this->mock(ApiService::class)
            ->shouldReceive('sendPayout')
            ->once()
            ->with($this->order->affiliate->user->email, $this->order->commission_owed)
            ->andThrow(RuntimeException::class);

        $this->expectException(RuntimeException::class);

        dispatch(new PayoutOrderJob($this->order));

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payout_status' => Order::STATUS_UNPAID
        ]);
    }
}
