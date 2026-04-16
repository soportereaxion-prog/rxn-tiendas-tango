/**
 * rxn-geo-consent.js
 *
 * Maneja la interacción del banner de consentimiento de geo-tracking.
 *
 * Cuando el usuario clickea "Acepto" / "No acepto" / "Decidir después":
 *   1. POST a /geo-tracking/consent con { decision }.
 *   2. Si success → oculta el banner.
 *   3. Si error → deja el banner visible y loguea en console.
 *
 * El banner solo existe en el DOM si el servidor decidió mostrarlo (el partial PHP
 * chequea `tieneConsentimientoVigente()` antes de renderizarlo). Por eso este JS
 * no necesita chequear "¿hay que mostrarlo?" — si el div está, hay que mostrarlo.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const banner = document.getElementById('rxn-geo-consent-banner');
        if (!banner) {
            return; // Usuario ya respondió o el módulo está deshabilitado.
        }

        const buttons = banner.querySelectorAll('[data-rxn-geo-consent]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const decision = btn.getAttribute('data-rxn-geo-consent');
                if (!decision) return;

                // Deshabilitar todos los botones para evitar doble-click.
                buttons.forEach(b => { b.disabled = true; });

                fetch('/geo-tracking/consent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ decision: decision }),
                })
                    .then(function (res) {
                        return res.json().catch(function () { return { success: false }; });
                    })
                    .then(function (data) {
                        if (data && data.success) {
                            // Fade out + remove.
                            banner.style.transition = 'opacity 0.3s ease';
                            banner.style.opacity = '0';
                            setTimeout(function () {
                                banner.remove();
                            }, 300);

                            // Notificar al helper de tracking que el consentimiento cambió
                            // (por si necesita reaccionar — por ejemplo, si había un evento
                            // pendiente de reportar posición).
                            window.dispatchEvent(new CustomEvent('rxn:geo-consent-changed', {
                                detail: { decision: decision },
                            }));
                        } else {
                            // Reactivar botones para reintentar.
                            buttons.forEach(b => { b.disabled = false; });
                            console.warn('[RxnGeoConsent] Falló el registro del consentimiento:', data);
                        }
                    })
                    .catch(function (err) {
                        buttons.forEach(b => { b.disabled = false; });
                        console.error('[RxnGeoConsent] Error de red:', err);
                    });
            });
        });
    });
})();
