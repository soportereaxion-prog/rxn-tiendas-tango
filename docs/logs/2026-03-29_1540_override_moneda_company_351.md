# Override de moneda local para company 351

## Que se hizo
- Se agregó una tabla de overrides en `TangoOrderHeaderResolver` para mapear `tango_connect_company_id = 351` a `ID_MONEDA = 2` al momento de enviar pedidos.

## Por qué
- En el ambiente `000357-014 / company 351` la tabla de monedas de Tango tiene `Pesos` con `ID_MONEDA = 2` y `Dólares` con `ID_MONEDA = 1`, al revés que en otros tenants. La heurística `MONEDA_HABITUAL = 'C' → 1` no aplica ahí y provoca un rechazo inmediato.
- Hasta que Axoft libere el catálogo oficial (process 16660 u otro) preferimos un override explícito por company.

## Impacto
- PDS y pedidos web enviados contra company 351 ahora mandan `ID_MONEDA = 2` sin tocar la configuración del resto de las empresas.

## Deuda
- Reemplazar este override duro por la lectura del maestro real apenas tengamos el endpoint operativo.
