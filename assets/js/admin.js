(function (window, document) {
    'use strict';
    const __waEditors = new Map();
    const __waSaveControllers = new Map();
    function debounce(fn, wait){let t;return function(){clearTimeout(t);const a=arguments;t=setTimeout(()=>fn.apply(this,a),wait);};}
    const app = window.WaAcfPtmAdmin || {};
    if (!app.ready) {
        return;
    }
    app.ready(function () {
        const allFieldLists = Array.from(document.querySelectorAll('.wa-acf-ptm-fields-list[data-target-reference]'));
        const exportForm = document.getElementById('wa-acf-ptm-export-form');
        const selectionStatus = document.getElementById('wa-acf-ptm-export-field-selection-status');
        if (!allFieldLists.length || !exportForm) {
            return;
        }
        function getString(key, fallback) {
            return app.getString ? app.getString(key, fallback) : fallback;
        }
        function escapeHtml(value) {
            return app.escapeHtml ? app.escapeHtml(value) : String(value === null || typeof value === 'undefined' ? '' : value);
        }
        function setStatus(message, isError) {
            if (!selectionStatus) {
                return;
            }
            selectionStatus.textContent = message || '';
            selectionStatus.classList.toggle('is-error', Boolean(isError));
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
        document.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
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
            if (!isWysiwygEditor(editor)) return;
            if (window.tinymce && editor.id && window.tinymce.get(editor.id)) { try { window.tinymce.get(editor.id).remove(); } catch (e) {} }
            editor.removeAttribute('data-wysiwyg-ready');
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
                __waEditors.set(editor.id, true);
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
        const debouncedUpdateButton = debounce(updateSaveButton, 150);

        async function saveInlineEditor(editor, options) {
            const item = editor.closest('.wa-acf-ptm-field-item');
            if (!item) return;
            const settings = options || {};
            if (window.tinymce) { window.tinymce.triggerSave(); }
            const existingController = __waSaveControllers.get(editor.id);
            if (existingController) { existingController.abort(); }
            const controller = new AbortController();
            __waSaveControllers.set(editor.id, controller);
            const closeAfterSave = settings.closeAfterSave !== false;
            const closeWhenUnchanged = settings.closeWhenUnchanged !== false;
            const value = getEditorValue(editor);
            const initialValue = editor.getAttribute('data-initial-value') || '';
            const isMediaRename = isMediaFileNameEditor(editor);
            if (value === initialValue) {
                debouncedUpdateButton(editor);
                if (closeAfterSave && closeWhenUnchanged) {
                    closeEditor(editor, false);
                }
                return;
            }
            if (isMediaRename && !settings.confirmMediaRename) {
                const confirmed = window.confirm(getString('confirmInlineMediaRename', 'Deze wijziging past de fysieke media-bestandsnaam aan en kan bestaande media-URL’s wijzigen. Doorgaan?'));
                if (!confirmed) {
                    debouncedUpdateButton(editor);
                    return;
                }
                settings.confirmMediaRename = true;
            }
            item.classList.add('is-saving');
            debouncedUpdateButton(editor);
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
                debouncedUpdateButton(editor);
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
            debouncedUpdateButton(editor);
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
                debouncedUpdateButton(editor);
                window.setTimeout(function () {
                    const instance = getTinyMceInstance(editor);
                    if (instance && !instance.isHidden()) { instance.focus(); } else { editor.focus(); editor.select(); }
                }, 80);
            };
            body.addEventListener('click', function (event) {
                const target = event.target;
                if (target instanceof Element && target.closest('.wa-acf-ptm-inline-save')) return;
                openEditor();
            });
            editor.addEventListener('input', function () {
                debouncedUpdateButton(editor);
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
            debouncedUpdateButton(editor);
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
                        debouncedUpdateButton(editor);
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
                if (event.defaultPrevented) {
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

window.addEventListener('beforeunload', function(){ if(window.tinymce){ Object.keys(window.tinymce.editors||{}).forEach(function(k){ try{window.tinymce.editors[k].save();}catch(e){} }); }});
