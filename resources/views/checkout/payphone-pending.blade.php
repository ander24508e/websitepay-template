<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Confirmando pago</title></head>
<body>
    <h1>Estamos verificando tu pago</h1>
    <p>Tu orden sigue pendiente. No vuelvas a pagar mientras verificamos el resultado.</p>
    <p id="retry-status">Reintentaremos la confirmacion automaticamente durante cinco minutos.</p>
    <button type="button" onclick="window.location.reload()">Volver a verificar</button>
    <a href="{{ route('carrito.index') }}">Volver al carrito</a>
    <script>
        const key = 'payphone-confirm:' + new URLSearchParams(location.search).get('clientTransactionId')
            + ':' + new URLSearchParams(location.search).get('clientTxId') + ':' + new URLSearchParams(location.search).get('id');
        const started = Number(sessionStorage.getItem(key)) || Date.now();
        sessionStorage.setItem(key, String(started));
        if (Date.now() - started < 300000) {
            setTimeout(() => location.reload(), 5000);
        } else {
            document.getElementById('retry-status').textContent = 'No pudimos confirmar el pago. La orden continua pendiente. Consulta el estado con el comercio antes de intentar otro pago.';
        }
    </script>
</body>
</html>
