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
        . '<i class="bi bi-envelope-x-fill" style="font-size: 1.05rem;"></i>'
        . '<span class="badge bg-danger" style="font-size: 0.62rem; padding: 0.15rem 0.35rem; line-height: 1;">' . $count . '</span>'
        . '</span>';
} else {
    if ($count === 0) {
        $tooltip = 'Sin envíos todavía';
    } else {
        $tooltip = 'Enviado ' . $count . ($count === 1 ? ' vez' : ' veces')
                 . (!empty($ultimoEnvio) ? ' — último: ' . $fmt($ultimoEnvio) : '');
    }
    $iconClass = $count === 0 ? 'bi-envelope' : 'bi-envelope-check-fill';
    $badgeClass = $count === 0 ? 'bg-secondary' : 'bg-success';
    $textClass = $count === 0 ? 'text-secondary' : 'text-success';
    echo '<span class="rxn-correo-badge ' . $textClass . ' d-inline-flex align-items-center gap-1" style="position: relative;"'
        . ' title="' . htmlspecialchars($tooltip, ENT_QUOTES) . '"'
        . ' data-bs-toggle="tooltip" data-bs-placement="top">'
        . '<i class="bi ' . $iconClass . '" style="font-size: 1.05rem;"></i>'
        . '<span class="badge ' . $badgeClass . '" style="font-size: 0.62rem; padding: 0.15rem 0.35rem; line-height: 1;">' . $count . '</span>'
        . '</span>';
}
