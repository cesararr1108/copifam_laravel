document.addEventListener('DOMContentLoaded', function () {

    const form    = document.getElementById('formLogin');
    const btnLogin = document.getElementById('btnLogin');

    // Enter en cualquier campo dispara el submit
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            form.submit();
        }
    });

    // Deshabilitar botón al enviar para evitar doble submit
    form.addEventListener('submit', function () {
        btnLogin.disabled = true;
        btnLogin.textContent = 'Ingresando...';
    });

});
