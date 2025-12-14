<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\Negotiation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with wallets
        $this->customer = User::factory()->create();
        $this->driver = User::factory()->create();
        
        // Create wallets for test users
        UserWallet::create([
            'user_id' => $this->customer->id,
            'wallet_balance' => 100.00,
            'total_earnings' => 0.00,
        ]);
        
        UserWallet::create([
            'user_id' => $this->driver->id,
            'wallet_balance' => 50.00,
            'total_earnings' => 200.00,
        ]);
        
        // Create a test negotiation
        $this->negotiation = Negotiation::create([
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'pickup_address' => 'Test Pickup',
            'dropoff_address' => 'Test Dropoff',
            'pickup_lat' => 43.6532,
            'pickup_lng' => -79.3832,
            'dropoff_lat' => 43.6777,
            'dropoff_lng' => -79.6248,
            'status' => 'ACCEPTED',
            'agreed_price' => 100.00,
            'payment_status' => 'pending',
        ]);
    }

    /** @test */
    public function it_can_create_a_payment()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(100.00, $payment->amount);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('cad', $payment->currency);
    }

    /** @test */
    public function it_sets_default_currency_on_creation()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
        ]);

        $this->assertEquals('cad', $payment->currency);
    }

    /** @test */
    public function it_can_mark_payment_as_paid()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $payment->markAsPaid();

        $this->assertEquals('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
        
        // Check negotiation status updated
        $this->negotiation->refresh();
        $this->assertEquals('paid', $this->negotiation->payment_status);
        $this->assertNotNull($this->negotiation->payment_completed_at);
    }

    /** @test */
    public function it_creates_transactions_when_marked_as_paid()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $transactionCountBefore = Transaction::count();
        $payment->markAsPaid();
        $transactionCountAfter = Transaction::count();

        // Should create 3 transactions: customer payment, driver earning, service fee
        $this->assertEquals(3, $transactionCountAfter - $transactionCountBefore);

        // Verify customer transaction
        $customerTransaction = Transaction::where('user_id', $this->customer->id)
            ->where('payment_id', $payment->id)
            ->where('type', 'debit')
            ->first();
        
        $this->assertNotNull($customerTransaction);
        $this->assertEquals(100.00, $customerTransaction->amount);
        $this->assertEquals('ride_payment', $customerTransaction->category);

        // Verify driver earning transaction
        $driverTransaction = Transaction::where('user_id', $this->driver->id)
            ->where('payment_id', $payment->id)
            ->where('type', 'credit')
            ->where('category', 'ride_earning')
            ->first();
        
        $this->assertNotNull($driverTransaction);
        $this->assertEquals(90.00, $driverTransaction->amount);

        // Verify service fee transaction
        $feeTransaction = Transaction::where('user_id', $this->driver->id)
            ->where('payment_id', $payment->id)
            ->where('category', 'service_fee')
            ->first();
        
        $this->assertNotNull($feeTransaction);
        $this->assertEquals(10.00, $feeTransaction->amount);
    }

    /** @test */
    public function it_updates_driver_wallet_balance_when_paid()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $driverWallet = $this->driver->wallet;
        $initialBalance = $driverWallet->wallet_balance;
        $initialEarnings = $driverWallet->total_earnings;

        $payment->markAsPaid();

        $driverWallet->refresh();
        
        $this->assertEquals($initialBalance + 90.00, $driverWallet->wallet_balance);
        $this->assertEquals($initialEarnings + 90.00, $driverWallet->total_earnings);
    }

    /** @test */
    public function it_can_mark_payment_as_failed()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $payment->markAsFailed('insufficient_funds');

        $this->assertEquals('failed', $payment->status);
        $this->assertEquals('insufficient_funds', $payment->failure_reason);
        $this->assertNotNull($payment->failed_at);
        
        // Check negotiation status updated
        $this->negotiation->refresh();
        $this->assertEquals('failed', $this->negotiation->payment_status);
    }

    /** @test */
    public function it_has_relationships_with_customer_and_driver()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $this->assertInstanceOf(User::class, $payment->customer);
        $this->assertInstanceOf(User::class, $payment->driver);
        $this->assertEquals($this->customer->id, $payment->customer->id);
        $this->assertEquals($this->driver->id, $payment->driver->id);
    }

    /** @test */
    public function it_has_relationship_with_negotiation()
    {
        $payment = Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_123',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $this->assertInstanceOf(Negotiation::class, $payment->negotiation);
        $this->assertEquals($this->negotiation->id, $payment->negotiation->id);
    }

    /** @test */
    public function it_can_filter_succeeded_payments()
    {
        Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_1',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'succeeded',
            'currency' => 'cad',
        ]);

        Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_2',
            'amount' => 50.00,
            'service_fee' => 5.00,
            'driver_amount' => 45.00,
            'status' => 'pending',
            'currency' => 'cad',
        ]);

        $succeededPayments = Payment::succeeded()->get();
        
        $this->assertEquals(1, $succeededPayments->count());
        $this->assertEquals('succeeded', $succeededPayments->first()->status);
    }

    /** @test */
    public function it_can_filter_pending_payments()
    {
        Payment::create([
            'negotiation_id' => $this->negotiation->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'stripe_payment_intent_id' => 'pi_test_1',
            'amount' => 100.00,
            'service_fee' => 10.00,
            'driver_amount' => 90.00,
            'status' => 'pending',
            'currency' => 'cad',
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
        ]);

        $pendingPayments = Payment::pending()->get();
        
        $this->assertEquals(1, $pendingPayments->count());
        $this->assertEquals('pending', $pendingPayments->first()->status);
    }
}
