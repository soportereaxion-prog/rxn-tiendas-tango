(function () {
    var LAYOUT_KEY = 'rxn_module_notes_widget_layout_v4';
    var DESKTOP_BREAKPOINT = 768;
    var MIN_WIDTH = 320;
    var MIN_HEIGHT = 360;
    var VIEWPORT_MARGIN = 8;
    var DOCK_OFFSET = 16;

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function isDesktopViewport() {
        return window.innerWidth >= DESKTOP_BREAKPOINT;
    }

    function readLayout() {
        try {
            return JSON.parse(window.localStorage.getItem(LAYOUT_KEY) || 'null');
        } catch (error) {
            return null;
        }
    }

    function writeLayout(layout) {
        try {
            window.localStorage.setItem(LAYOUT_KEY, JSON.stringify(layout));
        } catch (error) {
            // noop
        }
    }

    function extractImagesFromClipboard(event) {
        var clipboard = event.clipboardData;
        var files = [];

        if (!clipboard || !clipboard.items) {
            return files;
        }

        for (var index = 0; index < clipboard.items.length; index += 1) {
            var item = clipboard.items[index];

            if (item.type && item.type.indexOf('image/') === 0) {
                files.push(item.getAsFile());
            }
        }

        return files.filter(Boolean);
    }

    function applyFilesToInput(input, files) {
        var transfer = new DataTransfer();

        files.forEach(function (file) {
            transfer.items.add(file);
        });

        input.files = transfer.files;
    }

    function setupWidget(widget) {
        var body = widget.querySelector('[data-module-notes-body]');
        var dragHandle = widget.querySelector('[data-module-notes-drag-handle]');
        var resizeHandle = widget.querySelector('[data-module-notes-resize-handle]');
        var toggleButton = widget.querySelector('[data-module-notes-toggle]');
        var chooseButton = widget.querySelector('[data-module-notes-choose]');
        var clearAllButton = widget.querySelector('[data-module-notes-clear-all]');
        var pastezone = widget.querySelector('[data-module-notes-pastezone]');
        var fileInput = widget.querySelector('[data-module-notes-file]');
        var labelsInput = widget.querySelector('[data-module-notes-labels]');
        var textarea = widget.querySelector('[data-module-notes-content]');
        var previewGrid = widget.querySelector('[data-module-notes-preview-grid]');
        var launcherButton = widget.querySelector('[data-module-notes-launcher]');
        var attachments = [];
        var dragState = null;
        var resizeState = null;
        var layoutState = null;

        function nextAttachmentIndex() {
            return attachments.reduce(function (max, attachment) {
                var match = /^#imagen(\d+)$/i.exec(attachment.label || '');
                var current = match ? parseInt(match[1], 10) : 0;
                return Math.max(max, current);
            }, 0) + 1;
        }

        function setOpen(isOpen, shouldPersist) {
            widget.classList.toggle('is-collapsed', !isOpen);

            if (isOpen) {
                restoreExpandedWidget();
            } else {
                dockCollapsedWidget();
            }

            if (toggleButton) {
                toggleButton.textContent = isOpen ? 'Minimizar' : 'Restaurar';
                toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            if (shouldPersist !== false) {
                saveLayout();
            }
        }

        function saveLayout() {
            if (!isDesktopViewport()) {
                return;
            }

            var nextLayout = layoutState && typeof layoutState === 'object' ? Object.assign({}, layoutState) : {};
            var isCollapsed = widget.classList.contains('is-collapsed');
            nextLayout.collapsed = isCollapsed;

            if (!isCollapsed) {
                var rect = widget.getBoundingClientRect();

                nextLayout.width = Math.round(rect.width);
                nextLayout.height = Math.round(rect.height);
                nextLayout.left = Math.round(rect.left);
                nextLayout.top = Math.round(rect.top);
            }

            layoutState = nextLayout;
            writeLayout(nextLayout);
        }

        function dockCollapsedWidget() {
            widget.style.left = 'auto';
            widget.style.top = 'auto';
            widget.style.right = (isDesktopViewport() ? DOCK_OFFSET : 12) + 'px';
            widget.style.bottom = (isDesktopViewport() ? DOCK_OFFSET : 12) + 'px';
            widget.style.width = '';
            widget.style.height = '';
        }

        function restoreExpandedWidget() {
            if (!isDesktopViewport()) {
                clearDesktopPositioning();
                return;
            }

            var layout = layoutState && typeof layoutState === 'object' ? layoutState : {};

            widget.style.right = 'auto';
            widget.style.bottom = 'auto';

            if (Number.isFinite(layout.width)) {
                widget.style.width = clamp(layout.width, MIN_WIDTH, window.innerWidth - VIEWPORT_MARGIN * 2) + 'px';
            } else {
                widget.style.width = '';
            }

            if (Number.isFinite(layout.height)) {
                widget.style.height = clamp(layout.height, MIN_HEIGHT, window.innerHeight - VIEWPORT_MARGIN * 2) + 'px';
            } else {
                widget.style.height = '';
            }

            if (Number.isFinite(layout.left) && Number.isFinite(layout.top)) {
                widget.style.left = layout.left + 'px';
                widget.style.top = layout.top + 'px';
                ensureViewportBounds();
                return;
            }

            widget.style.left = '';
            widget.style.top = '';
            widget.style.right = '';
            widget.style.bottom = '';
        }

        function clearDesktopPositioning() {
            widget.style.left = '';
            widget.style.top = '';
            widget.style.width = '';
            widget.style.height = '';
            widget.style.right = '';
            widget.style.bottom = '';
        }

        function ensureViewportBounds() {
            if (!isDesktopViewport()) {
                clearDesktopPositioning();
                return;
            }

            var rect = widget.getBoundingClientRect();
            var maxLeft = Math.max(VIEWPORT_MARGIN, window.innerWidth - rect.width - VIEWPORT_MARGIN);
            var maxTop = Math.max(VIEWPORT_MARGIN, window.innerHeight - rect.height - VIEWPORT_MARGIN);
            var nextLeft = clamp(rect.left, VIEWPORT_MARGIN, maxLeft);
            var nextTop = clamp(rect.top, VIEWPORT_MARGIN, maxTop);

            widget.style.left = nextLeft + 'px';
            widget.style.top = nextTop + 'px';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        }

        function applySavedLayout() {
            var layout = readLayout();

            layoutState = layout && typeof layout === 'object' ? layout : {};

            if (!layout) {
                setOpen(widget.dataset.defaultOpen === '1', false);
                return;
            }

            setOpen(!layout.collapsed, false);

            saveLayout();
        }

        function updateHiddenInputs() {
            applyFilesToInput(fileInput, attachments.map(function (attachment) {
                return attachment.file;
            }));

            if (labelsInput) {
                labelsInput.value = JSON.stringify(attachments.map(function (attachment) {
                    return attachment.label;
                }));
            }
        }

        function insertReferences(labels) {
            if (!textarea || !labels.length) {
                return;
            }

            var insertion = labels.join(' ');
            var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
            var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : textarea.value.length;
            var before = textarea.value.slice(0, start);
            var after = textarea.value.slice(end);
            var prefix = before && !/\s$/.test(before) ? ' ' : '';
            var suffix = after && !/^\s/.test(after) ? ' ' : '';
            var nextValue = before + prefix + insertion + suffix + after;
            var caretPosition = (before + prefix + insertion + suffix).length;

            textarea.value = nextValue;
            textarea.focus();
            textarea.setSelectionRange(caretPosition, caretPosition);
        }

        function renderPreviews() {
            if (!previewGrid) {
                return;
            }

            previewGrid.innerHTML = '';

            if (!attachments.length) {
                previewGrid.classList.add('d-none');

                if (clearAllButton) {
                    clearAllButton.classList.add('d-none');
                }

                return;
            }

            attachments.forEach(function (attachment, index) {
                var card = document.createElement('div');
                card.className = 'rxn-module-notes-preview-card';
                card.innerHTML = [
                    '<div class="d-flex justify-content-between align-items-center gap-2 mb-2">',
                    '  <span class="badge text-bg-dark">' + attachment.label + '</span>',
                    '  <button type="button" class="btn btn-outline-danger btn-sm" data-module-notes-remove="' + index + '">x</button>',
                    '</div>',
                    '  <img src="' + attachment.previewUrl + '" alt="' + attachment.label + '">',
                    '  <div class="small text-muted mt-2 text-truncate">' + attachment.displayName + '</div>'
                ].join('');
                previewGrid.appendChild(card);
            });

            previewGrid.classList.remove('d-none');

            if (clearAllButton) {
                clearAllButton.classList.remove('d-none');
            }
        }

        function syncAttachments() {
            updateHiddenInputs();
            renderPreviews();
        }

        function addFiles(files, shouldInsertReferences) {
            var labels = [];

            files.forEach(function (file) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                    return;
                }

                if (attachments.length >= 6) {
                    return;
                }

                var label = '#imagen' + nextAttachmentIndex();
                var previewUrl = URL.createObjectURL(file);
                var displayName = file.name && file.name !== '' ? file.name : label + '.png';

                attachments.push({
                    file: file,
                    label: label,
                    previewUrl: previewUrl,
                    displayName: displayName
                });
                labels.push(label);
            });

            syncAttachments();

            if (shouldInsertReferences && labels.length) {
                insertReferences(labels);
            }
        }

        function clearAttachments() {
            attachments.forEach(function (attachment) {
                URL.revokeObjectURL(attachment.previewUrl);
            });
            attachments = [];
            syncAttachments();
        }

        function removeAttachment(index) {
            if (index < 0 || index >= attachments.length) {
                return;
            }

            URL.revokeObjectURL(attachments[index].previewUrl);
            attachments.splice(index, 1);
            syncAttachments();
        }

        function startDrag(event) {
            if (!isDesktopViewport()) {
                return;
            }

            if (widget.classList.contains('is-collapsed')) {
                return;
            }

            if (event.target.closest('button, a')) {
                return;
            }

            event.preventDefault();
            ensureViewportBounds();

            var rect = widget.getBoundingClientRect();
            dragState = {
                offsetX: event.clientX - rect.left,
                offsetY: event.clientY - rect.top
            };

            widget.classList.add('is-dragging');
            document.body.classList.add('rxn-module-notes-no-select');
        }

        function onDrag(event) {
            if (!dragState) {
                return;
            }

            var rect = widget.getBoundingClientRect();
            var nextLeft = clamp(event.clientX - dragState.offsetX, VIEWPORT_MARGIN, window.innerWidth - rect.width - VIEWPORT_MARGIN);
            var nextTop = clamp(event.clientY - dragState.offsetY, VIEWPORT_MARGIN, window.innerHeight - rect.height - VIEWPORT_MARGIN);

            widget.style.left = nextLeft + 'px';
            widget.style.top = nextTop + 'px';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        }

        function stopDrag() {
            if (!dragState) {
                return;
            }

            dragState = null;
            widget.classList.remove('is-dragging');
            document.body.classList.remove('rxn-module-notes-no-select');
            saveLayout();
        }

        function startResize(event) {
            if (!isDesktopViewport()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            ensureViewportBounds();

            var rect = widget.getBoundingClientRect();
            resizeState = {
                startX: event.clientX,
                startY: event.clientY,
                startWidth: rect.width,
                startHeight: rect.height,
                left: rect.left,
                top: rect.top
            };

            document.body.classList.add('rxn-module-notes-no-select');
        }

        function onResize(event) {
            if (!resizeState) {
                return;
            }

            var nextWidth = clamp(
                resizeState.startWidth + (event.clientX - resizeState.startX),
                MIN_WIDTH,
                window.innerWidth - resizeState.left - VIEWPORT_MARGIN
            );
            var nextHeight = clamp(
                resizeState.startHeight + (event.clientY - resizeState.startY),
                MIN_HEIGHT,
                window.innerHeight - resizeState.top - VIEWPORT_MARGIN
            );

            widget.style.width = nextWidth + 'px';
            widget.style.height = nextHeight + 'px';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        }

        function stopResize() {
            if (!resizeState) {
                return;
            }

            resizeState = null;
            document.body.classList.remove('rxn-module-notes-no-select');
            saveLayout();
        }

        if (toggleButton) {
            toggleButton.addEventListener('click', function () {
                setOpen(widget.classList.contains('is-collapsed'));
            });
        }

        if (launcherButton) {
            launcherButton.addEventListener('click', function () {
                setOpen(true);
            });
        }

        if (chooseButton) {
            chooseButton.addEventListener('click', function () {
                fileInput.click();
            });
        }

        if (clearAllButton) {
            clearAllButton.addEventListener('click', function () {
                clearAttachments();
            });
        }

        if (previewGrid) {
            previewGrid.addEventListener('click', function (event) {
                var button = event.target.closest('[data-module-notes-remove]');

                if (!button) {
                    return;
                }

                removeAttachment(parseInt(button.getAttribute('data-module-notes-remove'), 10));
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                addFiles(Array.prototype.slice.call(fileInput.files || []), true);
            });
        }

        if (pastezone) {
            pastezone.addEventListener('paste', function (event) {
                var files = extractImagesFromClipboard(event);

                if (!files.length) {
                    return;
                }

                event.preventDefault();
                addFiles(files, true);
            });

            pastezone.addEventListener('dragover', function (event) {
                event.preventDefault();
                pastezone.classList.add('is-active');
            });

            pastezone.addEventListener('dragleave', function () {
                pastezone.classList.remove('is-active');
            });

            pastezone.addEventListener('drop', function (event) {
                event.preventDefault();
                pastezone.classList.remove('is-active');

                var droppedFiles = Array.prototype.slice.call((event.dataTransfer && event.dataTransfer.files) || []);
                addFiles(droppedFiles, true);
            });
        }

        if (textarea) {
            textarea.addEventListener('paste', function (event) {
                var files = extractImagesFromClipboard(event);

                if (!files.length) {
                    return;
                }

                event.preventDefault();
                addFiles(files, true);
            });
        }

        if (dragHandle) {
            dragHandle.addEventListener('click', function (event) {
                if (!widget.classList.contains('is-collapsed')) {
                    return;
                }

                if (event.target.closest('button, a')) {
                    return;
                }

                setOpen(true);
            });

            dragHandle.addEventListener('mousedown', startDrag);
        }

        if (resizeHandle) {
            resizeHandle.addEventListener('mousedown', startResize);
        }

        document.addEventListener('mousemove', function (event) {
            onDrag(event);
            onResize(event);
        });

        document.addEventListener('mouseup', function () {
            stopDrag();
            stopResize();
        });

        window.addEventListener('resize', function () {
            if (widget.classList.contains('is-collapsed')) {
                dockCollapsedWidget();
            } else {
                ensureViewportBounds();
            }
            saveLayout();
        });

        applySavedLayout();
        syncAttachments();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-module-notes-widget]').forEach(setupWidget);
    });
})();
