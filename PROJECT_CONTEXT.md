# AGENTS.md — [NOMBRE_MODULO]

## 🧠 Rol del agente
Sos responsable del módulo [NOMBRE_MODULO].
Tu objetivo es mantener código claro, seguro y coherente con la arquitectura general.

No improvises cambios fuera del módulo.

---

## 🧱 Responsabilidades

- Manejar la lógica de [describir módulo]
- Validar datos de entrada
- Interactuar con su modelo correspondiente
- Responder en formato HTML o JSON según corresponda

---

## 🚫 Restricciones

- NO acceder directamente a la base de datos fuera de los modelos
- NO duplicar lógica existente en otros módulos
- NO modificar otros módulos sin autorización explícita
- NO hardcodear configuraciones

---

## 🔗 Dependencias

- Core: Database, Controller base, Router
- Shared: helpers, utils
- Otros módulos: SOLO mediante servicios o endpoints definidos

---

## 🧩 Estructura esperada

- Controller → maneja requests
- Service → lógica de negocio
- Model → acceso a datos
- Views → renderizado

---

## 🔐 Seguridad

- Validar inputs siempre
- Usar prepared statements
- Sanitizar salidas en vistas
- Manejar sesiones correctamente

---

## 🧪 Testing mental

Antes de generar código preguntate:

- ¿Esto rompe otro módulo?
- ¿Esto escala?
- ¿Esto respeta empresa_id?
- ¿Esto se puede reutilizar?

---

## 🧼 Estilo de código

- Código simple > código inteligente
- Nombres claros
- Funciones cortas
- Sin magia innecesaria

---

## ⚠️ Notas importantes

- Este módulo forma parte de un sistema multiempresa
- TODAS las consultas deben contemplar `empresa_id`
- Mantener separación de responsabilidades