(function (window, document) {
    'use strict';
    const app = window.WaAcfPtmAdmin || {};
    function getString(key, fallback) {
        return app.getString ? app.getString(key, fallback) : fallback;
    }
    function getTypeLabels() {
        const config = app.getConfig ? app.getConfig() : {};
        return config.strings && config.strings.typeLabels ? config.strings.typeLabels : {};
    }
    function initTabs() {
        const tabButtons = Array.from(document.querySelectorAll('.wa-acf-ptm-tab'));
        const tabPanels = Array.from(document.querySelectorAll('.wa-acf-ptm-tab-panel'));
        const fieldsCard = document.querySelector('.wa-acf-ptm-fields-card');
        if (!tabButtons.length || !tabPanels.length) {
            return;
        }
        function syncFieldsCard(name) {
            if (!fieldsCard) { return; }
            if (name === 'selected') {
                fieldsCard.removeAttribute('hidden');
            } else {
                fieldsCard.setAttribute('hidden', 'hidden');
            }
        }
        function activateTab(name, focusButton) {
            if (!name) { return; }
            tabButtons.forEach(function (button) {
                const active = button.getAttribute('data-tab') === name;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.setAttribute('tabindex', active ? '0' : '-1');
                if (active && focusButton) { button.focus(); }
            });
            tabPanels.forEach(function (panel) {
                const active = panel.getAttribute('data-panel') === name;
                panel.classList.toggle('is-active', active);
                if (active) { panel.removeAttribute('hidden'); } else { panel.setAttribute('hidden', 'hidden'); }
            });
            syncFieldsCard(name);
        }
        const currentButton = tabButtons.find(function (button) { return button.classList.contains('is-active'); });
        syncFieldsCard(currentButton ? currentButton.getAttribute('data-tab') : 'selected');
        tabButtons.forEach(function (button, index) {
            button.addEventListener('click', function () { activateTab(button.getAttribute('data-tab'), false); });
            button.addEventListener('keydown', function (event) {
                let nextIndex = index;
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') { nextIndex = (index + 1) % tabButtons.length; }
                else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') { nextIndex = (index - 1 + tabButtons.length) % tabButtons.length; }
                else if (event.key === 'Home') { nextIndex = 0; }
                else if (event.key === 'End') { nextIndex = tabButtons.length - 1; }
                else { return; }
                event.preventDefault();
                activateTab(tabButtons[nextIndex].getAttribute('data-tab'), true);
            });
        });
    }
    function initPicker(config) {
        const typeSelect = document.getElementById(config.typeSelectId);
        const searchInput = document.getElementById(config.searchInputId);
        const itemSelect = document.getElementById(config.itemSelectId);
        const countLabel = config.countLabelId ? document.getElementById(config.countLabelId) : null;
        if (!(typeSelect instanceof HTMLSelectElement) || !(itemSelect instanceof HTMLSelectElement)) {
            return;
        }
        function inferContentScope(optionValue, rawScope) {
            const explicitScope = String(rawScope || '').trim();
            if (explicitScope) {
                return explicitScope;
            }
            const value = String(optionValue || '');
            if (value.indexOf('page:') === 0) {
                return 'page';
            }
            return 'post';
        }
        function getTypeLabel(scope) {
            const labels = getTypeLabels();
            return labels[scope] ? String(labels[scope]) : '';
        }
        function isAllowedScope(scope) {
            return ['post', 'page'].indexOf(String(scope || '')) !== -1;
        }
        const allOptions = Array.from(itemSelect.options).map(function (option) {
            const contentScope = inferContentScope(option.value, option.getAttribute('data-content-scope'));
            return {
                value: option.value,
                text: option.text,
                selected: option.selected,
                contentScope: contentScope
            };
        }).filter(function (option) {
            return !!option.value && isAllowedScope(option.contentScope);
        });
        function updateCount(total) {
            if (!countLabel) { return; }
            countLabel.textContent = total === 1
                ? getString('itemsFoundSingular', '1 pagina/bericht gevonden')
                : getString('itemsFoundPlural', '%d pagina’s/berichten gevonden').replace('%d', String(total));
        }
        function renderOptions() {
            const activeType = String(typeSelect.value || '');
            const query = searchInput instanceof HTMLInputElement ? String(searchInput.value || '').toLowerCase().trim() : '';
            const currentValue = String(itemSelect.value || '');
            const filtered = allOptions.filter(function (option) {
                if (activeType && option.contentScope !== activeType) {
                    return false;
                }
                if (query && option.text.toLowerCase().indexOf(query) === -1) {
                    return false;
                }
                return true;
            });
            itemSelect.innerHTML = '';
            filtered.forEach(function (optionData, index) {
                const option = document.createElement('option');
                option.value = optionData.value;
                option.textContent = optionData.text;
                option.setAttribute('data-content-scope', optionData.contentScope);
                option.selected = currentValue
                    ? optionData.value === currentValue
                    : (index === 0 || optionData.selected);
                itemSelect.appendChild(option);
            });
            if (!itemSelect.value && itemSelect.options.length) {
                itemSelect.selectedIndex = 0;
            }
            updateCount(filtered.length);
        }
        function ensureValidType() {
            const availableTypes = Array.from(new Set(allOptions.map(function (option) {
                return String(option.contentScope || '').trim();
            }).filter(function (scope) { return !!scope && isAllowedScope(scope); })));
            if (!typeSelect.options.length || Array.from(typeSelect.options).every(function (option) { return !String(option.textContent || '').trim(); })) {
                typeSelect.innerHTML = '';
                availableTypes.forEach(function (scope) {
                    const option = document.createElement('option');
                    option.value = scope;
                    option.textContent = getTypeLabel(scope);
                    typeSelect.appendChild(option);
                });
            }
            Array.from(typeSelect.options).forEach(function (option) {
                option.hidden = availableTypes.indexOf(option.value) === -1 || !isAllowedScope(option.value);
            });
            if (availableTypes.indexOf(typeSelect.value) === -1) {
                const firstVisible = Array.from(typeSelect.options).find(function (option) { return !option.hidden; });
                if (firstVisible) {
                    typeSelect.value = firstVisible.value;
                }
            }
        }
        ensureValidType();
        renderOptions();
        typeSelect.addEventListener('change', renderOptions);
        if (searchInput instanceof HTMLInputElement) {
            searchInput.addEventListener('input', renderOptions);
        }
    }
    function initExportChecklist() {
        const form = document.getElementById('wa-acf-ptm-export-form');
        const typeSelect = document.getElementById('wa-acf-ptm-export-target-type');
        const searchInput = document.getElementById('wa-acf-ptm-export-target-search');
        const list = document.getElementById('wa-acf-ptm-export-target-list');
        const status = document.getElementById('wa-acf-ptm-export-selection-status');
        const count = document.getElementById('wa-acf-ptm-export-target-count');
        const chips = document.getElementById('wa-acf-ptm-export-selected-chips');
        const submit = form ? form.querySelector('.wa-acf-ptm-export-submit') : null;
        if (!(form instanceof HTMLFormElement) || !(typeSelect instanceof HTMLSelectElement) || !(list instanceof HTMLElement)) {
            return;
        }
        const items = Array.from(list.querySelectorAll('.wa-acf-ptm-export-checklist-item')).filter(function (item) {
            return item instanceof HTMLElement;
        });
        function checkboxFor(item) {
            const input = item.querySelector('input[type="checkbox"]');
            return input instanceof HTMLInputElement ? input : null;
        }
        function itemTitle(item) {
            const title = item.querySelector('.wa-acf-ptm-export-check-title');
            return title ? String(title.textContent || '').trim() : String(item.textContent || '').trim();
        }
        function visibleItems() {
            return items.filter(function (item) { return !item.hidden; });
        }
        function selectedItems() {
            return items.filter(function (item) {
                const input = checkboxFor(item);
                return !!input && input.checked;
            });
        }
        function update() {
            const activeType = String(typeSelect.value || '');
            const query = searchInput instanceof HTMLInputElement ? String(searchInput.value || '').toLowerCase().trim() : '';
            items.forEach(function (item) {
                const scope = String(item.getAttribute('data-content-scope') || '');
                const haystack = String(item.getAttribute('data-search-text') || item.textContent || '').toLowerCase();
                const matchesType = !activeType || scope === activeType;
                const matchesSearch = !query || haystack.indexOf(query) !== -1;
                item.hidden = !(matchesType && matchesSearch);
                const input = checkboxFor(item);
                item.classList.toggle('is-selected', !!input && input.checked);
            });
            const selected = selectedItems();
            const visible = visibleItems();
            if (status) {
                status.textContent = "";
            }
            if (count) {
                count.textContent = selected.length > 0
                    ? '(' + selected.length + ' ' + getString('selectedCountLabel', 'geselecteerd') + ')'
                    : '(' + visible.length + ' ' + getString('visibleCountLabel', 'zichtbaar') + ')';
            }
            if (chips) {
                chips.innerHTML = '';
                selected.slice(0, 6).forEach(function (item) {
                    const chip = document.createElement('span');
                    chip.className = 'wa-acf-ptm-export-chip';
                    chip.textContent = itemTitle(item);
                    chips.appendChild(chip);
                });
                if (selected.length > 6) {
                    const more = document.createElement('span');
                    more.className = 'wa-acf-ptm-export-chip wa-acf-ptm-export-chip-more';
                    more.textContent = '+' + (selected.length - 6) + ' ' + getString('moreLabel', 'meer');
                    chips.appendChild(more);
                }
            }
            if (submit instanceof HTMLButtonElement) {
                submit.disabled = selected.length === 0;
            }
        }
        typeSelect.addEventListener('change', update);
        if (searchInput instanceof HTMLInputElement) {
            searchInput.addEventListener('input', update);
        }
        items.forEach(function (item) {
            const input = checkboxFor(item);
            if (input) {
                input.addEventListener('change', update);
            }
        });
        form.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) { return; }
            const button = target.closest('[data-export-target-action]');
            if (!(button instanceof HTMLButtonElement)) { return; }
            const action = String(button.getAttribute('data-export-target-action') || '');
            const scope = action === 'clear-all' ? items : visibleItems();
            scope.forEach(function (item) {
                const input = checkboxFor(item);
                if (!input) { return; }
                if (action === 'select-visible') {
                    input.checked = true;
                } else if (action === 'clear-all') {
                    input.checked = false;
                }
            });
            update();
        });
        form.addEventListener('submit', function (event) {
            if (selectedItems().length === 0) {
                event.preventDefault();
                if (status) {
                    status.textContent = getString('exportNoItemsSelected', 'Selecteer minstens één pagina of bericht om te exporteren.');
                }
            }
        });
        update();
    }
    if (app.ready) {
        app.ready(function () {
            initTabs();
            initPicker({
                typeSelectId: 'wa-acf-ptm-target-type',
                searchInputId: 'wa-acf-ptm-target-search',
                itemSelectId: 'wa-acf-ptm-target-select',
                countLabelId: 'wa-acf-ptm-target-count'
            });
            initExportChecklist();
        });
    }
})(window, document);
