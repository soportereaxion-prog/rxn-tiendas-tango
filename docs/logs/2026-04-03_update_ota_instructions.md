# Manual Operativo: Despliegues OTA (Over The Air) y Release Builder

Este documento engloba las políticas definitivas sobre la nueva era de actualizaciones del sistema RXN Suite, eliminando los despliegues manuales mediante intervención humana directa sobre las carpetas del servidor.

---

## 1. El Dilema del Primer Despliegue (Cómo bootstappear OTA por primera vez)

Dado que la plataforma acaba de adquirir la habilidad de instalarse a sí misma, existe un dilema lógico: Producción no puede recibir paquetes ZIP todavía porque aún no tiene instalado el actualizador.

Por lo tanto, la **Primera Vez** que llevemos esto a Producción, debe hacerse por mecanismos tradicionales (FTP o Pipeline), en un "Deploy Híbrido" mínimo:
- Subir la nueva clase `app/core/SystemUpdater.php`.
- Actualizar `app/modules/Admin/Controllers/MantenimientoController.php`.
- Actualizar el ruteo en `app/config/routes.php`.
- Actualizar la interfaz en `app/modules/Admin/views/mantenimiento.php`.

A partir del instante en que esos pocos archivos pisen Producción, **RXN Suite cobra vida** y nunca más será necesario el FTP.

---

## 2. Generar el ZIP de Actualización (Construcción del Release)

Para facilitar el envío del paquete `.zip`, el sistema está dotado del motor *ReleaseBuilder*, el cual filtra, limpia y compila un ZIP ignorando de forma nativa los archivos indeseables (`.git`, basuras temporales de `.uploads`, `.env`, étc). 

Esta herramienta dispone de dos vías exclusivas para el desarrollador:

### Vía Interfaz Gráfica (UI)
Desde su servidor Local/Desarrollo, entre a *Mantenimiento*. Al fondo de la página visualizará la tarjeta verde **Fábrica de Empaquetados OTA (Local)**.
1. Presione *Generar Paquete (.zip) Ahora*.
2. El sistema lo fabricará y guardará.
3. Pulse *Bajar ZIP* descargándolo cómodamente a las descargas de su PC.

*Mecanismo de seguridad*: Esta tarjeta solo está disponible si su entorno global marca `APP_ENV = local`, `dev` o `development`. Jamás será visible ni activable desde el entorno productivo vivo, eliminando riesgos de agotamiento de recursos.

### Vía Consola (CLI)
Para los programadores clásicos o procesos automatizados:
1. Navegue a la raíz del proyecto.
2. Ejecute: `php tools/build_update_zip.php`
3. AG compilará el archivo depositándolo bajo la carpeta `/build_ota/`.

---

## 3. Despliegue Directo sobre Producción (OTA)

Usted es RXN Admin y ya tiene en sus manos el ZIP provisto por cualquiera de las dos vías anteriores. 

1. **Ingreso:** Navegue en su BackOffice de Producción hacia **Mantenimiento y Actualizaciones**.
2. **Carga:** En la tarjeta superior "Actualización del Sistema (OTA Release)", elija su archivo `.zip` en el selector nativo.
3. **Instalación:** Pulse **Instalar** y no cierre la pestaña.
4. **Validación Automática:** Durante los breves segundos de demora, RXN Suite estará forzando un guardado paralelo de Base de Datos y de Código Vivo para permitir rápida restauración por si se corta la luz.
5. Si superó las cortapisas (bloqueos seguros a la modificación de `.env`, `storage` y `uploads`), la pantalla anunciará éxito rotundo en color verde.
6. **Migraciones Diferidas:** Fíjese si el ZIP entrante liberó nuevas migraciones (Tarjeta Migraciones). Si hay pendientes, oprima "Ejecutar" sobre esa tarjeta separada para finalizar el deploy.

---

## Buenas Prácticas Requeridas

*   **Plesk File Uploads:** Validar que su servidor posea un valor acorde en PHP.ini (`upload_max_filesize` a >100M) para no rebotar zips generosos, o en su defecto fabricar siempre entregables delgados omitiendo vendors enteros.
*   **Archivable temporal:** Cada OTA inyectada en `/storage/backups/updates` queda viva en disco. Periódicamente depurar esto vía mantenimiento para no inundar cuotas de disco en hostings pequeños.
*   **Downtime Cero Teórico:** Salvo reconstrucciones titánicas, los archivos se escriben sin bajar el servicio. Actúe previendo micro-cortes si un inquilino cliquea exacto en la fracción de escritura.
