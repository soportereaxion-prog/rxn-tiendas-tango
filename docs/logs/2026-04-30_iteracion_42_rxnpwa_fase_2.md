# Iteración 42 — RXN PWA Fase 2: form mobile + creación offline + adjuntos

**Fecha:** 2026-04-30
**Release:** 1.32.0
**Build:** 20260430.3

---

## Qué se hizo

Cierre del Bloque B del roadmap PWA. Los vendedores ya pueden crear
presupuestos completos desde el celu, **100% offline**, con adjuntos. La
sincronización al server queda para Fase 3.

### Vista mobile dedicada

`/rxnpwa/presupuestos/nuevo` y `/rxnpwa/presupuestos/editar/{tmpUuid}`. Cabecera
+ renglones + comentarios/observaciones + adjuntos. Layout vertical, optimizado
para una mano.

### IndexedDB v2

DB `rxnpwa` pasó de version 1 a 2. 2 stores nuevas:
- `presupuestos_drafts` — keyPath `tmp_uuid`, índices `by_status` + `by_updated_at`.
- `presupuesto_attachments` — keyPath autoIncrement, índice `by_tmp_uuid`.

Migración la hace el browser solo en `onupgradeneeded`. Charly probó local con
DB v1 ya creada y subió a v2 sin perder el catálogo.

### UUID local

`crypto.randomUUID()` con fallback a `Date.now()+Math.random()`. Prefijo
`TMP-` para distinguir del id server (Fase 3 reemplaza el TMP por el id real).
URL del form se reescribe con `history.replaceState` al primer save para que
un reload edite el mismo draft.

### Auto-save + manual

Debounce 1.5s al cambiar cualquier input/select/textarea + botón "Guardar"
manual con feedback visible "Borrador guardado localmente". `updated_at` y
`total` se recalculan automático en cada save.

### Pickers cliente y artículo

Búsqueda fuzzy local sobre los 475 clientes y 4500 artículos cargados en
memoria. Match contra `razon_social`/`documento`/`codigo_tango` (clientes) o
`codigo_externo`/`nombre`/`descripcion` (artículos). Limit 30 resultados para
no congelar la UI. Mínimo 2 chars para arrancar la búsqueda.

### Auto-precio + auto-stock

Al elegir artículo en el modal:
- `resolvePrice(art, listaCodigo)` → busca en `crm_articulo_precios` local; si
  no hay match, fallback a `precio_lista_1` → `precio` base. Muestra origen
  ("Lista X", "Fallback lista 1", "Sin precio · cargar manual").
- `resolveStock(art, depositoCodigo)` → busca en `crm_articulo_stocks` local;
  si no hay info, lo dice explícitamente.

Cuando el operador cambia la lista de la cabecera, los renglones existentes
recalculan precio sugerido (no respeta manual override en v1 — iteramos
cuando se vea uso real).

### Adjuntos

2 botones:
- **"Sacar foto"** — `<input type=file accept=image/* capture=environment>`
  abre cámara trasera directa en Android/iOS.
- **"Adjuntar archivo"** — `multiple` + accept de JPG/PNG/WebP + PDF + Word
  (.doc/.docx) + Excel (.xls/.xlsx).

Límite 10 por presupuesto. Warning amarillo a partir del 5.

#### Compresión automática

Solo aplica a imágenes:
- Max lado largo 1600px (mantiene proporción con `Math.min(1, max/largo)`).
- Calidad 0.80 JPEG/WebP.
- PNG con transparencia (sample en 5 puntos del canvas) → mantiene PNG.
- PNG opaca → convierte a JPEG.
- Si la "compresión" pesa ≥ original (imagen ya optimizada o muy chica),
  devuelve la original sin tocar.

PDF/Word/Excel van crudos sin tocar.

### Listado de borradores en el shell

Card "Mis borradores" lee IndexedDB y renderiza:
- Cliente · total · nº de renglones/adjuntos · fecha de update · badge de estado.
- Click → abre el form en modo edit (URL con tmp_uuid).
- Botón "Nuevo" → form vacío (crea uuid al primer save).

---

## Por qué

Charly viene a competir con otra app de presupuestos mobile. Sin esta fase,
los vendedores no podían capturar la información completa en campo. Con esto:

- Cliente con guantes en el galpón saca una foto del producto, comprime
  automática a ~300 KB, queda asociada al presupuesto.
- Sin señal puede cotizar igual — todo se persiste local.
- Cuando hay red (Fase 3), un click sincroniza todo.

Compresión auto fue P0 — Charly explícito: "fotos super pesadas". 8 MB raw
desde una motoG → 200-400 KB comprimida. Diferencia entre tener offline
viable y morir en el intento.

---

## Validación

### Backend
- Lint PHP OK en RxnPwaController, presupuesto_form.php, presupuestos_shell.php, routes.php.
- 5 rutas RxnPwa registradas en el router.
- Migración no aplica (Fase 2 no toca DB server-side).

### Cliente (smoke real Charly)
- Crear borrador con renglones + cliente + lista + clasificación → guarda OK.
- Adjuntar foto desde cámara → comprime y guarda OK.
- Refrescar pestaña → draft persiste con todo (renglones + adjuntos).
- Listado en shell muestra el draft con datos correctos.
- Modo offline (avión) → sigue funcionando idéntico, todo es local.

Único pendiente visual: el ícono Reaxion no se vio porque Charly no llegó a
dejar el `rxnpwa-source.png`. Queda para Fase 3.

---

## Decisiones tomadas

1. **100% offline-first** — el botón "Enviar al server" es placeholder
   "Próximamente — Fase 3". Decisión P0 con Charly: arquitectura limpia, no
   híbrido sucio que después hay que migrar.
2. **DB version 2 con `onupgradeneeded`** — no resetea el catálogo existente.
3. **UUID local con prefijo `TMP-`** — distinción explícita server vs local.
   `crypto.randomUUID` cuando está disponible (todas las versiones modernas).
4. **Compresión solo para imágenes**. PDF/Office van crudos — la compresión
   sería destructiva o ineficiente.
5. **PNG con transparencia → mantiene PNG**. Sample 5 puntos del canvas
   (esquinas + centro) para detectar alfa < 255.
6. **Auto-save debounce 1.5s**. Más rápido genera ruido en transacciones
   IndexedDB; más lento siente lag.
7. **Recalculo de precios al cambiar lista pisa todo**. Iteramos cuando se
   vea uso real — capaz hace falta diferenciar precio sugerido vs precio
   manual override.
8. **Pickers limitados a 30 resultados**. 4500 artículos sin filtro congela
   el render incluso en celu moderno. Sumar paginación si Charly pide.
9. **`sanitizeTmpUuid` en el Controller** — defensivo contra path injection.
   Acepta solo `TMP-[A-Za-z0-9-]{1,64}`.

---

## Pendiente próxima sesión (Fase 3)

- 🔲 Cola de envío al server. POST a un endpoint nuevo `/api/rxnpwa/presupuestos/sync`
  que reciba el draft + sus attachments (multipart o varios POST).
- 🔲 Reconciliación: 1) crear presupuesto en server con los datos, recibir id
  real → 2) por cada attachment, upload + asociar al id server.
- 🔲 Retry con backoff exponencial si falla la red a la mitad.
- 🔲 Estado visible en cada draft del listado (pendiente / sincronizando /
  sincronizado / error).
- 🔲 Service Worker `sync` event (Background Sync API) para que la cola
  arranque sola al volver red, sin que el operador abra la app.
- 🔲 Reemplazar íconos placeholder por arte final (`rxnpwa-source.png`
  pendiente de Charly).
- 🔲 Afinar thresholds de stale-time si el comportamiento real lo pide.

---

## Files

### Nuevos
- `app/modules/RxnPwa/views/presupuesto_form.php`
- `public/js/pwa/rxnpwa-drafts-store.js`
- `public/js/pwa/rxnpwa-image-compressor.js`
- `public/js/pwa/rxnpwa-form.js`
- `public/js/pwa/rxnpwa-shell-drafts.js`
- `public/css/rxnpwa.css`

### Modificados
- `app/modules/RxnPwa/RxnPwaController.php` — 2 actions nuevas + `sanitizeTmpUuid`.
- `app/modules/RxnPwa/views/presupuestos_shell.php` — listado de drafts + link a Nuevo. Removidos los placeholders Fase 2.
- `public/js/pwa/rxnpwa-catalog-store.js` — DB_VERSION → 2 con stores nuevas en `onupgradeneeded`.
- `public/sw.js` — RXNPWA_VERSION → v2, precache extendido.
- `app/config/routes.php` — 2 rutas nuevas.
- `app/config/version.php` — bump a 1.32.0.
