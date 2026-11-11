document.addEventListener('DOMContentLoaded', function () {

    const form       = document.getElementById('formLogin');
    const btnLogin   = document.getElementById('btnLogin');
    const inputUser  = document.getElementById('usuario');
    const inputPass  = document.getElementById('clave');
    const chkRemember = document.getElementById('remember');
    const indicador  = document.getElementById('credencialesGuardadasIndicador');

    // ─── Cargar credenciales guardadas si existen ───
    function cargarCredencialesGuardadas() {
        const usuarioGuardado = localStorage.getItem('copifam_usuario_guardado');
        const claveGuardada = localStorage.getItem('copifam_clave_guardada');
        const recordarChecked = localStorage.getItem('copifam_recordarme');

        if (usuarioGuardado) {
            inputUser.value = usuarioGuardado;
        }
        if (claveGuardada) {
            inputPass.value = claveGuardada;
        }
        if (recordarChecked === 'true') {
            chkRemember.checked = true;
        }

        // Mostrar indicador si hay credenciales guardadas
        actualizarIndicador();
    }

    // ─── Actualizar indicador de credenciales guardadas ───
    function actualizarIndicador() {
        const usuarioGuardado = localStorage.getItem('copifam_usuario_guardado');
        if (usuarioGuardado && indicador) {
            indicador.classList.remove('hidden');
        } else if (indicador) {
            indicador.classList.add('hidden');
        }
    }

    // ─── Guardar credenciales si "Recuérdame" está marcado ───
    function guardarCredenciales() {
        if (chkRemember.checked) {
            localStorage.setItem('copifam_usuario_guardado', inputUser.value);
            localStorage.setItem('copifam_clave_guardada', inputPass.value);
            localStorage.setItem('copifam_recordarme', 'true');
        } else {
            // Limpiar credenciales si se desmarca
            localStorage.removeItem('copifam_usuario_guardado');
            localStorage.removeItem('copifam_clave_guardada');
            localStorage.removeItem('copifam_recordarme');
        }
        actualizarIndicador();
    }

    // ─── Limpiar credenciales cuando se desmarca "Recuérdame" ───
    chkRemember.addEventListener('change', function () {
        if (!this.checked) {
            localStorage.removeItem('copifam_usuario_guardado');
            localStorage.removeItem('copifam_clave_guardada');
            localStorage.removeItem('copifam_recordarme');
            actualizarIndicador();
        }
    });

    // ─── Enter en cualquier campo dispara el submit ───
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            form.submit();
        }
    });

    // ─── Guardar credenciales y deshabilitar botón al enviar ───
    form.addEventListener('submit', function (e) {
        guardarCredenciales();
        btnLogin.disabled    = true;
        btnLogin.textContent = 'Ingresando...';
    });

    // ─── Cargar credenciales guardadas al abrir la página ───
    cargarCredencialesGuardadas();

});
