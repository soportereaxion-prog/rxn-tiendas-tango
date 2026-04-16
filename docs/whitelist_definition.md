# Definición de Whitelist (RXN - Sync)

Este documento certifica el mapeo autorizado (Whitelist) dictaminado para la inyección de Push asimétrica desde el CRM (`RXNAPP`) hacia Tango Connect (`Axioma/Nexo`).
El objeto `Shadow Copy` recuperado vía `GetById` retiene su esquema nativo con decenas de campos sistémicos (por ej. configuraciones impositivas, subcolecciones de contactos, clasificaciones, etc.) inalterados. **Sólamente** se reemplazarán en memoria los campos que aparecen a continuación, y se truncarán exactamente a las longitudes permitidas por Tango mediante `mb_substr`.

## Entidad: Cliente (Process: 2117)

| Nombre Local (CRM)      | Campo en JSON Tango | Max Len Tango | Observación           |
|-------------------------|---------------------|---------------|-----------------------|
| `razon_social`          | `RAZON_SOCI`        | 60            | Obligatorio (Tango)   |
| `documento`             | `CUIT`              | 20            | Se inyecta limpio     |
| `calle` + `numero`      | `DOMICILIO`         | 60            | Concatenación simple  |
| `localidad`             | `LOCALIDAD`         | 20            |                       |
| `codigo_postal`         | `C_POSTAL`          | 8             |                       |
| `email`                 | `E_MAIL`            | 255           | Correo electrónico 1  |
| `telefono`              | `TELEFONO_1`        | 20            | Teléfono principal    |

## Entidad: Artículo (Process: 87)

| Nombre Local (CRM)      | Campo en JSON Tango | Max Len Tango | Observación           |
|-------------------------|---------------------|---------------|-----------------------|
| `nombre`                | `DESCRIPCIO`        | 60            | Descripción comercial |
| `codigo_barras`         | `COD_BARRA`         | 20            |                       |
| `descripcion`           | `OBSERVACIONES`     | 255           | Notas adicionales     |

## Consideraciones sobre Subcolecciones Generales

1. **Campos Protegidos Omitidos:** Todo campo de estructura, roles, clasificación, lista de precios asignada, percepciones, cuentas contables y condicionales se mantienen intactos as-is sobre el DTO proveniente del `GetById`. Este comportamiento protege de inconsistencias críticas en el ERP.
2. **Subcolecciones Arrays (Contactos, Direcciones múltiples):** En esta fase 1, el motor no envía re-ordenamiento de arrays o colecciones en formato subnodo (ej: *Direcciones de Entrega*). Sólo se aplasta sobre los nodos principales de la Entidad base. Si Tango retorna nodos `[ {...} ]`, estos serán reenviados al Endpoint en su mismo index para no perder los objetos relacionales preexistentes apuntados por el ERP. 
3. **Sandbox:** Ante la validación productiva con la URL de la API oficial (ej: Endpoint `SAVE / UPDATE`), utilícese el script `test_sandbox_push.php` habilitándolo mediante la variable de entorno o directamente antes de apagar el modo protegido del Controlador `RxnSyncController.php`.
