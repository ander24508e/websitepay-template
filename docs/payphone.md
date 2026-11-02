# PayPhone

- Configurar PAYPHONE_TOKEN para el boton y PAYPHONE_BOX_TOKEN para la cajita si usa otra aplicacion. Si se omite el segundo, utiliza PAYPHONE_TOKEN. Confirm debe usar el token de la aplicacion que preparo el cobro. No cambiar credenciales con pagos pendientes.
- Registrar el dominio de cada empresa (por ejemplo https://empresa.condoresoftware.com) y su responseUrl https://empresa.condoresoftware.com/transaccion-exitosa en PayPhone Developers. APP_URL debe coincidir con ese dominio.
- PAYPHONE_TAX_PERCENT representa IVA incluido en todo el total. Con 15 %, $11.50 se envia como amount=1150, amountWithTax=1000, tax=150, amountWithoutTax=0. El modelo actual no desglosa carritos con tarifas mixtas.
- Cada transaccion guarda source en response_payload. No se prueba un segundo endpoint cuando falla Confirm. Transacciones antiguas del boton se reconocen por payWithCard; un origen desconocido permanece pendiente.
- Solo Confirm puede aprobar o cancelar. Se guardan transactionId y authorizationCode en response_payload, y transactionId tambien en payphone_ref y en la orden aprobada. La aprobacion se guarda en una transaccion de base de datos con bloqueos; la venta se crea despues de confirmarla, para que un fallo al crearla no deshaga un cobro ya confirmado. Si falla, queda en el log ("PayPhone aprobado sin venta creada") y se reintenta al recargar el retorno o con "Registrar cobro" en el panel.
- Si Confirm falla, la pagina reintenta cada cinco segundos durante cinco minutos desde el primer retorno, mientras siga abierta. Tambien permite reintentar manualmente; cerrar la pagina detiene los reintentos. La orden permanece pending si no hay una respuesta valida. No existe confirmacion en segundo plano sin el retorno del cliente.
- PayPhone exige confirmar dentro de cinco minutos del pago; el formulario vence a los diez minutos. Un fallo de verificacion no demuestra un reverso: consultar el estado antes de volver a cobrar. El reverso manual solo esta disponible el mismo dia.

Documentacion oficial: https://docs.payphone.app/cajita-de-pagos y https://docs.payphone.app/boton-de-pago

Pruebas locales (SQLite habilitado solo para este proceso):

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --filter=PayphoneConfirmationTest
```
