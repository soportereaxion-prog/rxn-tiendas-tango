# Incremento de Timeout de API Cliente

**Fecha:** 2026-03-31 05:59
**Módulo:** Core / Sincronización
**Responsable:** Agente

## Descripción
Se detectó un error al intentar sincronizar el directorio de CRM (específicamente la carga inicial/completa de clientes y artículos) debido a un límite restrictivo de 30 segundos en la capa HTTP. La petición agotaba su tiempo con grandes volúmenes de datos (`Operation timed out after 30010 milliseconds`).

## Decisiones
1. Aumentar el `CURLOPT_TIMEOUT` en `app/Infrastructure/Http/ApiClient.php` de 30 a 120 segundos.
2. Esta cifra es conservadora pero prudente para asegurar el procesamiento de bases con ~4000 o más registros (como clientes), especialmente en integraciones CRM y Tango, sin mantener abierta una conexión por tiempo ilimitado.

## Archivos modificados
- `app/Infrastructure/Http/ApiClient.php`
