<?php
/**
 * Partial — ícono RXN del header de la PWA.
 *
 * Estrategia: si existe `public/icons/rxnpwa-source.png` (lo provee Charly con
 * el arte final), se usa esa imagen. Sino, se muestra un SVG inline aproximando
 * la estrella RXN (8 puntas alternadas blanco/rojo + flash central). El SVG es
 * robusto: no depende de archivos ni del SW, siempre se ve.
 *
 * Para reemplazar por el arte final: copiar el PNG a public/icons/rxnpwa-source.png
 * y correr `tools/generate_rxnpwa_icons_from_source.php` (regenera 192/512 con
 * safe-area de 12% para máscaras circulares).
 */
$sourceAbs = BASE_PATH . '/public/icons/rxnpwa-source.png';
$hasSource = is_file($sourceAbs);
$sourcePng = $hasSource ? ('/icons/rxnpwa-source.png?v=' . filemtime($sourceAbs)) : '';
?>
<span class="rxnpwa-brand-icon" aria-label="RXN">
    <?php if ($hasSource): ?>
        <img src="<?= htmlspecialchars($sourcePng) ?>" alt="RXN" width="32" height="32">
    <?php else: ?>
        <svg viewBox="0 0 100 100" width="32" height="32" aria-hidden="true">
            <g transform="translate(50,50)" stroke="#0f172a" stroke-width="2" stroke-linejoin="round">
                <!-- 4 flechas blancas (N/S/E/W) -->
                <path d="M 0,-42 L -10,-14 L 10,-14 Z" fill="#e5e7eb"/>
                <path d="M 0,42 L -10,14 L 10,14 Z" fill="#e5e7eb"/>
                <path d="M 42,0 L 14,-10 L 14,10 Z" fill="#e5e7eb"/>
                <path d="M -42,0 L -14,-10 L -14,10 Z" fill="#e5e7eb"/>
                <!-- 4 flechas rojas (diagonales) -->
                <g transform="rotate(45)">
                    <path d="M 0,-42 L -10,-14 L 10,-14 Z" fill="#dc2626"/>
                    <path d="M 0,42 L -10,14 L 10,14 Z" fill="#dc2626"/>
                    <path d="M 42,0 L 14,-10 L 14,10 Z" fill="#dc2626"/>
                    <path d="M -42,0 L -14,-10 L -14,10 Z" fill="#dc2626"/>
                </g>
                <!-- Flash central -->
                <circle r="4" fill="#ffffff"/>
                <circle r="2" fill="#ffffff" opacity="0.9"/>
            </g>
        </svg>
    <?php endif; ?>
</span>
