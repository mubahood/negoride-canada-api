<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\Negotiation;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $driver;
    protected $negotiation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->customer = User::factory()->create([
            'email' => 'customer@test.com',
            'phone_number' => '+1234567890',
        ]);

        $this->driver = User::factory()->create([
            'email' => 'driver@test.com',
            'phone_number' => '+1234567891',
        ]);

        // Create wallets
        UserWallet::create([
            'user_id' => $this->customer->id,
            'wallet_balance' => 200.00,
            'total_earnings' => 0.00,
        ]);

        UserWallet::create([
            'user_id' => $this->driver->id,
            'wallet_balance' => 100.00,
            'total_earnings' => 500.00,
        ]);

        // Create negotiation
        $this->negotiation = Negotiation::create([
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'pickup_address' => 'Downtown Toronto',
            'dropoff_address' => 'Pearson Airport',
            'pickup_lat' => 43.6532,
            'pickup_lng' => -79.3832,
            'dropoff_lat' => 43.6777,
            'dropoff_lng' => -79.6248,
            'status' => 'ACCEPTED',
            'agreed_price' => 75.00,
            'payment_status' => 'pending',
        ]);
    }

    /** @test */
    public function it_can_initiate_payment()
    {
        // Mock Stripe service
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn([
                    'id' => 'pi_test_123456',
                    'client_secret' => 'pi_test_123456_secret_test',
                    'status' => 'requires_payment_method',
                    'amount' => 7500,
                ]);
        });

        $response = $this->postJson('/api/initiate-payment', [
            'negotiation_id' => $this->negotiation->id,
            'amount' => 75.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment_intent_id',
                    'client_secret',
                    'payment_id',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_payment_initiation()
    {
        $response = $this->postJson('/api/initiate-payment', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['negotiation_id', 'amount']);
    }

    /** @test */
    public function it_can_verify_payment()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_verified',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'succeeded',
            'currency' => 'cad',
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/api/verify-payment', [
            'payment_intent_id' => 'pi_test_verified',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'succeeded',
                ],
            ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_payment_intent()
    {
        $response = $this->postJson('/api/verify-payment', [
            'payment_intent_id' => 'pi_invalid',
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_get_payment_history()
    {
        // Create multiple payments for the customer
        Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_1',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'succeeded',
            'currency' => 'cad',
            'paid_at' => now()->subDays(1),
        ]);

        Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_2',
            'amount' => 50.00,
            'service_fee' => 5.00,
            'driver_amount' => 45.00,
            'status' => 'succeeded',
            'currency' => 'cad',
            'paid_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->customer, 'api')
            ->getJson('/api/payment-history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'status',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    /** @test */
    public function it_can_get_specific_payment_details()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_detail',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'succeeded',
            'currency' => 'cad',
            'description' => 'Test payment',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->customer, 'api')
            ->getJson("/api/payment/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'amount' => '75.00',
                    'status' => 'succeeded',
                    'description' => 'Test payment',
                ],
            ]);
    }

    /** @test */
    public function it_can_refund_payment()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_refund',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'succeeded',
            'currency' => 'cad',
            'paid_at' => now(),
        ]);

        // Mock Stripe service
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createRefund')
                ->once()
                ->andReturn([
                    'id' => 'refund_test_123',
                    'status' => 'succeeded',
                    'amount' => 7500,
                ]);
        });

        $response = $this->postJson('/api/refund-payment', [
            'payment_id' => $payment->id,
            'reason' => 'Customer requested refund',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment refunded successfully',
            ]);

        $payment->refresh();
        $this->assertNotNull($payment->refunded_at);
    }

    /** @test */
    public function it_cannot_refund_non_succeeded_payment()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_pending',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $response = $this->postJson('/api/refund-payment', [
            'payment_id' => $payment->id,
            'reason' => 'Test refund',
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function it_can_cancel_pending_payment()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_cancel',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $response = $this->postJson('/api/cancel-payment', [
            'payment_id' => $payment->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment cancelled successfully',
            ]);

        $payment->refresh();
        $this->assertEquals('cancelled', $payment->status);
    }

    /** @test */
    public function it_cannot_cancel_succeeded_payment()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_succeeded',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'succeeded',
            'currency' => 'cad',
            'paid_at' => now(),
        ]);

        $response = $this->postJson('/api/cancel-payment', [
            'payment_id' => $payment->id,
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function it_requires_authentication_for_payment_history()
    {
        $response = $this->getJson('/api/payment-history');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_filters_payment_history_by_user()
    {
        // Create payment for customer
        Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_customer_payment',
            'amount' => 75.00,
            'service_fee' => 7.50,
            'driver_amount' => 67.50,
            'status' => 'succeeded',
            'currency' => 'cad',
        ]);

        // Create different negotiation and payment for driver
        $otherNegotiation = Negotiation::create([
            'customer_id' => User::factory()->create()->id,
            'driver_id' => $this->driver->id,
            'pickup_address' => 'Test',
            'dropoff_address' => 'Test',
            'pickup_lat' => 43.6532,
            'pickup_lng' => -79.3832,
            'dropoff_lat' => 43.6777,
            'dropoff_lng' => -79.6248,
            'status' => 'ACCEPTED',
            'agreed_price' => 50.00,
        ]);

        Payment::create([
            'negotiation_id' => $otherNegotiation->id,
            'customer_id' => $otherNegotiation->customer_id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_driver_earning',
            'amount' => 50.00,
            'service_fee' => 5.00,
            'driver_amount' => 45.00,
            'status' => 'succeeded',
            'currency' => 'cad',
        ]);

        // Customer should only see their payment
        $customerResponse = $this->actingAs($this->customer, 'api')
            ->getJson('/api/payment-history');

        $customerData = $customerResponse->json('data');
        $this->assertEquals(1, count($customerData));
        $this->assertEquals($this->customer->id, $customerData[0]['customer_id']);

        // Driver should only see payments where they are the driver
        $driverResponse = $this->actingAs($this->driver, 'api')
            ->getJson('/api/payment-history');

        $driverData = $driverResponse->json('data');
        $this->assertEquals(2, count($driverData));
    }
}

