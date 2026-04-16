# Iteración: Implementación de Spotlight Search (Buscador Flotante)

**Fecha:** 2026-04-07
**Módulo:** CRM Presupuestos / Global CRM
**Autor:** Antigravity (Lumi)

## 📌 Qué se hizo
Se implementó un nuevo buscador universal flotante (Spotlight Search) accesible mediante teclado (`Enter`) y ratón (`Doble Clic`) sobre los inputs de autocompletado y los elementos nativos `<select>`. Además, se realizaron correcciones visuales y funcionales en el módulo de Presupuestos.

1. **Spotlight Search (Buscador Flotante):**
   - Se diseñó el componente en `public/js/rxn-spotlight.js`, proveyendo una interfaz similar al "Spotlight" de macOS o un command palette.
   - Consta de un input buscador unificado y resultados renderizados bajo demanda.
   - Actuación Híbrida:
     - Si el elemento subyacente posee `data-picker-url`, el buscador consulta de manera asíncrona la API del backend (Ej: Artículos o Clientes).
     - Si el elemento es un simple `<select>`, el buscador atrapa localmente todos los `<option>` y realiza un filtro rápido sin red.
   - **Precedencia de Eventos (Capture Phase):** Se resolvió un conflicto técnico inyectando el listener de la tecla `Enter` con captura forzada (`true`). Esto permite arrebatar el evento de envío del formulario o de otros selectores locales (Chau Ratón), disparando `e.stopPropagation()` para levantar el Spotlight con autoridad absoluta.

2. **Adaptabilidad Dark Mode:**
   - La nueva caja flotante lee variables globales CSS (`var(--bs-tertiary-bg)`, `var(--bs-primary)`) asegurando su correcta visibilidad tanto en modo claro como oscuro.
   - Se aplicó una revisión de los colores `IMPORTE TEMP` del presupuesto, consolidándolo en un gris suave sin comprometer visuales oscuras.

3. **Cierre de Ciclo de Botón Sincronizar:**
   - El botón `Sync Catalogos` se reescribió para utilizar un formulario con verbo `POST`, resolviendo el requerimiento de seguridad de la ruta que había sido expuesta por los agentes de validación (Policy de no mutación en rutás `GET`).

4. **Corrección de PDO Statement:**
   - El `PresupuestoRepository->update` presentaba una falla fatal al proveer variables de auditoría al PDO `execute()` que no poseían un binding asociado en el query SQL (`$sql`). Se purgó localmente el array asociativo antes de ejecutar la actualización.

## 🧠 Por qué
- Se busca unificar la experiencia del operador que prefiere navegar velozmente por teclado y a su vez proveer un buscador cómodo para casos en los que el input integrado (`rxn-picker`) resulta insuficiente en contextos ricos.
- Era prioritario asegurar la compatibilidad universal del modo oscuro (Dark Mode), eliminando contrastes inversos.

## ⚙️ Impacto y Aislamiento de Variables
- **Archivos y Módulos tocados:** 
  - `public/js/rxn-spotlight.js` (Nuevo)
  - `admin_layout.php` (Importación global del script en CRM)
  - `rxn-theming.css` (Clases y variables del Spotlight)
  - `CrmPresupuestos/views/form.php` (Adopción en HTML)
  - `CrmPresupuestos/PresupuestoRepository.php` (Fix PDO)

## 🛑 Medidas de Seguridad Base Acatadas
- **Aislamiento multiempresa:** La API asíncrona lee el Contexto global y previene fuga de datos, el payload mantiene `cliente_id` dependiente si aplica.
- **Ruteo Seguro:** Migración de GET a POST en la sincronización comercial de este controlador.

## 🔜 Siguientes Pasos
- Permitir iteraciones orgánicas a medida que los operadores testeen este nuevo modo.
- Eventualmente llevar esta mecánica también al panel operativo de *Pedidos de Servicio* (PDS).

## 🪳 Hotfix Posterior (Crash Global Spotlight y Submission Involuntaria)
- Se detectó que el archivo `rxn-spotlight.js` corrompía su inicialización universal debido a una clausura `});` "huerfana" que produjo un SyntaxError subyacente. Se restituyó el envoltorio `DOMContentLoaded` recuperando toda la maquinaria global del Spotlight.
- Al morir silenciosamente el JS, la tecla `Enter` recaía en el estándar de HTML5, disparando el evento de submit (Guardar) sobre el formulario entero al ocluir en cualquier `<input>` o `<select>`.
- Como salvaguarda arquitectural adicional se blindó el `crm-presupuestos-form.js` inyectando un bloqueador absoluto de `Enter` en fase top-down del propio formulario, protegiendo todo el cuerpo comercial (excepto botones nativos o textareas) contra auto-gardados por pulsaciones accidentales de operadores veloces.

## 🪳 Hotfix Posterior 2 (Robo de Focus y Selects con Estética Spotlight)
- **Causa (Botón agregar muerto):** Spotlight, al culminar su animación de cierre asíncrona de 200ms, devolvía el foco de manera forzosa al campo "Buscar Artículo", pisando así el trabajo del framework de presupuestos (que acababa de mudarlo estratégicamente hacia la caja de "Cantidad" tras buscar exitosamente el precio en la BD). Esta colisión hacía inefectivo el botón de **+** o el `Enter` siguiente de los operadores veloces.
- Se implementó un parámetro preventivo `restoreFocus=false` que se transmite al cerrar producto de un `picker-selected`, delegando todo el fluir al componente reactivo nativo de Presupuestos.
- **Bonus UI:** Se mapeó un Interceptor en Evento de `mousedown` para todos los recuadros de catálogos seleccionables (Depósito, Vendedor, Listas, Transporte, etc). Cuando un operador haga un simple "Click", se prevendrá la modesta caja blanca de Select html nativa apostando en su lugar por alzar el Spotlight de manera consistente para todos sus combos.
