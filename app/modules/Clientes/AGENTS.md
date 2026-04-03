# AGENTS.md — CLIENTES

## 🧠 Rol del agente
Gestionar clientes del sistema.

---

## 🧱 Responsabilidades

- Alta, baja, modificación de clientes
- Listados y filtros
- Búsqueda
- Validación de datos

---

## 🚫 Restricciones

- NO acceder a clientes de otra empresa
- NO eliminar registros físicos si hay dependencias (usar soft delete)

---

## 🔐 Seguridad

- Validar datos obligatorios
- Sanitizar inputs
- Restringir por empresa_id

---

## 🧩 Particularidades

- Todas las queries deben incluir empresa_id
- Indexar por documento / nombre

---

## ⚠️ Notas

- Este módulo es base para pedidos y facturación