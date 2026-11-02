<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderController;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Orders\CreateSaleFromOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class PayphoneConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payphone.token' => 'button-token', 'services.payphone.box_token' => 'box-token']);
        Http::preventStrayRequests();
    }

    private function transaction(string $source = 'payphone_box'): Transaction
    {
        $order = Order::create(['total' => 11.50, 'status' => 'pending']);

        return Transaction::create([
            'order_id' => $order->id, 'amount' => 11.50, 'status' => 'pending',
            'client_transaction_id' => 'order-test', 'response_payload' => ['source' => $source],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'statusCode' => 3, 'transactionStatus' => 'Approved', 'amount' => 1150,
            'clientTransactionId' => 'order-test', 'transactionId' => 123456, 'authorizationCode' => 'AUTH-123',
        ], $overrides);
    }

    private function callbackUrl(): string
    {
        return route('payphone.success', ['id' => 123456, 'clientTransactionId' => 'order-test']);
    }

    public function test_each_flow_uses_its_own_endpoint_and_token_and_reload_is_idempotent(): void
    {
        foreach (['payphone_box', 'payphone_button'] as $source) {
            Transaction::query()->delete();
            $tx = $this->transaction($source);
            $endpoint = $source === 'payphone_box'
                ? 'https://paymentbox.payphonetodoesposible.com/api/confirm'
                : 'https://pay.payphonetodoesposible.com/api/button/V2/Confirm';
            Http::fake([$endpoint => Http::response($this->payload())]);
            $this->mock(CreateSaleFromOrderService::class, function (MockInterface $mock) {
                // Simula el servicio real, que enlaza la venta a la orden.
                $mock->shouldReceive('create')->once()->andReturnUsing(function (Order $order) {
                    $order->forceFill(['sale_id' => 1])->save();

                    return null;
                });
            });
            // Una sesi?n apuntando a otra orden no debe determinar qu? orden se paga.
            $this->withSession(['current_order_id' => 999999])->get($this->callbackUrl())
                ->assertRedirect(route('orden.confirmacion', $tx->order_id));
            $this->assertSame('paid', $tx->order->fresh()->status);
            $this->assertSame('AUTH-123', $tx->fresh()->response_payload['authorizationCode']);
            $this->get($this->callbackUrl())->assertRedirect(route('orden.confirmacion', $tx->order_id));
            Http::assertSentCount(1);
            Http::assertSent(fn ($request) => $request->url() === $endpoint
                && $request['id'] === 123456 && $request['clientTxId'] === 'order-test'
                && ! isset($request['clientTransactionId'])
                && $request->hasHeader('Authorization', 'Bearer '.($source === 'payphone_box' ? 'box-token' : 'button-token')));
            // Evitar el índice único de referencia entre las dos iteraciones.
            $tx->order->update(['payphone_transaction_id' => null, 'sale_id' => null]);
        }
    }

    public function test_sale_failure_keeps_the_confirmed_payment_and_reload_retries_the_sale(): void
    {
        $tx = $this->transaction();
        Http::fake(['*' => Http::response($this->payload())]);
        $this->mock(CreateSaleFromOrderService::class, function (MockInterface $mock) {
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('db down'));
            $mock->shouldReceive('create')->once()->andReturnUsing(function (Order $order) {
                $order->forceFill(['sale_id' => 1])->save();

                return null;
            });
        });

        $this->get($this->callbackUrl())->assertRedirect(route('orden.confirmacion', $tx->order_id));
        $this->assertSame('approved', $tx->fresh()->status);
        $this->assertSame('paid', $tx->order->fresh()->status);
        $this->assertNull($tx->order->fresh()->sale_id);

        // Recargar no vuelve a llamar a PayPhone, pero sí reintenta la venta pendiente.
        $this->get($this->callbackUrl())->assertRedirect(route('orden.confirmacion', $tx->order_id));
        $this->assertSame(1, $tx->order->fresh()->sale_id);
        Http::assertSentCount(1);
    }

    public function test_failed_confirm_never_trusts_url_and_can_be_retried(): void
    {
        $tx = $this->transaction();
        $this->mock(CreateSaleFromOrderService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('create'));
        Http::fake(['*' => Http::sequence()->push($this->payload(), 500)->push($this->payload(['amount' => 1]))]);
        $this->get($this->callbackUrl().'&transactionStatus=Approved&statusCode=3')->assertViewIs('checkout.payphone-pending');
        $this->get($this->callbackUrl())->assertViewIs('checkout.payphone-pending');
        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame('pending', $tx->order->fresh()->status);
    }

    public function test_all_response_checks_are_required(): void
    {
        $tx = $this->transaction();
        $this->mock(CreateSaleFromOrderService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('create'));
        foreach ([['statusCode' => 2], ['transactionStatus' => 'Canceled'], ['clientTransactionId' => 'other'], ['amount' => '1150'], ['transactionId' => 999]] as $override) {
            Http::fake(['*' => Http::response($this->payload($override))]);
            $this->get($this->callbackUrl())->assertViewIs('checkout.payphone-pending');
            $this->assertSame('pending', $tx->fresh()->status);
        }
    }

    public function test_network_failure_and_malformed_json_leave_the_order_pending(): void
    {
        $tx = $this->transaction();
        Http::fake(['*' => Http::failedConnection()]);
        $this->get($this->callbackUrl())->assertViewIs('checkout.payphone-pending');
        Http::fake(['*' => Http::response('invalid-json', 200)]);
        $this->get($this->callbackUrl())->assertViewIs('checkout.payphone-pending');
        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame('pending', $tx->order->fresh()->status);
    }

    public function test_only_a_verified_cancellation_changes_the_order(): void
    {
        $tx = $this->transaction();
        Http::fake(['*' => Http::response($this->payload(['statusCode' => 2, 'transactionStatus' => 'Canceled']))]);
        $this->get($this->callbackUrl())->assertRedirect(route('carrito.index'));
        $this->assertSame('cancelled', $tx->fresh()->status);
        $this->assertSame('cancelled', $tx->order->fresh()->status);
    }

    public function test_url_cancel_cannot_change_an_approved_payment(): void
    {
        $tx = $this->transaction();
        $tx->update(['status' => 'approved']);
        $tx->order->update(['status' => 'paid']);
        $this->withSession(['current_order_id' => $tx->order_id])->get(route('payphone.cancel'))->assertRedirect(route('carrito.index'));
        $this->assertSame('approved', $tx->fresh()->status);
        $this->assertSame('paid', $tx->order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_unknown_transaction_does_not_call_payphone(): void
    {
        $this->get($this->callbackUrl())->assertRedirect(route('carrito.index'));
        Http::assertNothingSent();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_button_prepare_sends_tax_values_in_cents(): void
    {
        $order = Order::create(['total' => 11.50, 'status' => 'pending']);
        foreach ([0, 15] as $tax) {
            config(['services.payphone.tax' => $tax]);
            Http::fake(['*' => Http::response(['payWithCard' => 'https://example.com/pay'])]);
            $method = new \ReflectionMethod(OrderController::class, 'prepararPagoPayphone');
            $result = $method->invoke(app(OrderController::class), $order);
            $this->assertNotEmpty($result['clientTransactionId']);
            Http::assertSent(fn ($request) => $request['amount'] === 1150
                && $request['amountWithTax'] === ($tax ? 1000 : 0)
                && $request['amountWithoutTax'] === ($tax ? 0 : 1150)
                && $request['tax'] === ($tax ? 150 : 0));
        }
    }
}
