// This file is part of Moodle - https://moodle.org/

/**
 * Progressive enhancement for the immersive simulation workspace.
 *
 * @module     mod_decisionforge/simulation
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const formatTime = seconds => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remaining = seconds % 60;
    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`
        : `${minutes}:${String(remaining).padStart(2, '0')}`;
};

export const init = config => {
    const root = document.querySelector(config.root);
    if (!root) {
        return;
    }
    const evidenceCards = Array.from(root.querySelectorAll('[data-region="evidence-card"]'));
    const evidenceError = root.querySelector('[data-region="evidence-error"]');
    const evidenceProgress = root.querySelector('[data-region="evidence-progress"]');
    const requiredEvidence = evidenceCards.filter(card => card.dataset.required === '1');
    const refreshEvidenceProgress = () => {
        const reviewed = requiredEvidence.filter(card => card.querySelector('input[type="checkbox"]').checked).length;
        if (evidenceProgress && config.evidenceprogress) {
            evidenceProgress.textContent = config.evidenceprogress.replace('__REVIEWED__', String(reviewed));
        }
        if (reviewed === requiredEvidence.length && evidenceError) {
            evidenceError.classList.remove('is-visible');
        }
    };
    evidenceCards.forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        const toggle = card.querySelector('[data-region="evidence-toggle"]');
        const refreshReview = () => {
            card.classList.toggle('is-reviewed', checkbox.checked);
            card.classList.remove('needs-review');
            refreshEvidenceProgress();
        };
        checkbox.addEventListener('change', refreshReview);
        toggle.addEventListener('click', () => {
            const opening = !card.classList.contains('is-open');
            if (opening) {
                evidenceCards.forEach(otherCard => {
                    if (otherCard !== card) {
                        otherCard.classList.remove('is-open');
                        otherCard.querySelector('[data-region="evidence-toggle"]')?.setAttribute('aria-expanded', 'false');
                    }
                });
            }
            card.classList.toggle('is-open', opening);
            toggle.setAttribute('aria-expanded', String(opening));
            if (opening && !checkbox.checked) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', {bubbles: true}));
            }
        });
        refreshReview();
    });

    const list = root.querySelector('[data-region="priority-list"]');
    if (list) {
        let dragged = null;
        const syncRanks = () => {
            Array.from(list.querySelectorAll('.df-priority__item')).forEach((item, index) => {
                const select = item.querySelector('select');
                if (select) {
                    select.value = String(index + 1);
                }
            });
        };
        list.querySelectorAll('.df-priority__item').forEach(item => {
            item.addEventListener('dragstart', () => {
                dragged = item;
                item.classList.add('is-dragging');
            });
            item.addEventListener('dragend', () => {
                item.classList.remove('is-dragging');
                dragged = null;
                syncRanks();
            });
            item.addEventListener('dragover', event => {
                event.preventDefault();
                if (!dragged || dragged === item) {
                    return;
                }
                const box = item.getBoundingClientRect();
                list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? item : item.nextSibling);
            });
        });
        list.addEventListener('change', event => {
            if (!event.target.matches('select')) {
                return;
            }
            const item = event.target.closest('.df-priority__item');
            const target = Number(event.target.value) - 1;
            const items = Array.from(list.querySelectorAll('.df-priority__item'));
            if (item && items[target]) {
                list.insertBefore(item, target > items.indexOf(item) ? items[target].nextSibling : items[target]);
                syncRanks();
            }
        });
    }

    const form = root.querySelector('[data-region="simulation-form"]');
    let submissionStarted = false;
    let timeoutNavigation = null;
    const deadline = Number(root.dataset.deadline || 0);
    const clock = root.querySelector('[data-region="countdown"]');
    if (deadline > 0 && clock) {
        let expired = false;
        let timer = null;
        const tick = () => {
            const remaining = Math.max(0, deadline - Math.floor(Date.now() / 1000));
            clock.textContent = formatTime(remaining);
            clock.closest('.df-simapp__clock').classList.toggle('is-urgent', remaining <= 300);
            if (remaining === 0 && !expired) {
                expired = true;
                if (timer !== null) {
                    window.clearInterval(timer);
                }
                const button = form?.querySelector('button[type="submit"]');
                if (button && !submissionStarted) {
                    button.disabled = true;
                    button.classList.add('is-busy');
                }
                timeoutNavigation = window.setTimeout(() => {
                    if (!submissionStarted) {
                        window.location.reload();
                    }
                }, 500);
            }
        };
        tick();
        if (!expired) {
            timer = window.setInterval(tick, 1000);
        }
    }

    if (form) {
        form.addEventListener('submit', event => {
            const missingEvidence = requiredEvidence.filter(
                card => !card.querySelector('input[type="checkbox"]').checked
            );
            if (missingEvidence.length) {
                event.preventDefault();
                evidenceError?.classList.add('is-visible');
                missingEvidence.forEach(card => card.classList.add('needs-review'));
                missingEvidence[0].querySelector('[data-region="evidence-toggle"]')?.focus();
                return;
            }
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }
            if (submissionStarted) {
                event.preventDefault();
                return;
            }
            submissionStarted = true;
            if (timeoutNavigation !== null) {
                window.clearTimeout(timeoutNavigation);
            }
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.classList.add('is-busy');
            }
        });
    }
};
