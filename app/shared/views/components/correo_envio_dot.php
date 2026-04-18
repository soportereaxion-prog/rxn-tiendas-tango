<?php
/**
 * Dot flotante estilo notificación (tipo WhatsApp) para superponer al ícono del botón Enviar.
 *
 * Se renderiza DENTRO de un elemento con `position: relative` — típicamente
 * el propio <button> que ya tiene el <i class="bi bi-envelope...">.
 *
 * Espera en scope:
 *   $count         int     — correos_enviados_count
 *   $ultimoEnvio   ?string — correos_ultimo_envio_at
 *   $ultimoError   ?string — correos_ultimo_error
 *   $ultimoErrorAt ?string — correos_ultimo_error_at
 */

$count = (int) ($count ?? 0);
$ultimoEnvio = $ultimoEnvio ?? null;
$ultimoError = $ultimoError ?? null;
$ultimoErrorAt = $ultimoErrorAt ?? null;

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

if ($count === 0 && !$hayErrorVigente) {
    return; // nada que mostrar
}

$bg = $hayErrorVigente ? 'bg-danger' : 'bg-success';
$tooltip = $hayErrorVigente
    ? ('Error al enviar (' . $fmt($ultimoErrorAt) . '): ' . mb_substr((string)$ultimoError, 0, 200))
    : ('Enviado ' . $count . ($count === 1 ? ' vez' : ' veces')
        . (!empty($ultimoEnvio) ? ' — último: ' . $fmt($ultimoEnvio) : ''));
?>
<span class="position-absolute translate-middle badge rounded-pill <?= $bg ?> rxn-correo-dot"
      style="top: 2px; left: 100%; font-size: 0.65rem; padding: 0.2rem 0.38rem; line-height: 1; border: 1.5px solid var(--card-bg, #fff);"
      title="<?= htmlspecialchars($tooltip, ENT_QUOTES) ?>"
      data-bs-toggle="tooltip" data-bs-placement="top">
    <?php if ($hayErrorVigente && $count === 0): ?>
        <i class="bi bi-exclamation-triangle-fill"></i>
    <?php else: ?>
        <?= (int) $count ?>
    <?php endif; ?>
</span>
