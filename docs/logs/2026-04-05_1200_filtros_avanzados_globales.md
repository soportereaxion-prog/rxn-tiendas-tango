# Control de Cambios: Filtros Avanzados Globales

**Fecha:** 2026-04-05
**Módulo:** Global (Usuarios, Empresas, Artículos, Categorías, Clientes Web, Pedidos Web)

## 📌 Qué se hizo
Se completó la integración de un sistema de "Filtros Avanzados" estilo Excel en múltiples CRUDs de la aplicación.
Esta expansión introdujo controles paramétricos sobre los encabezados de las tablas (TH), permitiendo filtrar específicamente por columna, conservando el estado general en la sesión ("crud_filters").

## ❓ Por qué
Facilitar las labores operativas de los usuarios al buscar datos de forma cruzada, minimizando baches en la búsqueda clásica de string unificado. Se requería también mantener el estado para evitar perder contexto tras entrar al ABM o realizar una acción y volver al listado.

## ✅ Impacto
- **Back-End:** Se incluyó un mecanismo en el controlador base (`handleCrudFilters()`) y a nivel de repositorios, que intercepta y sanitiza `$advancedFilters` utilizando diccionarios (whitelists) a través del Helper `AdvancedQueryFilter` para prevenir inyección SQL. Las consultas ahora aceptan alias dinámicos en tablas normalizadas.
- **Front-End:** Las vistas incorporaron referencias JS (`rxn-advanced-filters.js`) al final del layout y la inyección segura de atributos `data-filter-field="columna"` en HTML.
- Involados: Empresas, Usuarios, Artículos, Categorías, Clientes y Pedidos.

## 🔒 Decisiones Técnicas y de Seguridad
- Se utiliza `$_SESSION['crud_filters'][$modulo]` para la persistencia.
- Se ha incluido lógica de "limpieza selectiva" para descartar un filtro particular mediante la tecla Supr/Eliminar sobre el modal local.
- Se prohibió la inyección concatenando ciegamente claves HTTP al Query; todo se sanitiza vía un Mapa de Columnas válido provisto por cada Repositorio de base.
