/**
 * js/validation.js — Client-side Login Form Validation
 * Hostel Management System
 * Vanilla JS only — no libraries
 */

(function () {
    'use strict';

    const form         = document.getElementById('loginForm');
    const emailInput   = document.getElementById('email');
    const passwordInput= document.getElementById('password');
    const emailError   = document.getElementById('emailError');
    const passwordError= document.getElementById('passwordError');
    const toggleBtn    = document.getElementById('togglePassword');
    const toggleIcon   = document.getElementById('toggleIcon');

    if (!form) return; // Only run on login page

    // ── Show/Hide Password Toggle ──
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.textContent = type === 'password' ? '👁️' : '🙈';
        });
    }

    // ── Helpers ──
    function showError(input, errorEl, message) {
        errorEl.textContent = message;
        input.classList.add('is-invalid');
    }

    function clearError(input, errorEl) {
        errorEl.textContent = '';
        input.classList.remove('is-invalid');
    }

    function validateEmail(value) {
        if (!value || value.trim() === '') {
            return 'Email address is required.';
        }
        // RFC5322-inspired pattern
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        if (!emailPattern.test(value.trim())) {
            return 'Please enter a valid email address.';
        }
        return '';
    }

    function validatePassword(value) {
        if (!value || value.trim() === '') {
            return 'Password is required.';
        }
        if (value.length < 6) {
            return 'Password must be at least 6 characters.';
        }
        return '';
    }

    // ── Real-time validation (on blur) ──
    emailInput.addEventListener('blur', function () {
        const error = validateEmail(this.value);
        if (error) showError(this, emailError, error);
        else        clearError(this, emailError);
    });

    emailInput.addEventListener('input', function () {
        if (this.classList.contains('is-invalid') && !validateEmail(this.value)) {
            clearError(this, emailError);
        }
    });

    passwordInput.addEventListener('blur', function () {
        const error = validatePassword(this.value);
        if (error) showError(this, passwordError, error);
        else        clearError(this, passwordError);
    });

    passwordInput.addEventListener('input', function () {
        if (this.classList.contains('is-invalid') && !validatePassword(this.value)) {
            clearError(this, passwordError);
        }
    });

    // ── Form Submit ──
    form.addEventListener('submit', function (e) {
        let valid = true;

        const emailErr = validateEmail(emailInput.value);
        if (emailErr) {
            showError(emailInput, emailError, emailErr);
            valid = false;
        } else {
            clearError(emailInput, emailError);
        }

        const passErr = validatePassword(passwordInput.value);
        if (passErr) {
            showError(passwordInput, passwordError, passErr);
            valid = false;
        } else {
            clearError(passwordInput, passwordError);
        }

        if (!valid) {
            e.preventDefault(); // Block submission

            // Shake animation on card
            const card = document.querySelector('.login-card');
            if (card) {
                card.style.animation = 'none';
                card.offsetHeight; // reflow
                card.style.animation = 'shake 0.4s ease';
            }
        }
    });

    // ── Shake keyframe (inject once) ──
    if (!document.getElementById('shakeStyle')) {
        const style = document.createElement('style');
        style.id = 'shakeStyle';
        style.textContent = `
            @keyframes shake {
                0%,100% { transform: translateX(0); }
                20%      { transform: translateX(-8px); }
                40%      { transform: translateX(8px); }
                60%      { transform: translateX(-5px); }
                80%      { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);
    }

})();
