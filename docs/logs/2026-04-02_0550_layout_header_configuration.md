# Arquitectura Modular de Encabezados (Admin Layout)

A partir de la iteración actual, el layout maestro `admin_layout.php` controla en tiempo de ejecución si se debe dibujar o no el Título/Header contextual de cada pantalla.

## Reglas de Renderizado
1. **Desactivado por defecto**: Si una vista no dictamina explícitamente `$usePageHeader = true`, el layout principal IGNORARÁ la impresión del bloque de título. Esto asegura que vistas históricas que controlan su propio HTML no se rompan ni generen visuales dobles.
2. **Modo Estándar**: Pensado para ABMs y módulos clásicos. Se invoca usando `$usePageHeader = true` (por defecto asume modo `standard`).
3. **Modo Compacto**: Pensado para Dashboards informacionales. Configurable seteando `$headerMode = 'compact'`. Reducirá severamente los paddings del header para ahorrar espacio en Y.
4. **Modo Nulo o Personalizado**: Módulos que tienen su propia botonera ultracompleja (como Envío a Tango para **Pedidos de Servicio**) jamás deben activar el header nativo y conservarán intacta su libertad.

## Variables Admitidas
La vista que use `.php` debe declarar lo siguiente antes del volcado a `$content`:

```php
$usePageHeader = true; // Activa el layout nativo
$headerMode = 'compact'; // standard o compact
$pageHeaderTitle = 'Título del Módulo';
$pageHeaderSubtitle = 'Subtítulo o métrica';
$pageHeaderIcon = 'bi bi-reception-4';
$pageHeaderBackUrl = '/rxn_suite/public/';
$pageHeaderBackLabel = 'Volver';

ob_start();
?>
<a href="#" class="btn btn-primary">Botón custom inyectado</a>
<?php
$pageHeaderActions = ob_get_clean();
```

Esto consolida un esquema predecible en todo el backend y soltará los lastres del viejo diseño artesanal vista por vista.
