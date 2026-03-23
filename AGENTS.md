# AGENTS.md — rxnTiendasIA

## Propósito del proyecto
rxnTiendasIA es un proyecto PHP con arranque mínimo, orientado a crecer de forma ordenada hacia:
- operación multiempresa
- APIs
- automatización
- integraciones futuras

En esta etapa el objetivo NO es sobrearquitecturar ni introducir complejidad prematura.

---

## Estado actual
Bootstrap mínimo definido y validado.

Base actual:
- `public/index.php` como único punto de entrada
- `app/core/App.php`
- router mínimo
- estructura simple y evolutiva

La prioridad es consolidar una base estable y clara, sin rediseñar todo en cada paso.

---

## Principios obligatorios
1. Mantener simplicidad.
2. No introducir frameworks grandes en esta etapa.
3. No inventar arquitectura nueva si no fue pedida.
4. Priorizar código claro, mantenible y fácil de extender.
5. Todo cambio debe respetar la estructura ya definida.
6. Si algo puede resolverse simple, no llevarlo a una solución enterprise innecesaria.
7. Diseñar pensando en crecimiento futuro, pero implementar solo lo necesario hoy.

---

## Stack oficial v1
### Backend
- PHP 8.2+  
- Composer
- Router propio mínimo
- Arquitectura liviana por capas

### Frontend
- PHP server-rendered
- Bootstrap 5
- JavaScript vanilla
- `fetch()` para interacciones puntuales

### Base de datos
- MySQL 8 / MariaDB compatible

### Configuración
- `.env`
- configuración centralizada
- manejo de errores simple
- logs básicos

---

## Política de versión PHP
El entorno actual puede trabajar con PHP 8.2.x.

Regla:
- desarrollar compatible con PHP 8.2+
- evitar introducir features exclusivas de versiones superiores si no son necesarias
- permitir migración futura a PHP 8.3+ sin refactor grande

No forzar upgrade de entorno en esta etapa salvo necesidad concreta.

---

## Arquitectura permitida en esta etapa
Estructura simple por capas:

- `core/`
- `controllers/`
- `services/`
- `repositories/`
- `views/`
- `config/`

### Criterios
- `Controller`: recibe request y coordina respuesta
- `Service`: contiene lógica de negocio
- `Repository`: acceso a datos
- `View`: render
- `Core`: arranque, router, utilidades base

No mezclar lógica de negocio pesada dentro de vistas.
No meter acceso SQL directamente en controladores salvo pruebas mínimas muy justificadas.

---

## Qué NO hacer todavía
No introducir en esta etapa:
- microservicios
- frontend desacoplado tipo SPA
- JWT porque sí
- colas/event bus
- CQRS
- DDD formal
- ORM pesado
- contenedorización obligatoria
- multitenancy complejo
- ACL/RBAC sobrediseñado

Si algo de eso aparece, debe justificarse por necesidad real del proyecto.

---

## Base de datos — criterio actual
La base debe ser simple y prolija.

### Mínimos recomendados
- claves primarias claras
- claves foráneas donde aporte valor real
- índices en campos de búsqueda y relación
- `created_at`
- `updated_at`

### Multiempresa
Si una entidad ya nace con orientación multiempresa, contemplar `empresa_id`.
No forzar tenancy complejo todavía.
No separar por bases múltiples en esta etapa.

---

## Forma de trabajo para agentes
Cuando un agente proponga cambios debe:

1. respetar la estructura actual
2. justificar brevemente por qué ese cambio es necesario
3. elegir la opción más simple viable
4. evitar agregar dependencias sin motivo
5. dejar el código listo para crecer, no perfecto en abstracto

---

## Criterio para nuevas carpetas o componentes
Solo crear nuevas piezas si:
- resuelven una necesidad actual
- evitan mezclar responsabilidades
- mejoran claridad real del proyecto

No crear carpetas “por si acaso”.

---

## Convenciones generales
- nombres claros y consistentes
- responsabilidades bien separadas
- evitar helpers mágicos innecesarios
- evitar acoplamiento fuerte
- comentarios solo cuando aporten contexto real
- priorizar legibilidad sobre “ingenio”

---

## Objetivo inmediato
Construir una versión mínima que:
- arranque correctamente
- resuelva rutas
- renderice vistas
- permita conexión a base de datos
- quede preparada para crecer sin rehacer la base

---

## Regla de decisión
Ante duda:
elegir la solución más simple, compatible con la estructura actual y fácil de evolucionar.