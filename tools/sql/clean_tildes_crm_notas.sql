-- ============================================================================
-- Limpieza de caracteres ~ residuales en crm_notas.contenido
-- ----------------------------------------------------------------------------
-- Origen: 507 notas importadas arrastran bloques de ~ que se usaban como
-- espaciadores/separadores en el formato fuente. En la UI se ven como ruido
-- (ver screenshot 2026-04-21).
--
-- Estrategia: cualquier corrida de 1+ tildes se reemplaza por UN espacio.
-- Con eso:
--   'Email:~barbat@x.com'         -> 'Email: barbat@x.com'
--   'Llave:~~~~~~~~~002100/004'   -> 'Llave: 002100/004'
--   '~~~~~dsa.admin@x.com'        -> ' dsa.admin@x.com'
-- Saltos de línea y espacios existentes NO se tocan.
--
-- Preserva updated_at: al asignarle su propio valor en el mismo UPDATE,
-- MySQL/MariaDB NO dispara el ON UPDATE CURRENT_TIMESTAMP. De esta forma la
-- limpieza no aparece como "nota modificada hoy" para los usuarios.
--
-- Requisitos: MySQL 8.0+ o MariaDB 10.0.5+ (REGEXP_REPLACE).
-- ============================================================================


-- PASO 1 — Backup de respaldo (idempotente). Si algo sale mal, se restaura
-- con: INSERT INTO crm_notas SELECT * FROM crm_notas_backup_tildes_20260421
-- (previo DELETE de las filas afectadas).
CREATE TABLE IF NOT EXISTS crm_notas_backup_tildes_20260421 AS
SELECT * FROM crm_notas WHERE contenido LIKE '%~%';


-- PASO 2 — Dry run. Revisar visualmente antes de aplicar.
SELECT
    id,
    empresa_id,
    LEFT(contenido, 140) AS antes,
    LEFT(REGEXP_REPLACE(contenido, '~+', ' '), 140) AS despues
FROM crm_notas
WHERE contenido LIKE '%~%'
ORDER BY id
LIMIT 30;


-- PASO 3 — Aplicar limpieza.
UPDATE crm_notas
SET contenido  = REGEXP_REPLACE(contenido, '~+', ' '),
    updated_at = updated_at
WHERE contenido LIKE '%~%';


-- PASO 4 — Verificación (debería devolver 0).
SELECT COUNT(*) AS notas_con_tilde_restantes
FROM crm_notas
WHERE contenido LIKE '%~%';


-- PASO 5 — Si todo OK, podés borrar el backup cuando te sientas cómodo.
-- DROP TABLE crm_notas_backup_tildes_20260421;
