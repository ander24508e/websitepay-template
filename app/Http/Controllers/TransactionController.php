<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\Orders\CreateSaleFromOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function success(Request $request, CreateSaleFromOrderService $createSaleFromOrderService)
    {
        $clientId = $request->input('clientTransactionId', $request->input('clientTxId'));
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_string($clientId) || $clientId === '' || $id === false) {
            return redirect()->route('carrito.index')->with('error', 'Respuesta de pago incompleta.');
        }

        $tx = DB::transaction(function () use ($clientId, $id) {
            $tx = Transaction::where('client_transaction_id', $clientId)->lockForUpdate()->first();
            if (! $tx || $tx->status !== 'pending') {
                return $tx;
            }
            $order = Order::whereKey($tx->order_id)->lockForUpdate()->firstOrFail();
            $metadata = $tx->response_payload ?? [];
            // Compatibilidad con botones creados antes de guardar source explicitamente.
            $source = $metadata['source'] ?? (isset($metadata['payWithCard']) ? 'payphone_button' : null);
            $payload = $this->verificarPagoPayphone($id, $clientId, $source);
            if (! $payload) {
                return $tx;
            }

            // Ningun dato de la URL autoriza un pago ni cambia su estado.
            if (($payload['clientTransactionId'] ?? null) !== $tx->client_transaction_id
                || ! is_int($payload['amount'] ?? null)
                || $payload['amount'] !== (int) round($order->total * 100)
                || ! is_int($payload['transactionId'] ?? null)
                || $payload['transactionId'] !== $id) {
                Log::warning('PayPhone Confirm no coincide con la transaccion', ['transaction_id' => $tx->id]);

                return $tx;
            }

            $approved = ($payload['statusCode'] ?? null) === 3
                && ($payload['transactionStatus'] ?? null) === 'Approved';
            $cancelled = ($payload['statusCode'] ?? null) === 2
                && ($payload['transactionStatus'] ?? null) === 'Canceled';
            if (! $approved && ! $cancelled) {
                return $tx;
            }

            $tx->update([
                'payphone_ref' => (string) $payload['transactionId'],
                'status' => $approved ? 'approved' : 'cancelled',
                'response_payload' => array_merge($metadata, $payload, ['source' => $source]),
            ]);
            if ($approved) {
                $order->update(['status' => 'paid', 'payphone_transaction_id' => (string) $payload['transactionId']]);
            } elseif ($order->status === 'pending') {
                $order->update(['status' => 'cancelled']);
            }

            return $tx;
        });

        if (! $tx) {
            return redirect()->route('carrito.index')->with('error', 'Transaccion no encontrada.');
        }
        if ($tx->status === 'approved') {
            // La aprobacion ya esta guardada: un fallo al crear la venta no debe deshacer un cobro confirmado.
            // Si falla, recargar esta pagina o "Registrar cobro" en el panel la vuelve a intentar.
            try {
                $order = Order::findOrFail($tx->order_id);
                if (! $order->sale_id) {
                    $createSaleFromOrderService->create($order);
                }
            } catch (\Throwable $e) {
                Log::error('PayPhone aprobado sin venta creada', ['transaction_id' => $tx->id, 'message' => $e->getMessage()]);
            }
            if ((int) session('current_order_id') === (int) $tx->order_id) {
                session()->forget(['carrito', 'current_order_id']);
                session()->regenerateToken();
            }

            return redirect()->route('orden.confirmacion', $tx->order_id)
                ->with('success', 'Pago realizado con exito.');
        }
        if ($tx->status === 'pending') {
            return response()->view('checkout.payphone-pending');
        }

        return redirect()->route('carrito.index')->with('error', 'El pago fue cancelado. Tu carrito sigue guardado.');
    }

    public function cancel(Request $request, CreateSaleFromOrderService $createSaleFromOrderService)
    {
        if ($request->filled('id') && ($request->filled('clientTransactionId') || $request->filled('clientTxId'))) {
            return $this->success($request, $createSaleFromOrderService);
        }

        // Abrir una URL de cancelacion no demuestra que PayPhone cancelo el cobro.
        return redirect()->route('carrito.index')
            ->with('error', 'Saliste del pago. Tu carrito sigue guardado.');
    }

    private function verificarPagoPayphone(int $id, string $clientId, ?string $source): ?array
    {
        $configuration = match ($source) {
            'payphone_box' => ['https://paymentbox.payphonetodoesposible.com/api/confirm', config('services.payphone.box_token')],
            'payphone_button' => [rtrim(config('services.payphone.base_url'), '/').'/api/button/V2/Confirm', config('services.payphone.token')],
            default => null,
        };
        if (! $configuration || ! $configuration[1]) {
            return null;
        }

        try {
            $response = Http::withToken($configuration[1])->acceptJson()->timeout(10)
                ->post($configuration[0], ['id' => $id, 'clientTxId' => $clientId]);
            $payload = $response->successful() ? $response->json() : null;
            if (is_array($payload)) {
                return $payload;
            }
            Log::warning('PayPhone Confirm pendiente', ['status' => $response->status(), 'source' => $source]);
        } catch (\Exception $e) {
            Log::warning('PayPhone Confirm no disponible', ['message' => $e->getMessage()]);
        }

        return null;
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $transactions = Transaction::with('order.user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('id', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('payphone_ref', 'like', "%{$search}%")
                        ->orWhere('client_transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($orderQuery) use ($search) {
                            $orderQuery->where('id', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($userQuery) use ($search) {
                                    $userQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('order.user');

        $payload = $transaction->response_payload ?? [];
        $processingDetails = collect([
            'Origen' => match (data_get($payload, 'source')) {
                'payphone_box' => 'PayPhone Box',
                'payphone_button' => 'Botón PayPhone',
                null, '' => null,
                default => (string) data_get($payload, 'source'),
            },
            'Código de autorización' => data_get($payload, 'authorizationCode')
                ?? data_get($payload, 'authorization_code'),
            'Método procesado' => data_get($payload, 'paymentMethod')
                ?? data_get($payload, 'payment_method'),
            'Tipo de tarjeta' => data_get($payload, 'cardType')
                ?? data_get($payload, 'card_type'),
            'Últimos dígitos' => data_get($payload, 'lastDigits')
                ?? data_get($payload, 'last_digits'),
        ])->filter(fn ($value) => $value !== null && $value !== '');

        return view('admin.transactions.show', compact('transaction', 'processingDetails'));
    }
}
