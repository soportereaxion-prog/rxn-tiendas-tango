# Control de Cambios: Propagación de Filtros Avanzados (Notas y Llamadas)

**Fecha:** 2026-04-05
**Módulo:** CRM Llamadas, CRM Notas

## 📌 Qué se hizo
Se completó la propagación del sistema de "Filtros Avanzados" estilo Excel a los últimos dos módulos restantes del CRM: **Gestión de Notas** y **Central Telefónica (Llamadas)**.

## ❓ Por qué
Durante la iteración anterior se había cubierto gran parte del ecosistema principal, pero estos dos módulos habían quedado rezagados. La integración permite sostener la misma ergonomía "sin pérdida de contexto" cuando se asisten operaciones diarias y se salta del listado al detalle/abm y de vuelta al catálogo matriz.

## ✅ Impacto
- **CRM Notas:** Se actualizó `CrmNotasController`, `CrmNotaRepository` y la vista `index.php` inyectando tanto el diccionario de `AdvancedQueryFilter` como los atributos HTML `data-filter-field`.
- **CRM Llamadas:** Se actualizó `CrmLlamadasController`, `CrmLlamadaRepository` y la vista `index.php`. El filtrado permite cruzar orígenes telefónicos con resolución de internos en vivo basándose en la configuración nativa de la central.
- **Seguridad:** Idéntica a la implementación global, whitelisting de alias SQL contra el request previniendo Inyección.
