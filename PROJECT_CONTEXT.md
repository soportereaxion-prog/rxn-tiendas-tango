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

---

## 💾 Política Formal de Persistencia (Base de Datos y Migraciones)

A partir de la estabilización del módulo de mantenimiento, el manejo de Base de Datos debe seguir OBLIGATORIAMENTE estas políticas:

**Reglas Operativas:**
- **Todo cambio debe ser versionado:** Las modificaciones a tablas y datos base deben quedar representadas única y exclusivamente como una migración versionada (`database_migrations_[nombre].php`).
- **NO manualidad en Producción:** Bajo NINGUNA circunstancia pueden realizarse alteraciones manuales (ad hoc) sobre herramientas de consola en el entorno Productivo vivo.
- **Dumps Prohibidos:** Jamás se pisará la base productiva completa con volcados ("dumps") originados en el entorno de Desarrollo o Locales.
- **Inmutabilidad:** Si un archivo de migración ya fue desplegado a Producción y ejecutado, queda **SELLADO/INMUTABLE**. Si incurrió en error posterior, el estándar exige la creación de un nuevo script de migración compensatorio / correctivo ("Rollback Forward").
- **Tipología de Migraciones:** Para facilitar la lectura se clasificarán (idealmente en el nombre) mediante tipos claros: **schema** (cambios DDL), **data** (ajuste DML o inserts de registros en masa), **fix** (parche a errores en datos) o **seed** (datos fundacionales para inicialización).
- **Procedimiento "Baseline":** La primera vez que el proyecto pise un entorno vivo tras habilitarse este sistema, se recurrirá a un 'Baseline'. Subsiguientemente solo aplicarán scripts incrementales.

**Criterios de Calidad Técnicos:**
- **Idempotencia exigida:** Toda orden DDL en las migraciones de esquemas debe redactarse usando enfoques seguros previniendo caídas: (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, comprobar existencia de columnas mediante `SHOW COLUMNS` previo a un `ALTER`, etc).
- **Tamaño atómico:** Las tareas se programarán breves y puntuales sin acoplar lógicas de módulos desconectados.
- **Separación DDL/DML:** Trate de evitar un mix caótico de DDL y DML en un solo paso. Si es inevitable y se manipulan transacciones de negocio sensibles (data DML), se exigen clausulas *Transactions Commit/Rollback*.
- **Cero destrucción injustificada:** Emplee `DROP` de forma extremadamente minuciosa o evítelo si esto corrompe históricos.

**Impacto en Flujo de Desarrollo (Workflow):**
En las próximas iteraciones el workflow queda establecido así:
1. *Local / Desarrollo*: Ajuste local del requerimiento. Elaboración de código.
2. *Generación DDL/DML*: Identificación del impacto de BD con creación manual del archivo `database_migrations_xxx.php`.
3. *Empaquetado (Build)*: Promoción de los binarios web mediante la ejecución del empaquetado de producción.
4. *Deploy & Sync*: Sincronización FTP/Pipeline, ingreso logueado del SysAdmin global a Backend y *Ejecución de Pendientes* mediante Interfaz Mantenimiento.