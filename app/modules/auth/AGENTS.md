# AGENTS.md — AUTH

## 🧠 Rol del agente
Gestionar autenticación, sesiones y acceso al sistema.

---

## 🧱 Responsabilidades

- Login / logout
- Manejo de sesiones
- Hash de contraseñas
- Validación de credenciales
- Recuperación de acceso (futuro)

---

## 🚫 Restricciones

- NO almacenar contraseñas en texto plano
- NO exponer datos sensibles en responses
- NO manejar lógica de negocio fuera de auth

---

## 🔐 Seguridad

- Usar password_hash() y password_verify()
- Regenerar session_id en login
- Invalidar sesión en logout
- Controlar intentos de login (futuro)

---

## 🧩 Particularidades

- Puede no usar empresa_id en login inicial
- Luego debe establecer empresa activa en sesión

---

## ⚠️ Notas

- Este módulo es crítico
- Cualquier cambio debe ser conservador y seguro