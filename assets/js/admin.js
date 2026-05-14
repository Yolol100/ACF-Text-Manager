(function (window, document) {
    'use strict';
    const app = window.WaAcfPtmAdmin || {};
    if (!app.ready) {
        return;
    }
    app.ready(function () {
        const allFieldLists = Array.from(document.querySelectorAll('.wa-acf-ptm-fields-list[data-target-reference]'));
        const exportForm = document.getElementById('wa-acf-ptm-export-form');
        const selectionStatus = document.getElementById('wa-acf-ptm-export-field-selection-status');
        const fieldsToolbar = document.querySelector('.wa-acf-ptm-fields-toolbar');
        const hasFieldSelectionControls = Boolean(document.querySelector('.wa-acf-ptm-export-toggle'));
        if (!allFieldLists.length || !exportForm) {
            return;
        }
        function getString(key, fallback) {
            return app.getString ? app.getString(key, fallback) : fallback;
        }
        function escapeHtml(value) {
            return app.escapeHtml ? app.escapeHtml(value) : String(value === null || typeof value === 'undefined' ? '' : value);
        }
        function getItems() {
            return allFieldLists.reduce(function (carry, list) {
                return carry.concat(Array.from(list.querySelectorAll('.wa-acf-ptm-field-item[data-field-key]')));
            }, []);
        }
        function getSelectedItems() {
            if (!hasFieldSelectionControls) {
                return getItems();
            }

            return getItems().filter(function (item) {
                return item.getAttribute('data-export-selected') !== 'false';
            });
        }
        function setStatus(message, isError) {
            if (!selectionStatus) {
                return;
            }
            selectionStatus.textContent = message || '';
            selectionStatus.classList.toggle('is-error', Boolean(isError));
        }
        function syncHiddenInputs() {
            exportForm.querySelectorAll('input[name="selected_field_keys[]"], input[name="field_selection_mode"], input[name="field_selection_target_reference"]').forEach(function (input) { input.remove(); });

            if (!hasFieldSelectionControls) {
                return;
            }

            const modeInput = document.createElement('input');
            modeInput.type = 'hidden';
            modeInput.name = 'field_selection_mode';
            modeInput.value = 'custom';
            exportForm.appendChild(modeInput);

            const targetReferenceInput = document.createElement('input');
            targetReferenceInput.type = 'hidden';
            targetReferenceInput.name = 'field_selection_target_reference';
            targetReferenceInput.value = allFieldLists[0] ? (allFieldLists[0].getAttribute('data-target-reference') || '') : '';
            exportForm.appendChild(targetReferenceInput);
            getSelectedItems().forEach(function (item) {
                const key = item.getAttribute('data-field-key') || '';
                if (!key) return;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_field_keys[]';
                input.value = key;
                exportForm.appendChild(input);
            });
        }
        function updateItemState(item) {
            const selected = item.getAttribute('data-export-selected') !== 'false';
            const button = item.querySelector('.wa-acf-ptm-export-toggle');
            const icon = button ? button.querySelector('.wa-acf-ptm-export-toggle-icon') : null;
            const srText = button ? button.querySelector('.screen-reader-text') : null;
            const title = item.querySelector('.wa-acf-ptm-field-title');
            const label = title ? String(title.textContent || '').trim() : '';
            item.classList.toggle('is-export-excluded', !selected);
            if (button) {
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
                button.setAttribute('title', (selected ? getString('exportFieldIncluded', '%s wordt meegenomen in export.') : getString('exportFieldExcluded', '%s wordt niet meegenomen in export.')).replace('%s', label));
            }
            if (icon) {
                icon.textContent = selected ? '✓' : '×';
            }
            if (srText) {
                srText.textContent = (selected ? getString('exportFieldIncluded', '%s wordt meegenomen in export.') : getString('exportFieldExcluded', '%s wordt niet meegenomen in export.')).replace('%s', label);
            }
        }
        function updateSelectionSummary() {
            const total = getItems().length;
            const selected = getSelectedItems().length;
            if (hasFieldSelectionControls) {
                setStatus(getString('exportSelectionCount', '%1$d van %2$d velden geselecteerd voor export.').replace('%1$d', String(selected)).replace('%2$d', String(total)), false);
            } else {
                setStatus('', false);
            }
            syncHiddenInputs();
        }
        function toggleItem(item) {
            const selected = item.getAttribute('data-export-selected') !== 'false';
            item.setAttribute('data-export-selected', selected ? 'false' : 'true');
            updateItemState(item);
            updateSelectionSummary();
            const title = item.querySelector('.wa-acf-ptm-field-title');
            const label = title ? String(title.textContent || '').trim() : '';
            if (app.announce) {
                app.announce((selected ? getString('exportFieldExcluded', '%s wordt niet meegenomen in export.') : getString('exportFieldIncluded', '%s wordt meegenomen in export.')).replace('%s', label));
            }
        }
        function getActiveFilters() {
            const buttons = Array.from(document.querySelectorAll('.wa-acf-ptm-field-filter[data-field-filter]'));
            return buttons.filter(function (button) {
                return button.classList.contains('is-active') && (button.getAttribute('data-field-filter') || '') !== 'all';
            }).map(function (button) { return button.getAttribute('data-field-filter') || ''; }).filter(Boolean);
        }
        function setFilterButtonState(button, active) {
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
        function updateGroupFilter(filter) {
            const groups = Array.from(document.querySelectorAll('.wa-acf-ptm-field-group[data-field-group]'));
            const buttons = Array.from(document.querySelectorAll('.wa-acf-ptm-field-filter[data-field-filter]'));
            if (filter === 'all') {
                buttons.forEach(function (button) { setFilterButtonState(button, (button.getAttribute('data-field-filter') || '') === 'all'); });
            } else {
                const allButton = buttons.find(function (button) { return (button.getAttribute('data-field-filter') || '') === 'all'; });
                if (allButton) setFilterButtonState(allButton, false);
                buttons.forEach(function (button) {
                    const key = button.getAttribute('data-field-filter') || '';
                    if (key === 'all' || key !== filter) return;
                    setFilterButtonState(button, !button.classList.contains('is-active'));
                });
                if (!getActiveFilters().length && allButton) setFilterButtonState(allButton, true);
            }
            const activeFilters = getActiveFilters();
            groups.forEach(function (group) {
                const groupKey = group.getAttribute('data-field-group') || '';
                group.toggleAttribute('hidden', !!activeFilters.length && activeFilters.indexOf(groupKey) === -1);
            });
        }
        function setAllItems(selected) {
            getItems().forEach(function (item) {
                item.setAttribute('data-export-selected', selected ? 'true' : 'false');
                updateItemState(item);
            });
            updateSelectionSummary();
            if (app.announce) app.announce(selected ? getString('exportAllFieldsSelected', 'Alle velden worden meegenomen in export.') : getString('exportAllFieldsCleared', 'Alle velden zijn uitgezet voor export.'));
        }
        getItems().forEach(updateItemState);
        updateSelectionSummary();
        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const toggleButton = target.closest('.wa-acf-ptm-export-toggle');
            if (toggleButton) {
                event.preventDefault();
                const item = toggleButton.closest('.wa-acf-ptm-field-item[data-field-key]');
                if (item instanceof HTMLElement) toggleItem(item);
                return;
            }
            const actionButton = target.closest('[data-export-bulk-action]');
            if (actionButton && fieldsToolbar && fieldsToolbar.contains(actionButton)) {
                event.preventDefault();
                const action = actionButton.getAttribute('data-export-bulk-action');
                if (action === 'select-all') setAllItems(true);
                if (action === 'clear-all') setAllItems(false);
                return;
            }
            const filterButton = target.closest('.wa-acf-ptm-field-filter[data-field-filter]');
            if (filterButton) {
                event.preventDefault();
                const filter = filterButton.getAttribute('data-field-filter') || 'all';
                updateGroupFilter(filter);
                if (app.announce) {
                    app.announce(filter === 'all' || !getActiveFilters().length ? getString('filterAllFields', 'Alle veldgroepen worden getoond.') : getString('filterSelectionUpdated', 'Veldfilters bijgewerkt.'));
                }
            }
        });
        updateGroupFilter('all');

        const inlineEditors = Array.from(document.querySelectorAll('.wa-acf-ptm-inline-editor[data-target-reference][data-field-key]'));
        function setEditorStatus(editor, message, isError) {
            const item = editor.closest('.wa-acf-ptm-field-item');
            const status = item ? item.querySelector('.wa-acf-ptm-inline-editor-status') : null;
            if (!status) return;
            status.textContent = message || '';
            status.classList.toggle('is-error', Boolean(isError));
        }
        function isMediaFileNameEditor(editor) {
            const fieldType = String(editor.getAttribute('data-field-type') || '');
            const fieldName = String(editor.getAttribute('data-field-name') || '');
            return fieldType === 'image_meta' && fieldName.endsWith('__file_name');
        }
        function updateSaveButton(editor) {
            const item = editor.closest('.wa-acf-ptm-field-item');
            const button = item ? item.querySelector('.wa-acf-ptm-inline-save') : null;
            if (!button || !item) return;
            const hasChanged = getEditorValue(editor) !== (editor.getAttribute('data-initial-value') || '');
            button.disabled = item.classList.contains('is-saving') || editor.hidden || !hasChanged;
            button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');
        }
        function setDisplayHtml(item, html) {
            const display = item ? item.querySelector('.wa-acf-ptm-field-display') : null;
            const body = item ? item.querySelector('.wa-acf-ptm-field-body') : null;
            if (!display || !body) return;
            display.innerHTML = html;
            body.classList.toggle('is-empty', !String(display.textContent || '').trim());
        }
        function isWysiwygEditor(editor) {
            return editor.getAttribute('data-wysiwyg') === '1' && !!editor.id;
        }
        function getTinyMceInstance(editor) {
            return window.tinymce && editor.id ? window.tinymce.get(editor.id) : null;
        }
        function initWysiwygEditor(editor) {
            if (!isWysiwygEditor(editor) || editor.getAttribute('data-wysiwyg-ready') === '1') return;
            if (window.wp && window.wp.editor && typeof window.wp.editor.initialize === 'function') {
                window.wp.editor.initialize(editor.id, {
                    tinymce: {
                        wpautop: true,
                        toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo',
                        toolbar2: '',
                        height: 220
                    },
                    quicktags: true,
                    mediaButtons: false
                });
                editor.setAttribute('data-wysiwyg-ready', '1');
            }
        }
        function getEditorValue(editor) {
            const instance = getTinyMceInstance(editor);
            if (instance && !instance.isHidden()) {
                instance.save();
            }
            return editor.value;
        }
        function setEditorValue(editor, value) {
            const normalizedValue = value === null || typeof value === 'undefined' ? '' : String(value);
            editor.value = normalizedValue;
            const instance = getTinyMceInstance(editor);
            if (instance) {
                instance.setContent(normalizedValue);
                instance.save();
            }
        }
        async function saveInlineEditor(editor, options) {
            const item = editor.closest('.wa-acf-ptm-field-item');
            if (!item) return;
            const settings = options || {};
            const closeAfterSave = settings.closeAfterSave !== false;
            const closeWhenUnchanged = settings.closeWhenUnchanged !== false;
            const value = getEditorValue(editor);
            const initialValue = editor.getAttribute('data-initial-value') || '';
            const isMediaRename = isMediaFileNameEditor(editor);
            if (value === initialValue) {
                updateSaveButton(editor);
                if (closeAfterSave && closeWhenUnchanged) {
                    closeEditor(editor, false);
                }
                return;
            }
            if (isMediaRename && !settings.confirmMediaRename) {
                const confirmed = window.confirm(getString('confirmInlineMediaRename', 'Deze wijziging past de fysieke media-bestandsnaam aan en kan bestaande media-URL’s wijzigen. Doorgaan?'));
                if (!confirmed) {
                    updateSaveButton(editor);
                    return;
                }
                settings.confirmMediaRename = true;
            }
            item.classList.add('is-saving');
            updateSaveButton(editor);
            setEditorStatus(editor, getString('inlineSaving', 'Opslaan...'), false);
            const body = new FormData();
            body.append('action', 'wa_acf_ptm_save_field');
            body.append('nonce', app.getConfig().nonce || '');
            body.append('target_reference', editor.getAttribute('data-target-reference') || '');
            body.append('field_key', editor.getAttribute('data-field-key') || '');
            body.append('value', value);
            if (settings.confirmMediaRename) {
                body.append('confirm_media_rename', '1');
            }
            try {
                const response = await fetch(app.getConfig().ajaxUrl || '', { method: 'POST', body, credentials: 'same-origin' });
                const result = await app.parseJsonResponse(response, getString('inlineSaveError', 'Opslaan mislukt.'));
                if (!result || !result.success || !result.data) {
                    throw new Error((result && result.data && result.data.message) || getString('inlineSaveError', 'Opslaan mislukt.'));
                }
                const savedValue = result.data.value === null || typeof result.data.value === 'undefined' ? '' : String(result.data.value);
                editor.setAttribute('data-initial-value', savedValue);
                setEditorValue(editor, savedValue);
                setDisplayHtml(item, result.data.display_html || ('<span class="wa-acf-ptm-empty">' + escapeHtml(getString('inlineEmptyPlaceholder', 'Leeg')) + '</span>'));
                setEditorStatus(editor, result.data.message || getString('inlineSaved', 'Opgeslagen.'), false);
                if (closeAfterSave) {
                    closeEditor(editor, false);
                }
            } catch (error) {
                setEditorStatus(editor, error instanceof Error ? error.message : getString('inlineSaveError', 'Opslaan mislukt.'), true);
            } finally {
                item.classList.remove('is-saving');
                updateSaveButton(editor);
            }
        }
        function closeEditor(editor, restoreInitialValue) {
            const item = editor.closest('.wa-acf-ptm-field-item');
            const body = item ? item.querySelector('.wa-acf-ptm-field-body') : null;
            if (restoreInitialValue) {
                setEditorValue(editor, editor.getAttribute('data-initial-value') || '');
            }
            if (item) {
                item.classList.remove('is-editing');
            }
            editor.hidden = true;
            updateSaveButton(editor);
            setEditorStatus(editor, '', false);
            if (body instanceof HTMLElement) {
                body.focus();
            }
        }
        inlineEditors.forEach(function (editor) {
            editor.setAttribute('data-initial-value', editor.value || '');
            const item = editor.closest('.wa-acf-ptm-field-item');
            const body = item ? item.querySelector('.wa-acf-ptm-field-body') : null;
            const editButton = item ? item.querySelector('.wa-acf-ptm-inline-edit') : null;
            const saveButton = item ? item.querySelector('.wa-acf-ptm-inline-save') : null;
            if (!item || !body) return;
            const openEditor = function () {
                if (item.classList.contains('is-saving')) return;
                item.classList.add('is-editing');
                editor.hidden = false;
                initWysiwygEditor(editor);
                updateSaveButton(editor);
                window.setTimeout(function () {
                    const instance = getTinyMceInstance(editor);
                    if (instance && !instance.isHidden()) { instance.focus(); } else { editor.focus(); editor.select(); }
                }, 80);
            };
            body.addEventListener('click', function (event) {
                const target = event.target;
                if (target instanceof Element && target.closest('.wa-acf-ptm-export-toggle, .wa-acf-ptm-inline-save')) return;
                openEditor();
            });
            editor.addEventListener('input', function () {
                updateSaveButton(editor);
                setEditorStatus(editor, '', false);
            });
            editor.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    event.preventDefault();
                    saveInlineEditor(editor, { closeAfterSave: true, closeWhenUnchanged: true });
                }
                if (event.key === 'Escape') {
                    closeEditor(editor, true);
                }
            });
            if (editButton) {
                editButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    openEditor();
                });
            }
            if (saveButton) {
                saveButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (editor.hidden) {
                        openEditor();
                        return;
                    }
                    if (saveButton.disabled) return;
                    saveInlineEditor(editor, { closeAfterSave: true, closeWhenUnchanged: true });
                });
            }
            updateSaveButton(editor);
        });
        document.addEventListener('mousedown', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            inlineEditors.forEach(function (editor) {
                const item = editor.closest('.wa-acf-ptm-field-item');
                if (!item || !item.classList.contains('is-editing') || item.classList.contains('is-saving')) return;
                if (target.closest('.wa-acf-ptm-field-item') === item) return;
                if (getEditorValue(editor) !== (editor.getAttribute('data-initial-value') || '')) {
                    if (isMediaFileNameEditor(editor)) {
                        setEditorStatus(editor, getString('mediaRenameNeedsSave', 'Gebruik Opslaan om deze media-bestandsnaamwijziging expliciet te bevestigen.'), true);
                        updateSaveButton(editor);
                        return;
                    }
                    saveInlineEditor(editor, { closeAfterSave: true, closeWhenUnchanged: true });
                } else {
                    closeEditor(editor, false);
                }
            });
        });
        if (exportForm) {
            exportForm.addEventListener('submit', function (event) {
                syncHiddenInputs();

                if (!exportForm.querySelector('input[name="target_references[]"]:checked')) {
                    event.preventDefault();
                    const message = getString('exportNoItemsSelected', 'Selecteer minstens één item om te exporteren.');
                    setStatus(message, true);
                    if (app.announce) app.announce(message);
                    return;
                }

                if (hasFieldSelectionControls && !getSelectedItems().length) {
                    event.preventDefault();
                    const message = getString('exportNoFieldsSelected', 'Selecteer minstens één veld voor export.');
                    setStatus(message, true);
                    if (app.announce) app.announce(message);
                    return;
                }

                const submitButton = exportForm.querySelector('.wa-acf-ptm-export-submit');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = getString('exportPreparing', 'Export wordt voorbereid…');
                    window.setTimeout(function () {
                        submitButton.disabled = false;
                        submitButton.textContent = submitButton.getAttribute('data-default-label') || getString('exportDownload', 'Export downloaden');
                        setStatus(getString('exportStarted', 'Download gestart. Controleer je downloads als er geen nieuw venster verschijnt.'), false);
                    }, 2500);
                }
                setStatus(getString('exportPreparing', 'Export wordt voorbereid…'), false);
                if (app.announce) app.announce(getString('exportPreparing', 'Export wordt voorbereid…'));
            });
        }
    });
})(window, document);
