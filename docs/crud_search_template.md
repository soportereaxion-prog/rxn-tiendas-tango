# Mini Plantilla Tecnica - Buscadores CRUD

## Objetivo

Plantilla breve para replicar el patron de buscadores CRUD en nuevos modulos sin volver a pensarlo desde cero.

Usar junto con `docs/crud_search_standard.md`.

## 1. Campos buscables

Definir antes de picar codigo:

```php
private const SEARCH_FIELDS = ['all', 'id', 'codigo', 'nombre'];
```

Regla:
- incluir solo campos operativos visibles para el usuario;
- `all` significa todos los campos operativos, no toda la tabla.

## 2. Repository

### Campos whitelist

```php
private const SEARCHABLE_FIELDS = [
    'id' => 'CAST(id AS CHAR)',
    'codigo' => 'codigo',
    'nombre' => 'nombre',
];
```

### Metodo de sugerencias

```php
public function findSuggestions(?string $search = null, string $field = 'all', int $limit = 3): array
```

Debe:
- devolver maximo `3` registros;
- ordenar por un campo estable (`nombre`, `codigo`, etc.);
- reutilizar la misma logica de whitelist del buscador principal.

## 3. Service

### Reglas minimas

```php
private const SUGGESTION_LIMIT = 3;
```

```php
public function findSuggestions(array $filters = []): array
```

Debe:
- ignorar busquedas de menos de `2` caracteres;
- mapear respuesta a un formato comun:

```php
[
    'id' => 1,
    'label' => 'Registro visible',
    'value' => 'Registro visible',
    'caption' => '#1 | COD001',
]
```

## 4. Controller

Agregar endpoint JSON minimo:

```php
public function suggestions(): void
{
    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'data' => $this->service->findSuggestions($_GET),
    ]);
}
```

## 5. Ruta

```php
$router->get('/modulo/sugerencias', [ModuloController::class, 'suggestions']);
```

## 6. Vista

### Estructura minima

```php
<form method="GET" data-search-form>
    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>" data-search-hidden>

    <select name="field" data-search-field>
        <option value="all">Todos los campos</option>
    </select>

    <input
        type="search"
        value="<?= htmlspecialchars($search) ?>"
        data-search-input
        data-suggestions-url="/modulo/sugerencias"
        autocomplete="off"
    >

    <div data-search-suggestions></div>

    <button type="submit">Aplicar</button>
</form>
```

### Regla critica

- el input visible no debe llevar `name="search"`;
- el valor confirmado viaja en el hidden `data-search-hidden`.

## 7. JavaScript minimo

Debe resolver:
- cargar sugerencias al escribir;
- seleccionar sugerencia;
- sincronizar hidden solo al submit;
- cerrar con `Escape` o click externo;
- navegar con flechas.

## 8. Visual

Repetir siempre:
- mismo alto de select, input y boton;
- bloque de busqueda alineado a la derecha cuando comparte fila con otras acciones;
- mismo dropdown de sugerencias;
- misma ayuda contextual.

## 9. Checklist rapido

- [ ] no autofiltra al escribir
- [ ] sugiere maximo 3 resultados
- [ ] separa valor editable de valor confirmado
- [ ] mantiene server-rendered el CRUD
- [ ] usa whitelist de campos
- [ ] se ve bien en desktop y mobile
- [ ] queda documentado en `docs/logs`
