<?php

use App\Http\Controllers\Admin\ClientesController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\InventoryOperationsController;
use App\Http\Controllers\Admin\UsuariosController;
use App\Http\Controllers\Admin\VehicleSpecificationsController;
use App\Http\Controllers\Admin\VehiculosController;
use App\Http\Controllers\Admin\VentasController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\CatalogCategoryController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\CatalogItemVariantController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CatalogTypeController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceVehicleTypePriceController;
use App\Http\Controllers\TransactionController;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Services\CheckoutReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Rutas publicas
Route::get('/', [CatalogoController::class, 'index'])->name('home');
Route::redirect('/catalogo', '/');
Route::get('/catalogo/buscar', [CatalogoController::class, 'buscar'])->name('catalogo.buscar');

// Carrito publico
Route::get('/carrito', [CarritoController::class, 'index'])->name('carrito.index');
Route::post('/carrito/agregar', [CarritoController::class, 'agregar'])->name('carrito.agregar');
Route::patch('/carrito/actualizar/{id}', [CarritoController::class, 'actualizar'])->name('carrito.actualizar');
Route::delete('/carrito/quitar/{id}', [CarritoController::class, 'quitar'])->name('carrito.quitar');
Route::delete('/carrito/limpiar', [CarritoController::class, 'limpiar'])->name('carrito.limpiar');

// Checkout y pagos publicos (invitado o autenticado)
Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
Route::post('/orden/crear', [OrderController::class, 'store'])->name('orden.store');
Route::post('/orden/cajita', [OrderController::class, 'prepareBox'])->name('orden.cajita');
Route::post('/reservas/catalogo', [OrderController::class, 'reservarCatalogo'])->name('reservas.catalogo');
Route::get('/orden/{order}/confirmacion', [OrderController::class, 'confirmacion'])->name('orden.confirmacion');
Route::get('/orden/{order}/comprobante', [OrderController::class, 'comprobante'])->name('orden.comprobante');
Route::get('/orden/{order}/comprobante/descargar', [OrderController::class, 'descargarComprobante'])->name('orden.comprobante.descargar');
Route::get('/transaccion-exitosa', [TransactionController::class, 'success'])->name('transaccion.exitosa');
Route::get('/payphone/success', [TransactionController::class, 'success'])->name('payphone.success');
Route::get('/payphone/cancel', [TransactionController::class, 'cancel'])->name('payphone.cancel');

if (app()->environment('local')) {
    Route::get('/dev/preview/confirmacion', function () {
        $order = Order::query()
            ->with(['items.itemable', 'transaction', 'user'])
            ->latest()
            ->first();

        if (! $order) {
            $order = new Order([
                'user_id' => null,
                'total' => 15.00,
                'status' => 'paid',
                'order_type' => 'purchase',
            ]);
            $order->id = 999999;
            $order->created_at = now();
            $order->updated_at = now();

            $item = new OrderItem([
                'quantity' => 1,
                'unit_price' => 15.00,
            ]);
            $item->setRelation('itemable', new CatalogItem(['name' => 'Lavada Completa']));

            $transaction = new Transaction([
                'payphone_ref' => 'PREVIEW-'.now()->format('YmdHis'),
                'amount' => 15.00,
                'status' => 'approved',
                'client_transaction_id' => 'preview-'.Str::uuid(),
            ]);

            $order->setRelation('items', collect([$item]));
            $order->setRelation('transaction', $transaction);
            $order->setRelation('user', auth()->user());
        }

        $receipt = app(CheckoutReceiptService::class)->build($order);

        return view('checkout.confirmacion', ['order' => $order, ...$receipt]);
    })->name('dev.preview.confirmacion');

    Route::get('/dev/preview/comprobante', function () {
        $order = Order::query()
            ->with(['items.itemable', 'transaction', 'user'])
            ->latest()
            ->first();

        if (! $order) {
            abort(404, 'Crea una orden o usa primero la previsualizacion de confirmacion.');
        }

        $receipt = app(CheckoutReceiptService::class)->build($order);

        return view('checkout.comprobante', ['order' => $order, ...$receipt]);
    })->name('dev.preview.comprobante');
}

// Redireccion post-login segun rol
Route::get('/dashboard', function () {
    if (Auth::user()->hasAnyRole(['admin', 'gerente', 'empleado']) && Auth::user()->can('dashboard.view')) {
        return redirect()->route('admin.dashboard');
    }

    if (Auth::user()->can('sales.create')) {
        return redirect()->route('admin.ventas.create');
    }

    if (Auth::user()->can('users.create_employees')) {
        return redirect()->route('admin.usuarios.create');
    }

    if (Auth::user()->can('banners.create')) {
        return redirect()->route('admin.banners.create');
    }

    return redirect()->route('home');
})->middleware(['auth', 'active'])->name('dashboard');

// Rutas autenticadas
Route::middleware(['auth', 'active'])->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/account', [ProfileController::class, 'updateAccount'])->name('profile.account.update');
    Route::patch('/profile/security', [ProfileController::class, 'updateSecurity'])->name('profile.security.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Detalle de orden para usuarios autenticados
    Route::get('/orden/{order}', [OrderController::class, 'show'])->name('orden.show');
});

// Panel cliente
Route::middleware(['auth', 'active', 'role:cliente'])
    ->prefix('/customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/compras', [ClienteController::class, 'compras'])->name('compras');
    });

// Panel admin
Route::middleware(['auth', 'active', 'role:admin|gerente|empleado'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
        Route::get('/ventas/pagos', [TransactionController::class, 'index'])->middleware('permission:transactions.view')->name('transactions.index');
        Route::get('/ventas/pagos/{transaction}', [TransactionController::class, 'show'])->middleware('permission:transactions.view')->name('transactions.show');
        Route::get('/ventas', [VentasController::class, 'index'])->middleware('permission:sales.view')->name('ventas.index');
        Route::get('/ventas/create', [VentasController::class, 'create'])->middleware('permission:sales.create')->name('ventas.create');
        Route::post('/ventas', [VentasController::class, 'store'])->middleware('permission:sales.create')->name('ventas.store');
        Route::get('/ventas/{venta}', [VentasController::class, 'show'])->middleware('permission:sales.view')->name('ventas.show');
        Route::get('/ventas/{venta}/edit', [VentasController::class, 'edit'])->middleware('permission:sales.update')->name('ventas.edit');
        Route::match(['put', 'patch'], '/ventas/{venta}', [VentasController::class, 'update'])->middleware('permission:sales.update')->name('ventas.update');
        Route::delete('/ventas/{venta}', [VentasController::class, 'destroy'])->middleware('permission:sales.delete')->name('ventas.destroy');
        Route::post('/clientes/quick-store', [ClientesController::class, 'quickStore'])->middleware('permission:clients.manage')->name('clientes.quick-store');
        Route::resource('/clientes', ClientesController::class)
            ->parameters(['clientes' => 'cliente'])
            ->only(['index', 'show'])->middleware('permission:clients.view');
        Route::resource('/clientes', ClientesController::class)
            ->parameters(['clientes' => 'cliente'])
            ->only(['create', 'store', 'edit', 'update', 'destroy'])->middleware('permission:clients.manage');
        Route::post('/vehiculos/quick-store', [VehiculosController::class, 'quickStore'])->middleware('permission:vehicles.manage')->name('vehiculos.quick-store');
        Route::get('/vehiculos/especificaciones', [VehicleSpecificationsController::class, 'index'])
            ->middleware('permission:vehicles.view')->name('vehiculos.specifications.index');
        Route::post('/vehiculos/especificaciones/relaciones', [VehicleSpecificationsController::class, 'storeSpecification'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.store');
        Route::put('/vehiculos/especificaciones/relaciones/{vehicleSpecification}', [VehicleSpecificationsController::class, 'updateSpecification'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.update');
        Route::delete('/vehiculos/especificaciones/relaciones/{vehicleSpecification}', [VehicleSpecificationsController::class, 'destroySpecification'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.destroy');
        Route::post('/vehiculos/especificaciones/tipos', [VehicleSpecificationsController::class, 'storeType'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.types.store');
        Route::put('/vehiculos/especificaciones/tipos/{vehicleType}', [VehicleSpecificationsController::class, 'updateType'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.types.update');
        Route::delete('/vehiculos/especificaciones/tipos/{vehicleType}', [VehicleSpecificationsController::class, 'destroyType'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.types.destroy');
        Route::post('/vehiculos/especificaciones/marcas', [VehicleSpecificationsController::class, 'storeBrand'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.brands.store');
        Route::put('/vehiculos/especificaciones/marcas/{vehicleBrand}', [VehicleSpecificationsController::class, 'updateBrand'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.brands.update');
        Route::delete('/vehiculos/especificaciones/marcas/{vehicleBrand}', [VehicleSpecificationsController::class, 'destroyBrand'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.brands.destroy');
        Route::post('/vehiculos/especificaciones/modelos', [VehicleSpecificationsController::class, 'storeModel'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.models.store');
        Route::put('/vehiculos/especificaciones/modelos/{vehicleModel}', [VehicleSpecificationsController::class, 'updateModel'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.models.update');
        Route::delete('/vehiculos/especificaciones/modelos/{vehicleModel}', [VehicleSpecificationsController::class, 'destroyModel'])
            ->middleware('permission:vehicles.manage')->name('vehiculos.specifications.models.destroy');
        Route::resource('/vehiculos', VehiculosController::class)
            ->parameters(['vehiculos' => 'vehiculo'])
            ->only(['index', 'show'])->middleware('permission:vehicles.view');
        Route::resource('/vehiculos', VehiculosController::class)
            ->parameters(['vehiculos' => 'vehiculo'])
            ->only(['create', 'store', 'edit', 'update', 'destroy'])->middleware('permission:vehicles.manage');
        Route::get('/usuarios', [UsuariosController::class, 'index'])->middleware('permission:users.view')->name('usuarios.index');
        Route::get('/usuarios/create', [UsuariosController::class, 'create'])->middleware('permission:users.create_employees|users.create_managers')->name('usuarios.create');
        Route::post('/usuarios', [UsuariosController::class, 'store'])->middleware('permission:users.create_employees|users.create_managers')->name('usuarios.store');
        Route::get('/usuarios/{usuario}', [UsuariosController::class, 'show'])->middleware('permission:users.view')->name('usuarios.show');
        Route::get('/usuarios/{usuario}/edit', [UsuariosController::class, 'edit'])->middleware('permission:users.update')->name('usuarios.edit');
        Route::match(['put', 'patch'], '/usuarios/{usuario}', [UsuariosController::class, 'update'])->middleware('permission:users.update')->name('usuarios.update');
        Route::delete('/usuarios/{usuario}', [UsuariosController::class, 'destroy'])->middleware('permission:users.deactivate')->name('usuarios.destroy');
        Route::get('/inventario', [InventarioController::class, 'index'])->middleware('permission:inventory.view')->name('inventario.index');
        Route::get('/inventario/exportar', [InventarioController::class, 'export'])->name('inventario.export');
        Route::get('/inventario/importar', [InventarioController::class, 'import'])->name('inventario.import');
        Route::post('/inventario/importar/preview', [InventarioController::class, 'previewImport'])->name('inventario.import.preview');
        Route::post('/inventario/importar', [InventarioController::class, 'storeImport'])->name('inventario.import.store');
        Route::get('/inventario/reportes', [InventoryOperationsController::class, 'reports'])->middleware('permission:inventory.view')->name('inventario.reports');
        Route::get('/inventario/reportes/exportar/{section}', [InventoryOperationsController::class, 'exportReport'])->name('inventario.reports.export');
        Route::get('/inventario/cierres', [InventoryOperationsController::class, 'periods'])->middleware('permission:inventory.view')->name('inventario.periods');
        Route::post('/inventario/cierres', [InventoryOperationsController::class, 'storePeriod'])->name('inventario.periods.store');
        Route::get('/inventario/ubicaciones', [InventoryOperationsController::class, 'locations'])->middleware('permission:inventory.view')->name('inventario.locations');
        Route::post('/inventario/ubicaciones', [InventoryOperationsController::class, 'storeLocation'])->name('inventario.locations.store');
        Route::get('/inventario/proveedores', [InventoryOperationsController::class, 'suppliers'])->middleware('permission:inventory.view')->name('inventario.suppliers');
        Route::post('/inventario/proveedores', [InventoryOperationsController::class, 'storeSupplier'])->name('inventario.suppliers.store');
        Route::get('/inventario/compras', [InventoryOperationsController::class, 'purchases'])->middleware('permission:inventory.view')->name('inventario.purchases');
        Route::post('/inventario/compras', [InventoryOperationsController::class, 'storePurchase'])->name('inventario.purchases.store');
        Route::get('/inventario/transferencias', [InventoryOperationsController::class, 'transfers'])->middleware('permission:inventory.view')->name('inventario.transfers');
        Route::post('/inventario/transferencias', [InventoryOperationsController::class, 'storeTransfer'])->name('inventario.transfers.store');
        Route::get('/inventario/devoluciones', [InventoryOperationsController::class, 'returns'])->middleware('permission:inventory.view')->name('inventario.returns');
        Route::post('/inventario/devoluciones', [InventoryOperationsController::class, 'storeReturn'])->name('inventario.returns.store');
        Route::get('/inventario/conteos', [InventoryOperationsController::class, 'counts'])->middleware('permission:inventory.view')->name('inventario.counts');
        Route::post('/inventario/conteos', [InventoryOperationsController::class, 'storeCount'])->name('inventario.counts.store');
        Route::get('/inventario/kardex/{variant}/exportar', [InventoryOperationsController::class, 'exportKardex'])->name('inventario.kardex.export');
        Route::get('/inventario/kardex/{variant}', [InventoryOperationsController::class, 'kardex'])->middleware('permission:inventory.view')->name('inventario.kardex');
        Route::get('/inventario/create', [InventarioController::class, 'create'])->name('inventario.create');
        Route::post('/inventario/movimientos', [InventarioController::class, 'storeMovement'])->name('inventario.movements.store');
        Route::get('/inventario/movimientos/{movement}/edit', [InventarioController::class, 'edit'])->name('inventario.movements.edit');
        Route::put('/inventario/movimientos/{movement}', [InventarioController::class, 'update'])->name('inventario.movements.update');
        Route::delete('/inventario/movimientos/{movement}', [InventarioController::class, 'destroy'])->name('inventario.movements.destroy');
        Route::view('/catalogo', 'admin.catalog.index')->middleware('permission:catalog.view')->name('catalog.index');
        Route::resource('/catalogo/tipos', CatalogTypeController::class)
            ->parameters(['tipos' => 'catalogType'])
            ->names('catalog-types')->middleware('permission:catalog.manage');
        Route::resource('/catalogo/categorias', CatalogCategoryController::class)
            ->parameters(['categorias' => 'catalogCategory'])
            ->names('catalog-categories')->middleware('permission:catalog.manage');
        Route::get('/catalogo/items/{catalogItem}/precios-vehiculo/create', [ServiceVehicleTypePriceController::class, 'create'])
            ->middleware('permission:catalog.manage')->name('catalog-service-prices.create');
        Route::post('/catalogo/items/{catalogItem}/precios-vehiculo', [ServiceVehicleTypePriceController::class, 'store'])
            ->middleware('permission:catalog.manage')->name('catalog-service-prices.store');
        Route::get('/catalogo/precios-vehiculo/{serviceVehicleTypePrice}/edit', [ServiceVehicleTypePriceController::class, 'edit'])
            ->middleware('permission:catalog.manage')->name('catalog-service-prices.edit');
        Route::put('/catalogo/precios-vehiculo/{serviceVehicleTypePrice}', [ServiceVehicleTypePriceController::class, 'update'])
            ->middleware('permission:catalog.manage')->name('catalog-service-prices.update');
        Route::delete('/catalogo/precios-vehiculo/{serviceVehicleTypePrice}', [ServiceVehicleTypePriceController::class, 'destroy'])
            ->middleware('permission:catalog.manage')->name('catalog-service-prices.destroy');
        Route::resource('/catalogo/items', CatalogItemController::class)
            ->parameters(['items' => 'catalogItem'])
            ->except(['index'])
            ->names('catalog-items')->middleware('permission:catalog.manage');
        Route::get('/catalogo/variantes', fn () => redirect()->route('admin.catalog-variants.index'));
        Route::get('/catalogo/variantes/create', fn (Request $request) => redirect()->route('admin.catalog-variants.create', $request->query()));
        Route::get('/catalogo/variantes/{catalogVariant}/edit', fn (Request $request, CatalogItemVariant $catalogVariant) => redirect()->route('admin.catalog-variants.edit', ['catalogVariant' => $catalogVariant] + $request->query()));
        Route::get('/catalogo/variantes/{catalogVariant}', fn (Request $request, CatalogItemVariant $catalogVariant) => redirect()->route('admin.catalog-variants.show', ['catalogVariant' => $catalogVariant] + $request->query()));
        Route::resource('/catalogo/presentaciones', CatalogItemVariantController::class)
            ->parameters(['presentaciones' => 'catalogVariant'])
            ->names('catalog-variants')->middleware('permission:catalog.manage');

        // Empresa
        Route::get('/empresa', [EmpresaController::class, 'edit'])->middleware('permission:company.view')->name('empresa.edit');
        Route::put('/empresa', [EmpresaController::class, 'update'])->middleware('permission:company.manage')->name('empresa.update');
        Route::delete('/empresa/logo', [EmpresaController::class, 'deleteLogo'])->middleware('permission:company.manage')->name('empresa.deleteLogo');

        // Landing Banners
        Route::get('/banners', [BannerController::class, 'index'])->middleware('permission:banners.view')->name('banners.index');
        Route::get('/banners/create', [BannerController::class, 'create'])->middleware('permission:banners.create')->name('banners.create');
        Route::post('/banners', [BannerController::class, 'store'])->middleware('permission:banners.create')->name('banners.store');
        Route::get('/banners/{banner}/edit', [BannerController::class, 'edit'])->middleware('permission:banners.update')->whereNumber('banner')->name('banners.edit');
        Route::match(['put', 'patch'], '/banners/{banner}', [BannerController::class, 'update'])->middleware('permission:banners.update')->whereNumber('banner')->name('banners.update');
        Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->middleware('permission:banners.delete')->whereNumber('banner')->name('banners.destroy');
        Route::get('/banners/{banner}', [BannerController::class, 'show'])->middleware('permission:banners.view')->whereNumber('banner')->name('banners.show');

        Route::resource('/orders', OrderController::class)->only(['index', 'show'])->middleware('permission:orders.view');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete')->name('orders.destroy');
        Route::patch('/orders/{order}/marcar-pagada', [OrderController::class, 'marcarPagada'])->middleware('permission:sales.collect')->name('orders.marcar-pagada');
        Route::patch('/orders/{order}/estado-operativo', [OrderController::class, 'updateWorkStatus'])->middleware('permission:orders.update')->name('orders.work-status');
        Route::patch('/orders/{order}/datos-operativos', [OrderController::class, 'updateOperationalDetails'])->middleware('permission:orders.update')->name('orders.operational-details');
    });

require __DIR__.'/auth.php';
