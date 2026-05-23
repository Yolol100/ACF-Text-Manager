(function (window, document) {
    'use strict';
    window.WaAcfPtmAdmin = window.WaAcfPtmAdmin || {};
    const app = window.WaAcfPtmAdmin;
    app.ready = function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        }
        else {
            fn();
        }
    };
    app.getConfig = function () {
        return window.waAcfPtm || {};
    };
    app.getString = function (key, fallback) {
        const config = app.getConfig ? app.getConfig() : {};
        return config.strings && config.strings[key] ? String(config.strings[key]) : fallback;
    };
    app.escapeHtml = function (value) {
        return String(value === null || typeof value === 'undefined' ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };
    app.decodeHtmlEntities = function (value) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(value === null || typeof value === 'undefined' ? '' : value);
        return textarea.value;
    };
    app.extractAjaxErrorMessage = function (rawText, fallback) {
        const text = String(rawText || '');
        const paragraphMatch = text.match(/<p>(.*?)<\/p>/i);
        const message = paragraphMatch && paragraphMatch[1]
            ? app.decodeHtmlEntities(paragraphMatch[1]).trim()
            : '';
        return message || fallback || '';
    };
    app.parseJsonResponse = async function (response, fallback) {
        const rawText = await response.text();
        try {
            return JSON.parse(rawText);
        } catch (_error) {
            throw new Error(app.extractAjaxErrorMessage(rawText, fallback));
        }
    };
    app.announce = function (message) {
        const announcer = document.getElementById('wa-acf-ptm-announcer');
        if (!announcer) {
            return;
        }
        announcer.textContent = '';
        window.setTimeout(function () {
            announcer.textContent = message || '';
        }, 20);
    };
})(window, document);
