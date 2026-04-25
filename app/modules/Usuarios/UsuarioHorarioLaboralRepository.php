<?php

declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\Database;
use PDO;

/**
 * UsuarioHorarioLaboralRepository
 *
 * Lectura/escritura del horario laboral declarado por cada usuario.
 *
 * Patrón de escritura: replace-all-by-user. Al guardar el horario de un usuario,
 * borramos todos sus bloques actuales e insertamos los nuevos. Es lo más simple
 * y correcto para una grilla L-D editable inline (sin tracking de IDs en la UI).
 *
 * Multi-bloque: un mismo (usuario_id, dia_semana) puede tener N filas — eso
 * permite turnos partidos (ej: 9-13 + 14-18).
 */
class UsuarioHorarioLaboralRepository
{
    private PDO $db;

    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Devuelve los bloques agrupados por día.
     *
     * @return array<int,array<int,array{id:int,bloque_inicio:string,bloque_fin:string,activo:int}>>
     */
    public function findByUserGroupedByDay(int $usuarioId): array
    {
        $stmt = $this->db->prepare('
            SELECT id, dia_semana, bloque_inicio, bloque_fin, activo
            FROM usuario_horario_laboral
            WHERE usuario_id = :u
            ORDER BY dia_semana ASC, bloque_inicio ASC
        ');
        $stmt->execute([':u' => $usuarioId]);

        $byDay = [];
        foreach (array_keys(self::DIAS) as $d) {
            $byDay[$d] = [];
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $d = (int) $row['dia_semana'];
            $byDay[$d][] = [
                'id'            => (int) $row['id'],
                'bloque_inicio' => substr((string) $row['bloque_inicio'], 0, 5),
                'bloque_fin'    => substr((string) $row['bloque_fin'], 0, 5),
                'activo'        => (int) $row['activo'],
            ];
        }
        return $byDay;
    }

    /**
     * Busca el bloque del día actual cuyo inicio sea más cercano a $atTime
     * (formato HH:MM:SS). Útil para hooks tipo "no iniciaste turno".
     *
     * @return array{bloque_inicio:string,bloque_fin:string}|null
     */
    public function findCurrentDayBlock(int $usuarioId, int $diaSemana, string $atTime): ?array
    {
        $stmt = $this->db->prepare('
            SELECT bloque_inicio, bloque_fin
            FROM usuario_horario_laboral
            WHERE usuario_id = :u AND dia_semana = :d AND activo = 1
              AND bloque_inicio <= :t
            ORDER BY bloque_inicio DESC
            LIMIT 1
        ');
        $stmt->execute([':u' => $usuarioId, ':d' => $diaSemana, ':t' => $atTime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'bloque_inicio' => (string) $row['bloque_inicio'],
            'bloque_fin'    => (string) $row['bloque_fin'],
        ];
    }

    /**
     * Reemplaza el horario completo del usuario por el set provisto.
     *
     * @param array<int,array<int,array{bloque_inicio:string,bloque_fin:string,activo?:int}>> $byDay
     */
    public function replaceForUser(int $usuarioId, array $byDay): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM usuario_horario_laboral WHERE usuario_id = :u');
            $del->execute([':u' => $usuarioId]);

            $ins = $this->db->prepare('
                INSERT INTO usuario_horario_laboral
                    (usuario_id, dia_semana, bloque_inicio, bloque_fin, activo)
                VALUES (:u, :d, :i, :f, :a)
            ');

            foreach ($byDay as $diaSemana => $bloques) {
                $diaSemana = (int) $diaSemana;
                if ($diaSemana < 1 || $diaSemana > 7) {
                    continue;
                }
                foreach ($bloques as $b) {
                    $inicio = $this->normalizeTime($b['bloque_inicio'] ?? '');
                    $fin    = $this->normalizeTime($b['bloque_fin'] ?? '');
                    if ($inicio === null || $fin === null) {
                        continue;
                    }
                    $activo = isset($b['activo']) ? (int) $b['activo'] : 1;
                    $ins->execute([
                        ':u' => $usuarioId,
                        ':d' => $diaSemana,
                        ':i' => $inicio,
                        ':f' => $fin,
                        ':a' => $activo ? 1 : 0,
                    ]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Aceptamos "9:00", "09:00", "09:00:00"
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h < 0 || $h > 23 || $i < 0 || $i > 59 || $s < 0 || $s > 59) {
                return null;
            }
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }
        return null;
    }
}
