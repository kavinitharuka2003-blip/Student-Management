/**
 * ==============================================================================
 * Client-Side Form Validation & UX Layer (assets/js/validation.js)
 * ------------------------------------------------------------------------------
 * Enhances user experience with instant real-time field validation, date format
 * checks, and visual feedback before form submission.
 * Note: Server-side validation in PHP remains the authoritative security boundary.
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    // Select all forms requiring validation
    const forms = document.querySelectorAll('form:not([novalidate="false"])');

    forms.forEach(form => {
        // Real-time input listener for instant feedback
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                validateField(input);
            });
            input.addEventListener('blur', () => {
                validateField(input);
            });
        });

        // Form submission listener
        form.addEventListener('submit', event => {
            let isFormValid = true;

            inputs.forEach(input => {
                if (!validateField(input)) {
                    isFormValid = false;
                }
            });

            if (!isFormValid) {
                event.preventDefault();
                event.stopPropagation();
                
                // Focus first invalid input
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });

    /**
     * Validates a single input element and applies Bootstrap is-valid / is-invalid classes.
     *
     * @param {HTMLElement} field - The form element to validate.
     * @return {boolean} - Whether the field passed validation.
     */
    function validateField(field) {
        // Skip hidden inputs or buttons
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
            return true;
        }

        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // Check 1: Required
        if (field.hasAttribute('required') && value === '') {
            isValid = false;
            errorMessage = 'This field is required.';
        }

        // Check 2: Email format
        if (isValid && field.type === 'email' && value !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address.';
            }
        }

        // Check 3: Number boundaries (min / max)
        if (isValid && field.type === 'number' && value !== '') {
            const num = parseFloat(value);
            const min = field.hasAttribute('min') ? parseFloat(field.getAttribute('min')) : null;
            const max = field.hasAttribute('max') ? parseFloat(field.getAttribute('max')) : null;

            if (min !== null && num < min) {
                isValid = false;
                errorMessage = `Value must be at least ${min}.`;
            } else if (max !== null && num > max) {
                isValid = false;
                errorMessage = `Value cannot exceed ${max}.`;
            }
        }

        // Check 4: Date sanity
        if (isValid && field.type === 'date' && value !== '') {
            const dateVal = new Date(value);
            if (isNaN(dateVal.getTime())) {
                isValid = false;
                errorMessage = 'Please enter a valid calendar date.';
            }
        }

        // Update DOM classes
        if (!isValid) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');

            // Find or create invalid-feedback element
            let feedback = field.parentNode.querySelector('.invalid-feedback');
            if (!feedback && field.closest('.input-group')) {
                feedback = field.closest('.input-group').parentNode.querySelector('.invalid-feedback');
            }
            if (feedback) {
                feedback.textContent = errorMessage;
                feedback.style.display = 'block';
            }
        } else {
            field.classList.remove('is-invalid');
            
            // Clean up custom feedback display
            let feedback = field.parentNode.querySelector('.invalid-feedback');
            if (!feedback && field.closest('.input-group')) {
                feedback = field.closest('.input-group').parentNode.querySelector('.invalid-feedback');
            }
            if (feedback && !field.classList.contains('server-error')) {
                feedback.style.display = 'none';
            }
        }

        return isValid;
    }
});
