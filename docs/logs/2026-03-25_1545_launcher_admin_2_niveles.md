# [UI] — Launcher Administrativo en 2 Niveles

## Contexto
El dashboard B2B anterior ubicaba todas las rutas transaccionales ("Pedidos", "Clientes", "Configuración") indiscriminadamente en la raíz del sistema (`/`) apilando los accesos directos en una columna central. Tras evaluar el enfoque visual requerido por jefatura, se migró hacia un modelo de "Lanzador de Aplicaciones" en subniveles con un diseño Dark mode Premium basado en tarjetas modulares masivas (Cards).

## Problema
- Interfaz monolítica que aglomeraba accesos de Tenant con los globales (si correspondía).
- Ausencia de un enrutador raíz (Nivel 1) que separara el rol operativo del master administrativo de forma semántica y limpia.
- Representación visual anticuada al renderizar "lista de botones simples", en vez de bloques navegables.

## Arquitectura de Navegación Esperada e Implementada
- **Nivel 1 (Launcher Principal - `home.php`):** Convertida en una mega puerta bidireccional. Presenta únicamente 2 tarjetas:
  1. *RXN Backoffice:* Renderizada y habilitada estrictamente para master admins (`$_SESSION['es_rxn_admin'] == 1`).
  2. *Entorno Operativo:* Puerta al panel cotidiano del inqulino.
- **Nivel 2A (RXN Backoffice - `admin_dashboard.php`):** Subdashboard especializado y unificado bajo `/admin/dashboard` que contiene (por ahora) validadores SMTP y Listado de Empresas.
- **Nivel 2B (Entorno Operativo - `tenant_dashboard.php`):** Nueva terminal operativa (`/mi-empresa/dashboard`) donde el `SortableJS` actúa de host para el array de módulos nativos ("Pedidos", "Catálogo").

## Decisiones Auth y UI
- **Estética "Click Completo":** Replicado el prototipo de cards pesadas y oscuras mediante `<div class="card p-4 h-100">` con un `<a class="stretched-link">` que absorbe e invoca la física del click en toda su área para elevar UX. Efectos Hover configurados.
- **Control RBAC (`Administrar Cuentas`):** El modelo anterior no verificaba ramificaciones de inquilino desde la raíz. Se inyectó la hidratación de la columna `es_admin` a `$_SESSION` desde `AuthService.php` filtrando automáticamente esta Mega-Tarjeta desde el backend de ser necesario durante la renderización.
- **Drag & Drop:** Trasladada íntegramente la validación del JSON nativo AJAX hacia el Nivel 2B conservando todo su poder.

## Impacto
Pivoteo general de la lógica Front-End dotando al backend de PHP con apariencia y flujo lógico equivalente a SAP Fiori/Vtex.

## Riesgos
- Modificaciones estructurales en `routes.php`. Comportamientos testeados exitosamente.
- Visualización restringida asume correctamente bloqueos de render (Seguridad perimetral resuelta).
