# Hotfix ID_MONEDA según MONEDA_HABITUAL de perfil

## Que se hizo
- Se incorporó en `TangoOrderHeaderResolver` una resolución simple: si el detalle del perfil expone `MONEDA_HABITUAL = 'C'` se envía `ID_MONEDA = 1` (moneda local/pesos). Cualquier otro flag distinto de vacío se mapea a `ID_MONEDA = 0` (moneda extranjera genérica), dejando como fallback final el valor histórico `1`.
- `TangoOrderMapper` ahora acepta valores cero para `ID_MONEDA`, evitando que el normalizador legacy descarte la moneda extranjera.

## Por que
- Los PDS seguían fallando con `ID_MONEDA = 1` porque el resolver nunca interpretaba la moneda declarada en el perfil.
- No tenemos todavía un endpoint operativo de Tango que devuelva `ID_MONEDA` literal (process 16660 responde "Action not found" en este ambiente), así que se documenta la suposición pedida por negocio: `MONEDA_HABITUAL = 'C'` corresponde a pesos.

## Impacto
- Se destraba el envío de PDS cuando la empresa trabaja en pesos.
- Para perfiles con moneda extranjera se envía `ID_MONEDA = 0`, alineado a la práctica bimonetaria que describió el usuario; queda registrado como heurística a revisar si Axoft expone los IDs reales a futuro.

## Deuda / pendiente
- Sigue pendiente obtener un catálogo oficial de monedas de Tango (process 16660 o equivalente) para reemplazar esta heurística por IDs verificados.
- También hay que estudiar los flags `COMPORTAMIENTO_MONEDA` para ver si corresponde forzar el valor o tomarlo del cliente en ciertos escenarios mixtos.
