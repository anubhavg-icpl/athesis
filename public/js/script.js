// Odyssey-style forum JS — sparse, no chrome noise

document.addEventListener('DOMContentLoaded', function () {
    initFormValidation();
    initAutoResize();
    initBackToTop();
    initTableRowClick();
});

function initFormValidation() {
    document.querySelectorAll('form').forEach(function (form) {
        form.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.addEventListener('blur', function () {
                validateField(field);
            });
        });

        form.addEventListener('submit', function (e) {
            var ok = true;
            form.querySelectorAll('input, textarea, select').forEach(function (field) {
                if (!validateField(field)) ok = false;
            });
            if (!ok) {
                e.preventDefault();
                return;
            }
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('is-loading');
                submitBtn.disabled = true;
                setTimeout(function () {
                    submitBtn.classList.remove('is-loading');
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    });
}

function validateField(field) {
    if (!field || field.disabled || field.type === 'hidden') return true;

    var value = (field.value || '').trim();
    var required = field.hasAttribute('required');
    var minLength = field.getAttribute('minlength');
    var maxLength = field.getAttribute('maxlength');
    var pattern = field.getAttribute('pattern');

    field.classList.remove('is-valid', 'is-invalid');

    if (required && !value) {
        field.classList.add('is-invalid');
        return false;
    }
    if (!value) return true;

    if (minLength && value.length < parseInt(minLength, 10)) {
        field.classList.add('is-invalid');
        return false;
    }
    if (maxLength && value.length > parseInt(maxLength, 10)) {
        field.classList.add('is-invalid');
        return false;
    }
    if (pattern && !new RegExp('^(?:' + pattern + ')$').test(value)) {
        field.classList.add('is-invalid');
        return false;
    }
    if (field.type === 'email') {
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            field.classList.add('is-invalid');
            return false;
        }
    }
    if (field.name === 'confirm_password') {
        var pw = document.querySelector('input[name="new_password"], input[name="password"]');
        if (pw && value !== pw.value) {
            field.classList.add('is-invalid');
            return false;
        }
    }

    field.classList.add('is-valid');
    return true;
}

function initAutoResize() {
    document.querySelectorAll('textarea').forEach(function (ta) {
        autoResize(ta);
        ta.addEventListener('input', function () {
            autoResize(ta);
        });
    });
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.max(textarea.scrollHeight, 80) + 'px';
}

function initBackToTop() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ody-top';
    btn.setAttribute('aria-label', 'Back to top');
    btn.textContent = '↑';
    document.body.appendChild(btn);

    window.addEventListener('scroll', function () {
        btn.classList.toggle('show', window.pageYOffset > 320);
    });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function initTableRowClick() {
    document.querySelectorAll('.table-hover tbody tr').forEach(function (row) {
        row.addEventListener('click', function () {
            var link = row.querySelector('a');
            if (link && !window.getSelection().toString()) {
                window.location.href = link.href;
            }
        });
    });
}

// Ctrl/Cmd + Enter submits focused textarea forms
document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        var el = document.activeElement;
        if (el && el.tagName === 'TEXTAREA') {
            var form = el.closest('form');
            if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    }
});
