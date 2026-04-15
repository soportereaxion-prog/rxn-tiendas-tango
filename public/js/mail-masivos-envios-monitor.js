/* ============================================================
   Mail Masivos — Envíos / Monitor (Fase 4)
   Polling cada 3 seg al endpoint de status. Actualiza
   barra de progreso, contadores y badge de estado sin reload.
   Se detiene automáticamente cuando el job llega a estado final.
   ============================================================ */
(function () {
    'use strict';

    const cfg = window.MailEnviosMonitor || {};
    if (!cfg.apiStatus || !cfg.jobId) {
        console.error('[MailEnviosMonitor] Falta config global.');
        return;
    }

    const POLL_INTERVAL_MS = 3000;

    const $badge = document.getElementById('job-estado-badge');
    const $progresoTexto = document.getElementById('progreso-texto');
    const $barOk = document.getElementById('bar-ok');
    const $barFail = document.getElementById('bar-fail');
    const $barSkp = document.getElementById('bar-skp');
    const $statPending = document.getElementById('stat-pending');
    const $statOk = document.getElementById('stat-ok');
    const $statFail = document.getElementById('stat-fail');
    const $statSkp = document.getElementById('stat-skp');
    const $pollDot = document.getElementById('poll-dot');
    const $pollHint = document.getElementById('poll-hint');

    const estadoLabelMap = {
        queued:    { cls: 'bg-secondary',            icon: 'bi-hourglass-split',         label: 'En cola' },
        running:   { cls: 'bg-primary',              icon: 'bi-arrow-repeat',            label: 'Enviando' },
        paused:    { cls: 'bg-warning text-dark',    icon: 'bi-pause-circle',            label: 'Pausado' },
        completed: { cls: 'bg-success',              icon: 'bi-check-circle-fill',       label: 'Completado' },
        cancelled: { cls: 'bg-warning text-dark',    icon: 'bi-x-circle',                label: 'Cancelado' },
        failed:    { cls: 'bg-danger',               icon: 'bi-exclamation-triangle-fill', label: 'Error' },
    };

    let timer = null;
    let lastEstado = null;

    async function pollOnce() {
        try {
            const resp = await fetch(cfg.apiStatus, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.success || !data.job) return;

            applyStatus(data.job);

            if (isFinalState(data.job.estado)) {
                stopPolling();
                // Recargar la página después de un breve delay para traer la tabla
                // completa de items con los estados finales. Simple y efectivo.
                setTimeout(() => location.reload(), 1500);
            }
        } catch (err) {
            console.warn('[MailEnviosMonitor] poll failed:', err.message);
        }
    }

    function applyStatus(job) {
        // Badge
        const meta = estadoLabelMap[job.estado] || { cls: 'bg-light text-dark', icon: 'bi-question-circle', label: job.estado };
        if (lastEstado !== job.estado) {
            $badge.className = 'badge ' + meta.cls + ' fs-6';
            $badge.innerHTML = '<i class="bi ' + meta.icon + '"></i> ' + meta.label;
            lastEstado = job.estado;
        }

        const total = parseInt(job.total_destinatarios || cfg.total || 0, 10);
        const ok = parseInt(job.total_enviados || 0, 10);
        const fail = parseInt(job.total_fallidos || 0, 10);
        const skp = parseInt(job.total_skipped || 0, 10);
        const done = ok + fail + skp;
        const pct = total > 0 ? Math.floor(done * 100 / total) : 0;

        $progresoTexto.textContent = done + ' de ' + total + ' (' + pct + '%)';

        if (total > 0) {
            $barOk.style.width = (ok * 100 / total) + '%';
            $barFail.style.width = (fail * 100 / total) + '%';
            $barSkp.style.width = (skp * 100 / total) + '%';
            $barOk.textContent = ok > 0 ? ok : '';
            $barFail.textContent = fail > 0 ? fail : '';
            $barSkp.textContent = skp > 0 ? skp : '';
        }

        if ($statPending) $statPending.textContent = Math.max(0, total - done);
        if ($statOk) $statOk.textContent = ok;
        if ($statFail) $statFail.textContent = fail;
        if ($statSkp) $statSkp.textContent = skp;

        // Animation on progress bars while running
        if (job.estado === 'running') {
            $barOk.classList.add('progress-bar-animated-local');
        } else {
            $barOk.classList.remove('progress-bar-animated-local');
        }
    }

    function isFinalState(estado) {
        return estado === 'completed' || estado === 'cancelled' || estado === 'failed';
    }

    function startPolling() {
        if (timer) return;
        $pollDot.classList.remove('is-idle');
        $pollHint.textContent = 'Actualiza cada ' + (POLL_INTERVAL_MS / 1000) + ' seg';
        timer = setInterval(pollOnce, POLL_INTERVAL_MS);
        pollOnce(); // primer tick inmediato
    }

    function stopPolling() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        $pollDot.classList.add('is-idle');
        $pollHint.textContent = 'Cerrado — no refresca';
    }

    if (cfg.isFinal) {
        $pollDot.classList.add('is-idle');
    } else {
        startPolling();
    }
})();
