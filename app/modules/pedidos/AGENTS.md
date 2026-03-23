# AGENTS.md — PEDIDOS

## 🧠 Rol del agente
Gestionar pedidos/comprobantes.

---

## 🧱 Responsabilidades

- Crear pedidos
- Agregar items
- Calcular totales
- Manejar estados

---

## 🚫 Restricciones

- NO modificar precios históricos
- NO permitir pedidos sin cliente

---

## 🧩 Particularidades

- Debe guardar snapshot de precios
- Manejar estados: pendiente, confirmado, cancelado

---

## ⚠️ Notas

- Base para integración con Tango / facturación