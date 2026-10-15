// This file is part of Moodle - https://moodle.org/

/**
 * Progressive enhancement for the AI Simulation Builder.
 *
 * @module     mod_decisionforge/aibuilder
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise generation and import loading states.
 *
 * @param {Object} config
 */
export const init = config => {
    const root = document.querySelector(config.root);
    if (!root) {
        return;
    }
    const loading = root.querySelector('[data-region="ai-loading"]');
    const generationInput = root.querySelector('textarea[name="prompt"]');
    const generationForm = generationInput?.closest('form');
    if (generationForm && loading) {
        generationForm.addEventListener('submit', event => {
            if (!generationForm.checkValidity()) {
                event.preventDefault();
                generationForm.reportValidity();
                return;
            }
            loading.classList.add('is-visible');
            loading.setAttribute('aria-hidden', 'false');
            const submit = generationForm.querySelector('input[type="submit"], button[type="submit"]');
            if (submit) {
                submit.disabled = true;
            }
        });
    }
    const importForms = root.querySelectorAll('[data-region="ai-import-form"]');
    importForms.forEach(importForm => {
        importForm.addEventListener('submit', event => {
            if (!importForm.checkValidity()) {
                event.preventDefault();
                importForm.reportValidity();
                return;
            }
            const submit = importForm.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.classList.add('is-busy');
            }
        });
    });
};
