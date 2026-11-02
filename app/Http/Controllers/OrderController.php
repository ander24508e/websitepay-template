<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\CatalogType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutReceiptService;
use App\Services\Orders\CreateSaleFromOrderService;
use App\Services\ServiceVehiclePriceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Throwable;

class OrderController extends Controller
{
    private function createOrderFromCart(array $carrito, ServiceVehiclePriceResolver $priceResolver): Order
    {
        $resolvedItems = $this->resolveCartItems($carrito, $priceResolver);
        $total = collect($resolvedItems)->sum(fn ($item) => $item['price'] * $item['quantity']);
        $order = Order::create($this->buildOrderData((float) $total, Auth::id(), false));

        foreach ($resolvedItems as $item) {
            $model = $item['model'];
            OrderItem::create([
                'order_id' => $order->id,
                'itemable_type' => get_class($model),
                'itemable_id' => $model->id,
                'catalog_item_variant_id' => $item['variant_id'] ?? null,
                'vehicle_id' => $item['vehicle_id'],
                'vehicle_type_id' => $item['vehicle_type_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
            ]);
        }

        return $order;
    }

    private function buildPayphoneAmounts(int $totalCents): array
    {
        $taxPercent = (int) config('services.payphone.tax', 0);
        $amountWithTax = 0;      // Base imponible
        $amountWithoutTax = $totalCents; // Base no imponible
        $taxAmount = 0;          // Valor de impuesto en centavos

        if ($taxPercent > 0) {
            // Asumimos que el total incluye impuesto sobre toda la base imponible.
            $amountWithTax = (int) round($totalCents / (1 + ($taxPercent / 100)));
            $taxAmount = max(0, $totalCents - $amountWithTax);
            $amountWithoutTax = 0;
        }

        return [
            'tax_percent' => $taxPercent,
            'amount' => $totalCents,
            'amount_with_tax' => $amountWithTax,
            'amount_without_tax' => $amountWithoutTax,
            'tax' => $taxAmount,
        ];
    }

    private function buildOrderData(float $total, ?int $userId, bool $isReservation = false): array
    {
        $data = [
            'user_id' => $userId,
            'total' => $total,
            'status' => $isReservation ? 'reserved' : 'pending',
        ];

        if (Schema::hasColumn('orders', 'work_status')) {
            $data['work_status'] = Order::WORK_PENDING;
        }

        if (Schema::hasColumn('orders', 'order_type')) {
            $data['order_type'] = $isReservation ? 'reservation' : 'purchase';
        } else {
            $data['status'] = 'pending';
        }

        return $data;
    }

    public function checkout(Request $request)
    {
        $carrito = (array) $request->session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index');
        }

        $total = collect($carrito)->sum(fn($item) => $item['price'] * $item['quantity']);

        return view('checkout.index', compact('carrito', 'total'));
    }

    public function store(Request $request, ServiceVehiclePriceResolver $priceResolver)
    {
        $carrito = (array) $request->session()->get('carrito', []);

        if (empty($carrito)) {
            return redirect()->route('carrito.index');
        }

        try {
            $order = $this->createOrderFromCart($carrito, $priceResolver);
        } catch (ValidationException $exception) {
            return redirect()->route('carrito.index')->withErrors($exception->errors());
        }

        $request->session()->put('current_order_id', $order->id);

        $payphoneResponse = $this->prepararPagoPayphone($order);

        if (!$payphoneResponse || !isset($payphoneResponse['payWithCard'])) {
            Log::error('Payphone error', ['order_id' => $order->id, 'response' => $payphoneResponse]);
            $order->update(['status' => 'failed']);

            return redirect()->route('carrito.index')
                ->with('error', 'No se pudo conectar con el sistema de pago. Intenta de nuevo.');
        }

        $order->update([
            'payphone_transaction_id' => $payphoneResponse['paymentId'] ?? null,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'payphone_ref' => $payphoneResponse['paymentId'] ?? null,
            'amount' => $order->total,
            'status' => 'pending',
            'response_payload' => array_merge($payphoneResponse, ['source' => 'payphone_button']),
            'client_transaction_id' => $payphoneResponse['clientTransactionId'] ?? null,
        ]);

        return redirect()->away($payphoneResponse['payWithCard']);
    }

    public function prepareBox(Request $request, ServiceVehiclePriceResolver $priceResolver)
    {
        $carrito = (array) $request->session()->get('carrito', []);

        if (empty($carrito)) {
            return response()->json([
                'ok' => false,
                'message' => 'El carrito esta vacio.',
            ], 422);
        }

        if (!config('services.payphone.box_token') || !config('services.payphone.store_id')) {
            Log::error('PayPhone Box no configurado.', [
                'has_box_token' => (bool) config('services.payphone.box_token'),
                'has_store_id' => (bool) config('services.payphone.store_id'),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'PayPhone no esta configurado correctamente.',
            ], 500);
        }

        try {
            $order = $this->createOrderFromCart($carrito, $priceResolver);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'No hay stock suficiente.',
            ], 422);
        } catch (Throwable $exception) {
            Log::error('No se pudo preparar PayPhone Box.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo preparar el pago. Revisa el carrito e intenta nuevamente.',
            ], 500);
        }
        $totalCents = (int) round($order->total * 100);
        $amounts = $this->buildPayphoneAmounts($totalCents);
        $clientTransactionId = 'order-' . $order->id . '-' . Str::uuid()->toString();

        $request->session()->put('current_order_id', $order->id);

        Transaction::create([
            'order_id' => $order->id,
            'payphone_ref' => null,
            'amount' => $order->total,
            'status' => 'pending',
            'response_payload' => [
                'source' => 'payphone_box',
                'created_at' => now()->toISOString(),
            ],
            'client_transaction_id' => $clientTransactionId,
        ]);

        return response()->json([
            'ok' => true,
            'token' => config('services.payphone.box_token'),
            'storeId' => config('services.payphone.store_id'),
            'clientTransactionId' => $clientTransactionId,
            'amount' => $amounts['amount'],
            'amountWithoutTax' => $amounts['amount_without_tax'],
            'amountWithTax' => $amounts['amount_with_tax'],
            'tax' => $amounts['tax'],
            'currency' => (string) config('services.payphone.currency', 'USD'),
            'reference' => 'Orden #' . $order->id . ' - ' . config('app.name'),
            'timeZone' => (int) config('services.payphone.timezone', -5),
        ]);
    }

    private function prepararPagoPayphone(Order $order): ?array
    {
        $currency = (string) config('services.payphone.currency', 'USD');
        $amounts = $this->buildPayphoneAmounts((int) round($order->total * 100));
        $clientTransactionId = 'order-' . $order->id . '-' . Str::uuid()->toString();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.payphone.token'),
                'Content-Type' => 'application/json',
            ])->post(config('services.payphone.base_url') . '/api/button/Prepare', [
                'amount' => $amounts['amount'],
                'amountWithTax' => $amounts['amount_with_tax'],
                'amountWithoutTax' => $amounts['amount_without_tax'],
                'tax' => $amounts['tax'],
                'clientTransactionId' => $clientTransactionId,
                'storeId' => config('services.payphone.store_id'),
                'responseUrl' => route('transaccion.exitosa'),
                'cancellationUrl' => route('payphone.cancel'),
                'currency' => $currency,
                'reference' => 'Orden #' . $order->id . ' - ' . config('app.name'),
            ]);

            if ($response->successful()) {
                $payload = $response->json();
                $payload['clientTransactionId'] = $clientTransactionId;
                return $payload;
            }

            Log::error('Payphone prepare failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $order->id,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Payphone exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);
        $order->load('items.itemable', 'items.variant', 'items.vehicle.specification.brand', 'items.vehicle.specification.model', 'items.vehicle.specification.type', 'items.vehicleType', 'transaction', 'user', 'assignedTo', 'sale.payments');
        $this->inheritSaleWorker($order);
        $workers = $this->workers();

        if (request()->routeIs('admin.orders.show')) {
            return view('admin.orders.show', compact('order', 'workers'));
        }

        return redirect()->route('orden.confirmacion', $order);
    }

    public function confirmacion(Order $order)
    {
        $order->load('items.itemable', 'items.variant', 'items.vehicle.specification.brand', 'items.vehicle.specification.model', 'items.vehicle.specification.type', 'items.vehicleType', 'transaction');
        $receipt = app(CheckoutReceiptService::class)->build($order);

        return view('checkout.confirmacion', ['order' => $order, ...$receipt]);
    }

    public function comprobante(Order $order)
    {
        $order->load('items.itemable', 'items.variant', 'items.vehicle.specification.brand', 'items.vehicle.specification.model', 'items.vehicle.specification.type', 'items.vehicleType', 'transaction', 'user');
        $receipt = app(CheckoutReceiptService::class)->build($order);

        return view('checkout.comprobante', ['order' => $order, ...$receipt]);
    }

    public function descargarComprobante(Order $order)
    {
        $order->load('items.itemable', 'items.variant', 'items.vehicle.specification.brand', 'items.vehicle.specification.model', 'items.vehicle.specification.type', 'items.vehicleType', 'transaction', 'user');
        $receipt = app(CheckoutReceiptService::class)->build($order);
        $fileName = 'comprobante-' . $receipt['orderCode'] . '.png';

        try {
            $html = $this->makeReceiptHtml($order, $receipt);
            $screenshot = $this->makeReceiptScreenshot($html);
        } catch (Throwable $exception) {
            Log::error('No se pudo generar el comprobante descargable.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('orden.comprobante', $order)
                ->with('error', 'No se pudo descargar la imagen del comprobante. Puedes guardar esta vista como captura.');
        }

        return response($screenshot, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function makeReceiptHtml(Order $order, array $receipt): string
    {
        $receipt['inlineStyles'] = $this->receiptInlineStyles();
        $receipt['receiptLogoUrl'] = $this->receiptLogoDataUri($receipt['empresa'] ?? null)
            ?: ($receipt['empresa']?->logo_url ?? null);

        return view('checkout.comprobante', ['order' => $order, ...$receipt])->render();
    }

    private function receiptInlineStyles(): string
    {
        $styles = [
            resource_path('scss/checkout-comprobante.scss'),
        ];

        return collect($styles)
            ->filter(fn (string $path) => is_file($path))
            ->map(fn (string $path) => file_get_contents($path))
            ->implode("\n");
    }

    private function receiptLogoDataUri($empresa): ?string
    {
        $path = null;

        if ($empresa?->logo && Storage::disk('public')->exists($empresa->logo)) {
            $path = Storage::disk('public')->path($empresa->logo);
        } elseif (is_file(public_path('Images/empresa-logo.jpg'))) {
            $path = public_path('Images/empresa-logo.jpg');
        }

        if (!$path || !is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    private function makeReceiptScreenshot(string $html): string
    {
        $browser = Browsershot::html($html)
            ->windowSize(820, 1200)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle(false)
            ->timeout(60)
            ->noSandbox()
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
            ]);

        if ($nodeBinary = config('services.browsershot.node_binary')) {
            $browser->setNodeBinary($nodeBinary);
        }

        if ($npmBinary = config('services.browsershot.npm_binary')) {
            $browser->setNpmBinary($npmBinary);
        }

        if ($chromePath = config('services.browsershot.chrome_path')) {
            $browser->setChromePath($chromePath);
        }

        return $browser->screenshot();
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $workStatus = trim((string) $request->query('work_status', ''));
        $assignedTo = trim((string) $request->query('assigned_to', ''));
        $dateFilter = trim((string) $request->query('date_filter', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $hasWorkStatus = Schema::hasColumn('orders', 'work_status');
        $workers = $this->workers();
        $stats = [
            'total' => Order::query()->count('*'),
            'pending' => $hasWorkStatus
                ? Order::query()->where('work_status', '=', Order::WORK_PENDING)->count('*')
                : Order::query()->where('status', '=', 'pending')->count('*'),
            'in_process' => $hasWorkStatus
                ? Order::query()->where('work_status', '=', Order::WORK_IN_PROGRESS)->count('*')
                : 0,
            'paid' => Order::query()->where('status', '=', 'paid')->count('*'),
        ];

        $orders = Order::with('user', 'items')
            ->with('assignedTo', 'sale')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery->where('id', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");

                    if (Schema::hasColumn('orders', 'order_type')) {
                        $subQuery->orWhere('order_type', 'like', "%{$search}%");
                    }

                    $subQuery->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->when($hasWorkStatus && $workStatus !== '', fn (Builder $query) => $query->where('work_status', '=', $workStatus))
            ->when($assignedTo !== '', fn (Builder $query) => $query->where('assigned_to', '=', $assignedTo))
            ->when($dateFilter !== '', function (Builder $query) use ($dateFilter, $dateFrom, $dateTo) {
                $query->where(function (Builder $dateQuery) use ($dateFilter, $dateFrom, $dateTo) {
                    match ($dateFilter) {
                        'today' => $this->applyAgendaDate($dateQuery, now()->toDateString()),
                        'tomorrow' => $this->applyAgendaDate($dateQuery, now()->addDay()->toDateString()),
                        'week' => $this->applyAgendaRange($dateQuery, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()),
                        'range' => $this->applyAgendaRange($dateQuery, $dateFrom, $dateTo),
                        default => null,
                    };
                });
            })
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'workStatus', 'assignedTo', 'dateFilter', 'dateFrom', 'dateTo', 'workers', 'stats'));
    }

    public function updateWorkStatus(Request $request, Order $order)
    {
        if (!Schema::hasColumn('orders', 'work_status')) {
            return redirect()->back()->with('error', 'Ejecuta las migraciones pendientes para activar estados operativos.');
        }

        $status = (string) $request->input('work_status');
        $allowed = $order->workTransitions();

        if (!array_key_exists($status, $allowed)) {
            return redirect()->back()->with('error', 'No se puede aplicar ese cambio de estado a esta orden.');
        }

        if ($status === Order::WORK_COMPLETED && $order->status !== 'paid') {
            return redirect()->back()->with('error', 'Primero registra el cobro para completar la orden.');
        }

        $timestampColumn = match ($status) {
            Order::WORK_ARRIVED => 'arrived_at',
            Order::WORK_IN_PROGRESS => 'started_at',
            Order::WORK_READY => 'ready_at',
            Order::WORK_COMPLETED => 'completed_at',
            Order::WORK_CANCELLED => 'cancelled_at',
            default => null,
        };

        $payload = [
            'work_status' => $status,
        ];

        if ($timestampColumn) {
            $payload[$timestampColumn] = now();
        }

        if ($status === Order::WORK_IN_PROGRESS && !$order->assigned_to) {
            $payload['assigned_to'] = Auth::id();
        }

        $order->update($payload);

        return redirect()->back()->with('success', 'Estado operativo actualizado.');
    }

    public function updateOperationalDetails(Request $request, Order $order)
    {
        $workers = $this->workers();
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::in($workers->pluck('id')->all())],
            'scheduled_at' => ['nullable', 'date'],
            'work_notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $order->loadMissing('sale');
        $assignedTo = !empty($data['assigned_to'])
            ? (int) $data['assigned_to']
            : ($order->assigned_to ?: $order->sale?->attended_by);
        $scheduledAt = !empty($data['scheduled_at'])
            ? $data['scheduled_at']
            : ($order->scheduled_at ?: now());

        $order->update([
            'assigned_to' => $assignedTo,
            'scheduled_at' => $scheduledAt,
            'work_notes' => $data['work_notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Datos operativos actualizados.');
    }

    private function applyAgendaDate(Builder $query, string $date): void
    {
        $query->whereDate('scheduled_at', $date)
            ->orWhere(function (Builder $fallbackQuery) use ($date) {
                $fallbackQuery->whereNull('scheduled_at')
                    ->whereDate('created_at', $date);
            });
    }

    private function applyAgendaRange(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        if (!$dateFrom && !$dateTo) {
            return;
        }

        if ($dateFrom) {
            $query->where(function (Builder $rangeQuery) use ($dateFrom) {
                $rangeQuery->whereDate('scheduled_at', '>=', $dateFrom)
                    ->orWhere(function (Builder $fallbackQuery) use ($dateFrom) {
                        $fallbackQuery->whereNull('scheduled_at')
                            ->whereDate('created_at', '>=', $dateFrom);
                    });
            });
        }

        if ($dateTo) {
            $query->where(function (Builder $rangeQuery) use ($dateTo) {
                $rangeQuery->whereDate('scheduled_at', '<=', $dateTo)
                    ->orWhere(function (Builder $fallbackQuery) use ($dateTo) {
                        $fallbackQuery->whereNull('scheduled_at')
                            ->whereDate('created_at', '<=', $dateTo);
                    });
            });
        }
    }

    public function reservarCatalogo(Request $request, ServiceVehiclePriceResolver $priceResolver)
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'item_type' => ['required', Rule::in(['catalog'])],
            'vehicle_id' => ['nullable', 'integer'],
            'vehicle_type_id' => ['nullable', 'integer'],
            'vehicle_specification_id' => ['nullable', 'integer'],
        ]);

        /** @var CatalogItem|null $model */
        $model = CatalogItem::query()
            ->with([
                'type',
                'category',
                'activeVariants',
                'vehicleTypePrices.vehicleType',
                'vehicleTypePrices.vehicleSpecification.brand',
                'vehicleTypePrices.vehicleSpecification.model',
                'vehicleTypePrices.vehicleSpecification.type',
            ])
            ->where('active', '=', true)
            ->whereHas('type', fn (Builder $query) => $query->where('business_model', CatalogType::BUSINESS_MODEL_SERVICES))
            ->find($data['item_id']);

        if (!$model) {
            return response()->json(['message' => 'El item seleccionado no esta disponible.'], 404);
        }

        $vehicleContext = $priceResolver->resolve(
            $model,
            $request->integer('vehicle_id') ?: null,
            $request->integer('vehicle_specification_id') ?: null,
            Auth::id(),
            $request->integer('vehicle_type_id') ?: null
        );
        $reservationPrice = $vehicleContext['price'];
        $order = Order::create($this->buildOrderData($reservationPrice, Auth::id(), true));

        OrderItem::create([
            'order_id' => $order->id,
            'itemable_type' => get_class($model),
            'itemable_id' => $model->id,
            'vehicle_id' => $vehicleContext['vehicle_id'],
            'vehicle_type_id' => $vehicleContext['vehicle_type_id'],
            'quantity' => 1,
            'unit_price' => $reservationPrice,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Reserva creada exitosamente.',
            'order_id' => $order->id,
        ]);
    }

    private function resolveCartItems(array $cart, ServiceVehiclePriceResolver $priceResolver): array
    {
        $resolved = [];

        foreach ($cart as $item) {
            if ((string) data_get($item, 'type') !== 'catalog') {
                continue;
            }

            $model = CatalogItem::query()
                ->with([
                    'type',
                    'vehicleTypePrices.vehicleType',
                    'vehicleTypePrices.vehicleSpecification.brand',
                    'vehicleTypePrices.vehicleSpecification.model',
                    'vehicleTypePrices.vehicleSpecification.type',
                ])
                ->where('active', '=', true)
                ->where('purchasable', '=', true)
                ->find((int) data_get($item, 'id'));

            if (!$model) {
                continue;
            }

            $isService = ($model->type?->business_model ?? \App\Models\CatalogType::BUSINESS_MODEL_SERVICES)
                === \App\Models\CatalogType::BUSINESS_MODEL_SERVICES;
            $variant = null;
            $vehicleContext = [
                'price' => 0,
                'vehicle_id' => null,
                'vehicle_type_id' => null,
                'vehicle_specification_id' => null,
            ];

            if ($isService) {
                $vehicleContext = $priceResolver->resolve(
                    $model,
                    data_get($item, 'vehicle_id') ? (int) data_get($item, 'vehicle_id') : null,
                    data_get($item, 'vehicle_specification_id') ? (int) data_get($item, 'vehicle_specification_id') : null,
                    Auth::id(),
                    data_get($item, 'vehicle_type_id') ? (int) data_get($item, 'vehicle_type_id') : null
                );
            } else {
                $variant = CatalogItemVariant::query()
                    ->where('catalog_item_id', '=', $model->id)
                    ->where('active', '=', true)
                    ->find((int) data_get($item, 'variant_id'));

                if (!$variant) {
                    throw ValidationException::withMessages([
                        'cart' => 'Uno de los productos ya no tiene una presentacion activa disponible.',
                    ]);
                }

                $quantity = max(1, (int) data_get($item, 'quantity', 1));

                if ($model->uses_inventory && (int) ($variant->stock ?? 0) < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "No hay stock suficiente para {$model->name}.",
                    ]);
                }
            }

            $resolved[] = [
                'model' => $model,
                'variant_id' => $variant?->id,
                'quantity' => max(1, (int) data_get($item, 'quantity', 1)),
                'price' => $isService ? $vehicleContext['price'] : (float) ($variant->price ?? 0),
                'vehicle_id' => $vehicleContext['vehicle_id'],
                'vehicle_type_id' => $vehicleContext['vehicle_type_id'],
            ];
        }

        return $resolved;
    }


    public function marcarPagada(Request $request, Order $order, CreateSaleFromOrderService $createSaleFromOrderService)
    {
        if ($order->status === 'paid') {
            $createSaleFromOrderService->create($order);

            return redirect()->back()->with('success', 'La orden ya estaba marcada como pagada. Venta comercial verificada.');
        }

        if (($order->work_status ?? Order::WORK_PENDING) !== Order::WORK_READY) {
            return redirect()->back()->with('error', 'La orden debe estar lista antes de registrar el cobro.');
        }

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card', 'payphone'])],
            'received_amount' => ['required', 'numeric', 'min:' . (float) $order->total],
            'payment_bank' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('payment_method') === 'transfer')],
            'payment_reference' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('payment_method') === 'transfer')],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:4096', Rule::requiredIf($request->input('payment_method') === 'transfer')],
        ], [
            'received_amount.min' => 'El monto recibido no puede ser menor al total de la orden.',
            'payment_bank.required_if' => 'Ingresa el banco de la transferencia.',
            'payment_reference.required_if' => 'Ingresa la referencia de la transferencia.',
            'payment_proof.required_if' => 'Adjunta el comprobante de la transferencia.',
            'payment_proof.mimes' => 'El comprobante debe ser una imagen o PDF.',
            'payment_proof.max' => 'El comprobante no debe superar los 4 MB.',
        ]);

        $proofPath = $request->file('payment_proof')?->store('sale-payment-proofs', 'public');

        DB::transaction(function () use ($order, $data, $proofPath) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $receivedAmount = round((float) $data['received_amount'], 2);
            $changeAmount = round(max(0, $receivedAmount - (float) $lockedOrder->total), 2);
            $paymentPayload = [
                'source' => 'manual_admin',
                'payment_method' => $data['payment_method'],
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
                'bank' => $data['payment_bank'] ?? null,
                'reference' => $data['payment_reference'] ?: null,
                'proof_path' => $proofPath,
            ];

            $approvedTransaction = $lockedOrder->transactions()->where('status', 'approved')->latest('id')->first();

            if (!$approvedTransaction) {
                Transaction::create([
                    'order_id' => $lockedOrder->id,
                    'payphone_ref' => $data['payment_method'] === 'payphone'
                        ? ($data['payment_reference'] ?: null)
                        : null,
                    'amount' => $lockedOrder->total,
                    'status' => 'approved',
                    'response_payload' => $paymentPayload,
                    'client_transaction_id' => 'manual-order-' . $lockedOrder->id . '-' . now()->timestamp,
                ]);
            } else {
                $approvedTransaction->update([
                    'payphone_ref' => $data['payment_method'] === 'payphone'
                        ? ($data['payment_reference'] ?: $approvedTransaction->payphone_ref)
                        : $approvedTransaction->payphone_ref,
                    'response_payload' => array_filter(array_merge(
                        $approvedTransaction->response_payload ?? [],
                        $paymentPayload
                    ), fn ($value) => $value !== null && $value !== ''),
                ]);
            }

            $lockedOrder->update([
                'status' => 'paid',
                'work_status' => Order::WORK_COMPLETED,
                'completed_at' => now(),
            ]);
        });

        $createSaleFromOrderService->create($order->fresh());

        return redirect()->back()->with('success', 'Cobro registrado. La orden y la venta quedaron completadas.');
    }

    public function destroy(Order $order)
    {
        Transaction::query()
            ->where('order_id', '=', $order->id)
            ->delete();

        Order::query()
            ->whereKey($order->id)
            ->delete();

        return redirect()->route('admin.orders.index')->with('success', 'Orden eliminada correctamente.');
    }

    private function inheritSaleWorker(Order $order): void
    {
        if ($order->assigned_to || !$order->sale?->attended_by) {
            return;
        }

        $order->update(['assigned_to' => $order->sale->attended_by]);
        $order->load('assignedTo');
    }

    private function workers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['admin', 'empleado']))
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);
    }
}
