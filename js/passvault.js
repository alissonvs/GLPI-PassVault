/**
 * Pass Vault — front-end helpers.
 *
 * Provides password visibility toggle, copy-to-clipboard and a small
 * client-side random password generator. Loaded on every GLPI page; the
 * code below only binds to elements that actually exist.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function findField() {
        return document.getElementById('passvault_password_field');
    }

    function bindToggle() {
        var btn = document.getElementById('passvault_password_toggle');
        var field = findField();
        if (!btn || !field) {
            return;
        }
        btn.addEventListener('click', function () {
            if (field.type === 'password') {
                field.type = 'text';
                btn.innerHTML = '<i class="ti ti-eye-off"></i>';
            } else {
                field.type = 'password';
                btn.innerHTML = '<i class="ti ti-eye"></i>';
            }
        });
    }

    function bindCopy() {
        var btn = document.getElementById('passvault_password_copy');
        var field = findField();
        if (!btn || !field) {
            return;
        }
        btn.addEventListener('click', function () {
            var value = field.value;
            if (!value) {
                return;
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(function () {
                    flash(btn, 'check');
                }).catch(function () {
                    fallbackCopy(field, btn);
                });
            } else {
                fallbackCopy(field, btn);
            }
        });
    }

    function fallbackCopy(field, btn) {
        try {
            field.select();
            field.setSelectionRange(0, field.value.length);
            var ok = document.execCommand('copy');
            if (ok) {
                flash(btn, 'check');
            }
        } catch (e) {
            // Give up silently; the field remains selected so the user can
            // press Ctrl+C manually.
        }
    }

    function flash(btn, icon) {
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-' + icon + '"></i>';
        window.setTimeout(function () {
            btn.innerHTML = original;
        }, 1200);
    }

    function bindRandom() {
        var btn = document.getElementById('passvault_password_random');
        var field = findField();
        if (!btn || !field) {
            return;
        }
        btn.addEventListener('click', function () {
            field.value = generatePassword(20);
            field.type = 'text';
            var toggle = document.getElementById('passvault_password_toggle');
            if (toggle) {
                toggle.innerHTML = '<i class="ti ti-eye-off"></i>';
            }
            field.focus();
        });
    }

    function generatePassword(length) {
        var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        var lower = 'abcdefghijkmnopqrstuvwxyz';
        var digits = '23456789';
        var symbols = '!@#$%&*()-_=+[]{}';
        var pool = upper + lower + digits + symbols;
        var arr = new Uint32Array(length);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(arr);
        } else {
            for (var i = 0; i < length; i++) {
                arr[i] = Math.floor(Math.random() * 4294967296);
            }
        }
        var out = '';
        for (var j = 0; j < length; j++) {
            out += pool.charAt(arr[j] % pool.length);
        }
        return out;
    }

    ready(function () {
        bindToggle();
        bindCopy();
        bindRandom();
    });
})();
