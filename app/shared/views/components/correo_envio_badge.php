<?php
/**
 * Muestra un sobrecito con badge del contador de envíos + alerta si hay error.
 *
 * Espera en el scope:
 *   $count        int       — veces que se envió el mail (correos_enviados_count)
 *   $ultimoEnvio  ?string   — fecha ISO del último envío exitoso
 *   $ultimoError  ?string   — mensaje de error (null si no hay)
 *   $ultimoErrorAt ?string  — fecha ISO del último error
 *
 * Los inputs son todos optional — el partial se adapta.
 */

$count = (int) ($count ?? 0);
$ultimoEnvio = $ultimoEnvio ?? null;
$ultimoError = $ultimoError ?? null;
$ultimoErrorAt = $ultimoErrorAt ?? null;

// Si hay un error registrado DESPUÉS del último envío exitoso, prevalece como estado visible.
$hayErrorVigente = false;
if (!empty($ultimoError)) {
    if (empty($ultimoEnvio)) {
        $hayErrorVigente = true;
    } elseif (!empty($ultimoErrorAt) && strtotime((string)$ultimoErrorAt) > strtotime((string)$ultimoEnvio)) {
        $hayErrorVigente = true;
    }
}

$fmt = function (?string $iso): string {
    if (empty($iso)) return '';
    $ts = strtotime($iso);
    if ($ts === false) return (string)$iso;
    return date('d/m/Y H:i', $ts);
};

if ($hayErrorVigente) {
    $tooltip = 'Error al enviar (' . $fmt($ultimoErrorAt) . '): ' . mb_substr((string)$ultimoError, 0, 200);
    echo '<span class="rxn-correo-badge text-danger d-inline-flex align-items-center gap-1" style="position: relative;"'
        . ' title="' . htmlspecialchars($tooltip, ENT_QUOTES) . '"'
        . ' data-bs-toggle="tooltip" data-bs-placement="top">'
        . '<i class="bi bi-envelope-x-fill" style="font-size: 1.05rem;"></i>';
    if ($count > 0) {
        echo '<span class="badge bg-danger" style="font-size: 0.62rem; padding: 0.15rem 0.35rem; line-height: 1;">' . $count . '</span>';
    } else {
        echo '<i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 0.75rem;"></i>';
    }
    echo '</span>';
} elseif ($count > 0) {
    $tooltip = 'Enviado ' . $count . ($count === 1 ? ' vez' : ' veces')
             . (!empty($ultimoEnvio) ? ' — último: ' . $fmt($ultimoEnvio) : '');
    echo '<span class="rxn-correo-badge text-success d-inline-flex align-items-center gap-1" style="position: relative;"'
        . ' title="' . htmlspecialchars($tooltip, ENT_QUOTES) . '"'
        . ' data-bs-toggle="tooltip" data-bs-placement="top">'
        . '<i class="bi bi-envelope-check-fill" style="font-size: 1.05rem;"></i>'
        . '<span class="badge bg-success" style="font-size: 0.62rem; padding: 0.15rem 0.35rem; line-height: 1;">' . $count . '</span>'
        . '</span>';
} else {
    echo '<span class="rxn-correo-badge text-muted"'
        . ' title="Sin envíos"'
        . ' data-bs-toggle="tooltip" data-bs-placement="top">'
        . '<i class="bi bi-envelope" style="font-size: 1.05rem; opacity: 0.55;"></i>'
        . '</span>';
}
