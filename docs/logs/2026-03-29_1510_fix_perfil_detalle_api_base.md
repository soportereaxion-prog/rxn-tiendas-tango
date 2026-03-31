# Hotfix detalle de perfil Tango sin `/Api`

## Que se hizo
- `TangoOrderHeaderResolver` ahora normaliza la URL de Connect: si el valor guardado en Configuración no termina en `/Api`, se lo anexa automáticamente; si la URL viene vacía pero existe `tango_connect_key`, arma `https://{key}.connect.axoft.com/Api` igual que el validador visual.
- Esto hace que el resolver pueda consultar `GetById` del perfil incluso cuando el operador configuró únicamente `https://000357-014.connect.axoft.com` (sin sufijo `/Api`).

## Por qué
- En CRM había empresas que operaban con un Connect distinto (company 351) y cargaban la URL sin `/Api`. El validador visual funciona porque usa la llave para forzar el path correcto, pero el resolver de pedidos usaba la URL tal cual estaba y obtenía `NULL`, quedando los fallbacks legacy (`ID_GVA43_TALON_PED=6`, `ID_MONEDA=1`).
- Resultado visible: un PDS enviado desde ese tenant seguía rechazado con `ID_GVA43_TALON_PED = 6` o `ID_MONEDA = 1` aunque el perfil ya estuviera configurado.

## Impacto
- El detalle del perfil vuelve a responder con el talonario real (en la empresa 9001 ahora baja `ID_GVA43_TALON_PED = 46`).
- La heurística de moneda (`C => 1 / resto => 0`) ya se evalúa con el dato real del perfil, no con el fallback.

## Deuda
- Sigue pendiente contar con el catálogo oficial de monedas de Tango (process 16660 u otro) para reemplazar la heurística cuando Axoft lo habilite.
