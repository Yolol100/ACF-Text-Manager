(function (window, document) {
    'use strict';
    const app = window.WaAcfPtmAdmin || {};
    if (!app.ready) {
        return;
    }
    app.ready(function () {
        const config = app.getConfig ? app.getConfig() : {};
        const ajaxTimeoutMs = Number(config.ajaxTimeoutMs || 180000);
        const importForm = document.getElementById('wa-acf-ptm-import-form');
        const runButton = document.getElementById('wa-acf-ptm-run-button');
        const statusBox = document.getElementById('wa-acf-ptm-status');
        const fileInput = document.getElementById('wa-acf-ptm-import-file');
        const fileNameLabel = document.querySelector('.wa-acf-ptm-file-name');
        const importOptionInputs = Array.from(importForm ? importForm.querySelectorAll('.wa-acf-ptm-import-options input') : []);
        if (!importForm || !runButton || !statusBox) {
            return;
        }
        let progressClosable = false;
        let lastFocusedElement = null;
        function getString(key, fallback) {
            return app.getString ? app.getString(key, fallback) : fallback;
        }
        function escapeHtml(value) {
            return app.escapeHtml ? app.escapeHtml(value) : String(value === null || typeof value === 'undefined' ? '' : value);
        }
        function setStatus(message, isError = false) {
            statusBox.textContent = message || '';
            statusBox.classList.toggle('is-error', isError);
            statusBox.classList.toggle('is-success', !isError && Boolean(message));
            statusBox.setAttribute('role', isError ? 'alert' : 'status');
            statusBox.setAttribute('aria-live', isError ? 'assertive' : 'polite');
            if (!isError && message) {
                window.setTimeout(function () {
                    if (statusBox.textContent === message) {
                        statusBox.textContent = '';
                        statusBox.classList.remove('is-success');
                    }
                }, 2400);
            }
            if (app.announce && message) {
                app.announce(message);
            }
        }
        function createProgressModal() {
            const modal = document.createElement('div');
            modal.className = 'wa-acf-ptm-progress-modal';
            modal.setAttribute('hidden', 'hidden');
            modal.innerHTML = [
                '<div class="wa-acf-ptm-progress-backdrop" data-wa-acf-ptm-close></div>',
                '<div class="wa-acf-ptm-progress-dialog" role="dialog" aria-modal="true" aria-labelledby="wa-acf-ptm-progress-title" tabindex="-1">',
                '<div class="wa-acf-ptm-progress-header">',
                '<div class="wa-acf-ptm-progress-check" aria-hidden="true" hidden>✓</div>',
                '<div class="wa-acf-ptm-progress-copy">',
                '<span class="wa-acf-ptm-progress-kicker">' + escapeHtml(getString('importLabel', 'Import')) + '</span>',
                '<h2 id="wa-acf-ptm-progress-title">' + escapeHtml(getString('processing', 'Import wordt uitgevoerd…')) + '</h2>',
                '<p class="wa-acf-ptm-progress-text" data-wa-acf-ptm-progress-text>' + escapeHtml(getString('preparing', 'Import wordt voorbereid…')) + '</p>',
                '</div>',
                '</div>',
                '<div class="wa-acf-ptm-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-describedby="wa-acf-ptm-progress-title">',
                '<span class="wa-acf-ptm-progress-bar-fill" data-wa-acf-ptm-progress-fill style="width:0%"></span>',
                '</div>',
                '<div class="wa-acf-ptm-progress-meta"><span data-wa-acf-ptm-progress-count></span><span data-wa-acf-ptm-progress-percent>0%</span></div>',
                '<div class="wa-acf-ptm-progress-warnings" data-wa-acf-ptm-progress-warnings hidden>',
                '<strong class="wa-acf-ptm-progress-warnings-title">' + escapeHtml(getString('warningsLabel', 'Waarschuwingen')) + '</strong>',
                '<ul class="wa-acf-ptm-progress-warnings-list" data-wa-acf-ptm-progress-warnings-list></ul>',
                '</div>',
                '<div class="wa-acf-ptm-progress-actions">',
                '<button type="button" class="button button-primary wa-acf-ptm-button wa-acf-ptm-button-primary" data-wa-acf-ptm-close disabled>' + escapeHtml(getString('closeLabel', 'Sluiten')) + '</button>',
                '</div>',
                '</div>'
            ].join('');
            document.body.appendChild(modal);
            return {
                root: modal,
                dialog: modal.querySelector('.wa-acf-ptm-progress-dialog'),
                bar: modal.querySelector('.wa-acf-ptm-progress-bar'),
                percent: modal.querySelector('[data-wa-acf-ptm-progress-percent]'),
                text: modal.querySelector('[data-wa-acf-ptm-progress-text]'),
                fill: modal.querySelector('[data-wa-acf-ptm-progress-fill]'),
                count: modal.querySelector('[data-wa-acf-ptm-progress-count]'),
                title: modal.querySelector('#wa-acf-ptm-progress-title'),
                check: modal.querySelector('.wa-acf-ptm-progress-check'),
                warnings: modal.querySelector('[data-wa-acf-ptm-progress-warnings]'),
                warningsList: modal.querySelector('[data-wa-acf-ptm-progress-warnings-list]'),
                closeButtons: modal.querySelectorAll('[data-wa-acf-ptm-close]')
            };
        }
        function getFocusableElements(container) {
            return Array.from(container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(function (element) {
                return !element.disabled && !element.hasAttribute('hidden');
            });
        }
        const progressModal = createProgressModal();
        function setProgressWarnings(warnings) {
            const items = Array.isArray(warnings) ? warnings.filter(Boolean) : [];
            if (!items.length) {
                progressModal.warnings.setAttribute('hidden', 'hidden');
                progressModal.warningsList.innerHTML = '';
                return;
            }
            progressModal.warningsList.innerHTML = items.slice(0, 8).map(function (warning) {
                return '<li>' + escapeHtml(warning) + '</li>';
            }).join('');
            progressModal.warnings.removeAttribute('hidden');
        }
        function setProgressClosable(isClosable) {
            progressClosable = Boolean(isClosable);
            progressModal.closeButtons.forEach(function (button) {
                if (button.tagName === 'BUTTON') {
                    button.disabled = !progressClosable;
                }
            });
        }
        function setProgressCompleteState(isComplete, titleKey = 'completedTitle', processingKey = 'processing') {
            progressModal.root.classList.toggle('is-complete', Boolean(isComplete));
            if (progressModal.dialog) {
                progressModal.dialog.classList.toggle('is-complete', Boolean(isComplete));
            }
            if (progressModal.check) {
                progressModal.check.toggleAttribute('hidden', !isComplete);
            }
            if (progressModal.title) {
                progressModal.title.textContent = isComplete ? getString(titleKey, 'Import afgerond') : getString(processingKey, 'Import wordt uitgevoerd…');
            }
        }
        function showProgressModal(processingKey = 'processing', messageKey = 'preparing') {
            lastFocusedElement = document.activeElement;
            setProgressClosable(false);
            setProgressCompleteState(false, 'completedTitle', processingKey);
            setProgressWarnings([]);
            updateProgressModal(8, getString(messageKey, 'Import wordt voorbereid…'), undefined, undefined, processingKey);
            progressModal.root.removeAttribute('hidden');
            document.body.classList.add('wa-acf-ptm-progress-open');
            window.setTimeout(function () {
                const focusables = getFocusableElements(progressModal.dialog || progressModal.root);
                if (focusables.length) {
                    focusables[0].focus();
                }
                else if (progressModal.dialog && 'focus' in progressModal.dialog) {
                    progressModal.dialog.focus();
                }
            }, 0);
        }
        function hideProgressModal() {
            progressModal.root.setAttribute('hidden', 'hidden');
            document.body.classList.remove('wa-acf-ptm-progress-open');
            if (lastFocusedElement && 'focus' in lastFocusedElement) {
                lastFocusedElement.focus();
            }
        }
        progressModal.root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && progressClosable) {
                event.preventDefault();
                hideProgressModal();
                return;
            }
            if (event.key !== 'Tab') {
                return;
            }
            const focusables = getFocusableElements(progressModal.dialog || progressModal.root);
            if (!focusables.length) {
                return;
            }
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
            else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
        progressModal.closeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (progressClosable) {
                    hideProgressModal();
                }
            });
        });
        function updateProgressModal(percent, message, processed, total, processingKey = 'processing') {
            var _a;
            setProgressCompleteState(false, 'completedTitle', processingKey);
            const safePercent = Math.max(0, Math.min(100, Number(percent || 0)));
            progressModal.percent.textContent = safePercent + '%';
            progressModal.fill.style.width = safePercent + '%';
            (_a = progressModal.bar) === null || _a === void 0 ? void 0 : _a.setAttribute('aria-valuenow', String(safePercent));
            progressModal.text.textContent = message || getString('processing', 'Import wordt uitgevoerd…');
            if (typeof processed === 'number' && typeof total === 'number' && total > 0) {
                progressModal.count.textContent = processed + ' / ' + total + ' ' + getString('itemsProcessedLabel', 'velden verwerkt');
            }
            else {
                progressModal.count.textContent = '';
            }
        }
        function safeRedirect(url) {
            if (!url) {
                return;
            }

            try {
                const target = new URL(url, window.location.href);
                if (target.origin !== window.location.origin) {
                    return;
                }

                if (target.pathname.indexOf('/wp-admin/') === -1) {
                    return;
                }

                window.location.href = target.href;
            } catch (_error) {
                // Ignore malformed redirect URLs.
            }
        }
        async function fetchWithTimeout(url, options) {
            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timeout = window.setTimeout(function () {
                if (controller) {
                    controller.abort();
                }
            }, ajaxTimeoutMs);
            try {
                return await fetch(url, Object.assign({}, options, controller ? { signal: controller.signal } : {}));
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    throw new Error(getString('ajaxTimeout', 'De server doet te lang over de import. Gebruik een kleinere ZIP of verhoog de PHP max_execution_time/server-timeout.'));
                }
                throw error;
            } finally {
                window.clearTimeout(timeout);
            }
        }
        async function postForm(action, extra = {}) {
            const body = new FormData(importForm);
            body.append('action', action);
            body.append('nonce', config.nonce || '');
            Object.keys(extra).forEach(function (key) {
                body.append(key, extra[key]);
            });
            const response = await fetchWithTimeout(config.ajaxUrl || '', {
                method: 'POST',
                body,
                credentials: 'same-origin'
            });
            if (!response.ok) {
                return app.parseJsonResponse(response, getString('unexpectedImportResponse', 'Onverwacht serverantwoord tijdens import.'));
            }
            return app.parseJsonResponse(response, getString('unexpectedImportResponse', 'Onverwacht serverantwoord tijdens import.'));
        }
        function hasValidImportFiles() {
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                return false;
            }
            return Array.from(fileInput.files).every(function (file) {
                return /\.(csv|xlsx|zip)$/i.test(file.name || '');
            });
        }
        function updateRunButtonState() {
            runButton.disabled = !hasValidImportFiles();
        }
        updateRunButtonState();
        if (fileInput && fileNameLabel) {
            fileInput.addEventListener('change', function () {
                let fileLabel = getString('noFileChosen', 'Nog geen bestand gekozen');
                if (fileInput.files && fileInput.files.length === 1) {
                    fileLabel = fileInput.files[0].name;
                }
                else if (fileInput.files && fileInput.files.length > 1) {
                    fileLabel = fileInput.files.length + ' ' + getString('multipleFilesSelected', 'bestanden geselecteerd');
                }
                fileNameLabel.textContent = fileLabel;
                updateRunButtonState();
                if (fileInput.files && fileInput.files.length && !hasValidImportFiles()) {
                    setStatus(getString('invalidFileType', 'Kies alleen CSV-, XLSX- of ZIP-bestanden.'), true);
                } else {
                    setStatus('', false);
                }
            });
        }
        importOptionInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                updateRunButtonState();
                setStatus('', false);
            });
        });
        runButton.addEventListener('click', async function () {
            var _a;
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                setStatus(getString('needsFile', 'Kies eerst een CSV-, XLSX- of ZIP-bestand.'), true);
                updateRunButtonState();
                return;
            }
            if (!hasValidImportFiles()) {
                setStatus(getString('invalidFileType', 'Kies alleen CSV-, XLSX- of ZIP-bestanden.'), true);
                updateRunButtonState();
                return;
            }
            if (!window.confirm(getString('confirmRunImport', 'Weet je zeker dat je deze import wilt uitvoeren? Download eerst het rollbackbestand als je wijzigingen snel wilt kunnen terugzetten.'))) {
                return;
            }
            runButton.disabled = true;
            setStatus(getString('preparing', 'Import wordt voorbereid…'));
            showProgressModal('preparing', 'preparing');
            updateProgressModal(0, getString('preparing', 'Import wordt voorbereid…'));
            try {
                const prepared = await postForm('wa_acf_ptm_prepare_import');
                if (!prepared || !prepared.success || !prepared.data || !prepared.data.token || prepared.data.can_run === false) {
                    throw new Error(((_a = prepared === null || prepared === void 0 ? void 0 : prepared.data) === null || _a === void 0 ? void 0 : _a.message) || getString('importFailed', 'Import mislukt.'));
                }
                const currentToken = prepared.data.token || '';
                setProgressWarnings((prepared.data.summary && prepared.data.summary.warnings) || []);
                setStatus(getString('processing', 'Import wordt uitgevoerd…'));
                updateProgressModal(0, getString('processing', 'Import wordt uitgevoerd…'));
                let done = false;
                let iterations = 0;
                while (!done) {
                    iterations++;
                    if (iterations > 5000) {
                        throw new Error(getString('importFailed', 'Import mislukt.'));
                    }
                    const body = new FormData();
                    body.append('action', 'wa_acf_ptm_process_import');
                    body.append('nonce', config.nonce || '');
                    body.append('token', currentToken);
                    const response = await fetchWithTimeout(config.ajaxUrl || '', {
                        method: 'POST',
                        body,
                        credentials: 'same-origin'
                    });
                    const result = await app.parseJsonResponse(response, getString('unexpectedImportResponse', 'Onverwacht serverantwoord tijdens import.'));
                    if (!result || !result.success || !result.data) {
                        throw new Error(((_a = result === null || result === void 0 ? void 0 : result.data) === null || _a === void 0 ? void 0 : _a.message) || getString('importFailed', 'Import mislukt.'));
                    }
                    done = Boolean(result.data.done);
                    updateProgressModal(result.data.percent || 0, getString('progressLabel', 'Importvoortgang') + ': ' + (result.data.percent || 0) + '%', Number(result.data.processed || 0), Number(result.data.total || 0));
                    if (!done) {
                        setStatus(getString('progressLabel', 'Importvoortgang') + ': ' + (result.data.percent || 0) + '%');
                    }
                    if (done) {
                        updateProgressModal(100, result.data.notice || getString('importDone', 'Import afgerond.'), Number(result.data.total || 0), Number(result.data.total || 0));
                        setProgressCompleteState(true);
                        setProgressWarnings(result.data.warnings || []);
                        setProgressClosable(true);
                        setStatus(result.data.notice || getString('importDone', 'Import afgerond.'));
                        runButton.disabled = true;
                        updateRunButtonState();
                        if (result.data.redirect_url) {
                            window.setTimeout(function () {
                                safeRedirect(result.data.redirect_url || '');
                            }, 1200);
                        }
                    }
                }
            }
            catch (error) {
                const message = error instanceof Error ? error.message : getString('importFailed', 'Import mislukt.');
                setProgressWarnings([message]);
                setProgressClosable(true);
                setStatus(message, true);
                runButton.disabled = false;
                updateRunButtonState();
            }
        });
    });
})(window, document);
