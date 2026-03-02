/**
 * password-reset.js
 * Lógica del formulario de recuperación de contraseña.
 */

document.addEventListener('DOMContentLoaded', function () {

    const form    = document.getElementById('formReset');
    const btn     = document.getElementById('btnReset');
    const spinner = document.getElementById('btnSpinner');
    const btnText = document.getElementById('btnText');

    if (!form) return;

    // ── Mostrar/ocultar estado de carga al enviar ────────────────────────────
    form.addEventListener('submit', function (e) {
        const usuario = document.getElementById('usuario').value.trim();
        const terms   = document.getElementById('terms');

        // Validar términos si el checkbox está presente
        if (terms && !terms.checked) {
            e.preventDefault();
            showAlert(
                'Términos requeridos',
                'Debes aceptar los Términos y Condiciones para continuar.',
                'warning'
            );
            return;
        }

        if (!usuario) return; // La validación HTML se encarga

        // Estado de carga
        btn.disabled      = true;
        spinner.classList.remove('hidden');
        btnText.textContent = 'Procesando…';
    });

    // ── Modal helper ─────────────────────────────────────────────────────────
    function showAlert(title, text, icon) {
        if (typeof CopifamModal !== 'undefined') {
            CopifamModal.fire({ title, text, icon, confirmText: 'Entendido' });
        } else {
            alert(text);
        }
    }

    // ── Mostrar errores del servidor vía modal ────────────────────────────────
    const errorMeta = document.querySelector('meta[name="form-error"]');
    if (errorMeta) {
        showAlert('Error', errorMeta.getAttribute('content'), 'error');
    }

});
