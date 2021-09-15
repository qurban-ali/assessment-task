<?php

namespace Tests\Feature\Services;

use App\Exceptions\AffiliateCreateException;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\ApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliateServiceTest extends TestCase
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

    public function getAffiliateService(): AffiliateService
    {
        return $this->app->make(AffiliateService::class);
    }

    public function test_register_affiliate()
    {
        $this->mock(ApiService::class)
            ->shouldReceive('createDiscountCode')
            ->once()
            ->andReturn([
                'id' => -1,
                'code' => $discountCode = $this->faker->uuid()
            ]);

        Mail::fake();

        $this->assertInstanceOf(Affiliate::class, $affiliate = $this->getAffiliateService()->register($this->merchant, $email = $this->faker->email(), $name = $this->faker->name(), 0.1));

        Mail::assertSent(function (AffiliateCreated $mail) use ($affiliate) {
            return $mail->affiliate->is($affiliate);
        });

        $this->assertDatabaseHas('users', [
            'email' => $email
        ]);

        $this->assertDatabaseHas('affiliates', [
            'merchant_id' => $this->merchant->id,
            'commission_rate' => 0.1,
            'discount_code' => $discountCode
        ]);
    }

    public function test_register_affiliate_when_email_in_use_as_merchant()
    {
        $this->expectException(AffiliateCreateException::class);

        $this->getAffiliateService()->register($this->merchant, $this->merchant->user->email, $this->faker->name(), 0.1);
    }

    public function test_register_affiliate_when_email_in_use_as_affiliate()
    {
        $this->expectException(AffiliateCreateException::class);

        $affiliate = Affiliate::factory()
            ->for($this->merchant)
            ->for(User::factory())
            ->create();

        $this->getAffiliateService()->register($this->merchant, $affiliate->user->email, $this->faker->name(), 0.1);
    }
}
