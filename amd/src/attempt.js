// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Progressive enhancement for the learner attempt screen.
 *
 * @module     mod_decisionforge/attempt
 * @copyright  2026 FoneRep Technologies
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Format seconds as H:MM:SS or M:SS.
 *
 * @param {number} seconds
 * @returns {string}
 */
const formatTime = (seconds) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remaining = seconds % 60;
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`;
    }
    return `${minutes}:${String(remaining).padStart(2, '0')}`;
};

/**
 * Initialise attempt interactions.
 *
 * @param {Object} config
 */
export const init = (config) => {
    const form = document.getElementById(config.formid);
    if (!form) {
        return;
    }

    const optionError = form.querySelector('[data-region="option-error"]');
    const optionInputs = Array.from(form.querySelectorAll('input[name="optionid"]'));
    optionInputs.forEach((input) => {
        input.addEventListener('change', () => {
            if (optionError) {
                optionError.classList.remove('is-visible');
            }
        });
    });

    const confidence = form.querySelector('input[name="confidence"][type="range"]');
    const confidenceValue = form.querySelector('[data-region="confidence-value"]');
    if (confidence && confidenceValue) {
        const updateConfidence = () => {
            confidenceValue.value = `${confidence.value}%`;
            confidenceValue.textContent = `${confidence.value}%`;
        };
        confidence.addEventListener('input', updateConfidence);
        updateConfidence();
    }

    const reasoning = form.querySelector('textarea[name="justification"]');
    if (reasoning) {
        const counter = reasoning.parentElement.querySelector('small');
        const updateCounter = () => {
            if (counter) {
                counter.textContent = `${reasoning.value.length} / 2000`;
            }
        };
        reasoning.addEventListener('input', updateCounter);
        updateCounter();
    }

    form.addEventListener('submit', (event) => {
        if (!optionInputs.some((input) => input.checked)) {
            event.preventDefault();
            if (optionError) {
                optionError.classList.add('is-visible');
            }
            optionInputs[0]?.focus();
            return;
        }
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        const submit = form.querySelector('[data-region="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.classList.add('is-busy');
        }
    });

    let timeLeft = Number(config.timeleft || 0);
    const timer = document.querySelector('[data-region="timer"]');
    const timerValue = document.querySelector('[data-region="timer-value"]');
    if (timeLeft > 0 && timer && timerValue) {
        window.setInterval(() => {
            timeLeft -= 1;
            timerValue.textContent = formatTime(Math.max(0, timeLeft));
            if (timeLeft <= 300) {
                timer.classList.add('decisionforge-timer--urgent');
            }
            if (timeLeft <= 0) {
                window.location.assign(config.expiredurl);
            }
        }, 1000);
    }
};

