<?php

/**
 * Partial reusable: panel de adjuntos para cualquier owner.
 *
 * Variables esperadas (las setea el caller antes del include):
 *   $ownerType (string)       — ej: 'crm_nota', 'crm_presupuesto'
 *   $ownerId   (int|null)     — null si la entidad todavía no se guardó
 *   $panelTitle (string|null) — opcional, default "Archivos adjuntos"
 *
 * Uso típico en un form.php:
 *
 *   <?php
 *       $ownerType = 'crm_nota';
 *       $ownerId   = $isEdit ? (int) $nota->id : null;
 *       include BASE_PATH . '/app/shared/views/partials/attachments-panel.php';
 *   ?>
 *
 * Comportamiento:
 *   - Si $ownerId es null (creación): muestra aviso "guardá primero".
 *   - Si $ownerId existe: carga la lista actual + drag & drop para subir + delete inline.
 *   - Endpoint de descarga: link directo a /attachments/{id}/download.
 */

use App\Core\CsrfHelper;
use App\Core\Services\AttachmentService;

/** @var string $ownerType */
/** @var int|null $ownerId */
$ownerType   = isset($ownerType) ? (string) $ownerType : '';
$ownerId     = isset($ownerId) && $ownerId !== null ? (int) $ownerId : null;
$panelTitle  = isset($panelTitle) && $panelTitle !== '' ? (string) $panelTitle : 'Archivos adjuntos';
$csrfToken   = CsrfHelper::generateToken();

$existing = [];
$limits   = [
    'max_files_per_owner'       => 10,
    'max_file_size_bytes'       => 100 * 1024 * 1024,
    'max_total_bytes_per_owner' => 100 * 1024 * 1024,
];

if ($ownerId !== null && $ownerType !== '') {
    try {
        $service  = new AttachmentService();
        $empresaId = (int) (\App\Core\Context::getEmpresaId() ?? 0);
        if ($empresaId > 0) {
            $existing = $service->listByOwner($empresaId, $ownerType, $ownerId);
        }
        $limits = $service->getLimits();
    } catch (\Throwable) {
        $existing = [];
    }
}

$maxFileMb  = (int) round($limits['max_file_size_bytes'] / (1024 * 1024));
$maxTotalMb = (int) round($limits['max_total_bytes_per_owner'] / (1024 * 1024));

$panelId = 'attachments-panel-' . bin2hex(random_bytes(4));
?>

<div
    class="attachments-panel"
    id="<?= htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8') ?>"
    data-attachments-panel
    data-owner-type="<?= htmlspecialchars($ownerType, ENT_QUOTES, 'UTF-8') ?>"
    data-owner-id="<?= $ownerId !== null ? (int) $ownerId : '' ?>"
    data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-max-files="<?= (int) $limits['max_files_per_owner'] ?>"
    data-max-file-bytes="<?= (int) $limits['max_file_size_bytes'] ?>"
    data-max-total-bytes="<?= (int) $limits['max_total_bytes_per_owner'] ?>"
    style="margin-top:1rem;border:1px solid #e3e3e3;border-radius:8px;padding:1rem;background:#fafafa;"
>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
        <h4 style="margin:0;font-size:1rem;">📎 <?= htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') ?></h4>
        <small style="color:#666;">
            Máx <?= (int) $limits['max_files_per_owner'] ?> archivos · <?= $maxFileMb ?> MB c/u · <?= $maxTotalMb ?> MB total
        </small>
    </div>

    <?php if ($ownerId === null): ?>
        <div style="padding:1rem;background:#fff5e1;border-radius:6px;color:#7a4f01;">
            Guardá primero el registro para poder adjuntar archivos.
        </div>
    <?php else: ?>

        <div class="attachments-dropzone"
             data-dropzone
             style="border:2px dashed #b8b8b8;border-radius:6px;padding:1.25rem;text-align:center;color:#555;cursor:pointer;background:#fff;transition:background .15s ease,border-color .15s ease;">
            <div style="pointer-events:none;">
                <strong>Arrastrá archivos acá</strong> o
                <button type="button" data-browse style="background:none;border:none;color:#1260a8;text-decoration:underline;cursor:pointer;padding:0;">seleccioná uno</button>
            </div>
            <input type="file" data-file-input multiple hidden>
        </div>

        <ul class="attachments-list" data-list style="list-style:none;padding:0;margin:.75rem 0 0 0;">
            <?php foreach ($existing as $att): ?>
                <?php
                    $sizeMb  = number_format(((int) $att['size_bytes']) / (1024 * 1024), 2);
                    $mime    = (string) ($att['mime'] ?? '');
                    $isImage = str_starts_with($mime, 'image/');
                ?>
                <li data-attachment-id="<?= (int) $att['id'] ?>"
                    data-mime="<?= htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') ?>"
                    style="display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;border-bottom:1px solid #eee;">
                    <?php if ($isImage): ?>
                        <button type="button"
                                data-preview
                                title="Previsualizar imagen"
                                style="background:none;border:none;color:#1260a8;cursor:pointer;font-size:1rem;padding:0 .25rem;">👁</button>
                    <?php endif; ?>
                    <span style="flex:1;">
                        <a href="/attachments/<?= (int) $att['id'] ?>/download"
                           style="color:#1260a8;text-decoration:none;">
                            <?= htmlspecialchars((string) $att['original_name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <small style="color:#888;margin-left:.5rem;"><?= $sizeMb ?> MB</small>
                    </span>
                    <button type="button"
                            data-delete
                            title="Eliminar adjunto"
                            style="background:none;border:none;color:#b00;cursor:pointer;font-size:1rem;">✕</button>
                </li>
            <?php endforeach; ?>
        </ul>

        <div data-status role="status" aria-live="polite" style="margin-top:.5rem;font-size:.875rem;color:#666;"></div>

    <?php endif; ?>
</div>

<script>
(function () {
    if (window.__rxnAttachmentsPanelInit) return;
    window.__rxnAttachmentsPanelInit = true;

    function fmtBytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function isImageMime(m) {
        return typeof m === 'string' && m.indexOf('image/') === 0;
    }

    // Modal singleton inyectado una sola vez en body.
    var previewModal = null;
    function ensurePreviewModal() {
        if (previewModal) return previewModal;
        var overlay = document.createElement('div');
        overlay.setAttribute('data-preview-modal', '');
        overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;';
        overlay.innerHTML =
            '<img data-preview-img alt="" style="max-width:92vw;max-height:92vh;box-shadow:0 6px 32px rgba(0,0,0,.6);border-radius:4px;background:#fff;">' +
            '<button type="button" data-preview-close aria-label="Cerrar" style="position:absolute;top:14px;right:22px;background:none;border:none;color:#fff;font-size:2.2rem;cursor:pointer;line-height:1;">&times;</button>';
        document.body.appendChild(overlay);

        function close() {
            overlay.style.display = 'none';
            overlay.querySelector('[data-preview-img]').src = '';
        }
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.matches('[data-preview-close]')) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') close();
        });

        previewModal = overlay;
        return overlay;
    }

    function openPreview(attachmentId) {
        var modal = ensurePreviewModal();
        var img = modal.querySelector('[data-preview-img]');
        img.src = '/attachments/' + attachmentId + '/preview';
        modal.style.display = 'flex';
    }

    function initPanel(panel) {
        if (panel.__init) return;
        panel.__init = true;

        var ownerType    = panel.getAttribute('data-owner-type');
        var ownerId      = panel.getAttribute('data-owner-id');
        var csrf         = panel.getAttribute('data-csrf');
        var maxFiles     = parseInt(panel.getAttribute('data-max-files') || '10', 10);
        var maxFileBytes = parseInt(panel.getAttribute('data-max-file-bytes') || '104857600', 10);

        if (!ownerId) return; // modo pre-create, no hay UI de upload

        var dropzone = panel.querySelector('[data-dropzone]');
        var input    = panel.querySelector('[data-file-input]');
        var list     = panel.querySelector('[data-list]');
        var status   = panel.querySelector('[data-status]');
        var browse   = panel.querySelector('[data-browse]');

        function setStatus(msg, isError) {
            status.textContent = msg || '';
            status.style.color = isError ? '#b00' : '#666';
        }

        function currentCount() {
            return list ? list.querySelectorAll('li[data-attachment-id]').length : 0;
        }

        function appendRow(att) {
            var li = document.createElement('li');
            li.setAttribute('data-attachment-id', att.id);
            li.setAttribute('data-mime', att.mime || '');
            li.style.cssText = 'display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;border-bottom:1px solid #eee;';

            var previewBtn = isImageMime(att.mime)
                ? '<button type="button" data-preview title="Previsualizar imagen" style="background:none;border:none;color:#1260a8;cursor:pointer;font-size:1rem;padding:0 .25rem;">👁</button>'
                : '';

            li.innerHTML =
                previewBtn +
                '<span style="flex:1;">' +
                    '<a href="/attachments/' + att.id + '/download" style="color:#1260a8;text-decoration:none;"></a>' +
                    '<small style="color:#888;margin-left:.5rem;">' + fmtBytes(att.size_bytes) + '</small>' +
                '</span>' +
                '<button type="button" data-delete title="Eliminar adjunto" style="background:none;border:none;color:#b00;cursor:pointer;font-size:1rem;">✕</button>';
            li.querySelector('a').textContent = att.original_name;
            list.appendChild(li);
        }

        function uploadOne(file) {
            if (currentCount() >= maxFiles) {
                setStatus('Llegaste al máximo de ' + maxFiles + ' archivos.', true);
                return Promise.resolve();
            }
            if (file.size > maxFileBytes) {
                setStatus('"' + file.name + '" supera el tope por archivo (' + fmtBytes(maxFileBytes) + ').', true);
                return Promise.resolve();
            }

            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('owner_type', ownerType);
            fd.append('owner_id', ownerId);
            fd.append('file', file);

            setStatus('Subiendo "' + file.name + '"…');

            return fetch('/attachments/upload', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            }).then(function (res) {
                if (!res.ok || !res.data.success) {
                    setStatus(res.data.error || 'Error subiendo el archivo.', true);
                    return;
                }
                appendRow(res.data.attachment);
                setStatus('"' + file.name + '" subido correctamente.');
            }).catch(function () {
                setStatus('Error de red al subir el archivo.', true);
            });
        }

        function handleFiles(fileList) {
            if (!fileList || !fileList.length) return;
            var files = Array.prototype.slice.call(fileList);
            var chain = Promise.resolve();
            files.forEach(function (f) {
                chain = chain.then(function () { return uploadOne(f); });
            });
        }

        // Click en "seleccioná uno" o en la zona.
        dropzone.addEventListener('click', function (e) {
            if (e.target && e.target.closest('[data-delete]')) return;
            input.click();
        });
        if (browse) browse.addEventListener('click', function (e) { e.stopPropagation(); input.click(); });

        input.addEventListener('change', function () {
            handleFiles(input.files);
            input.value = '';
        });

        // Drag & drop.
        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault(); e.stopPropagation();
                dropzone.style.background = '#eef5ff';
                dropzone.style.borderColor = '#1260a8';
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault(); e.stopPropagation();
                dropzone.style.background = '#fff';
                dropzone.style.borderColor = '#b8b8b8';
            });
        });
        dropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files) handleFiles(e.dataTransfer.files);
        });

        // Preview (delegado).
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-preview]');
            if (!btn) return;
            var li = btn.closest('li[data-attachment-id]');
            if (!li) return;
            var id = li.getAttribute('data-attachment-id');
            if (id) openPreview(id);
        });

        // Delete (delegado).
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-delete]');
            if (!btn) return;
            var li = btn.closest('li[data-attachment-id]');
            if (!li) return;
            var id = li.getAttribute('data-attachment-id');
            if (!confirm('¿Eliminar este adjunto?')) return;

            var fd = new FormData();
            fd.append('csrf_token', csrf);

            fetch('/attachments/' + id + '/delete', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            }).then(function (res) {
                if (res.ok && res.data.success) {
                    li.remove();
                    setStatus('Adjunto eliminado.');
                } else {
                    setStatus((res.data && res.data.error) || 'No se pudo eliminar.', true);
                }
            }).catch(function () {
                setStatus('Error de red al eliminar.', true);
            });
        });
    }

    function initAll(root) {
        (root || document).querySelectorAll('[data-attachments-panel]').forEach(initPanel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }

    // Expuesto para paneles inyectados dinámicamente por JS (ej: dentro de un modal).
    window.RxnAttachments = { initAll: initAll };
})();
</script>
