/**
 * copifam-modal.js
 * Modal personalizado estilo Flowbite/Tailwind para Copifam.
 * Uso: CopifamModal.fire({ title, text, icon, confirmText, cancelText, onConfirm })
 */

window.CopifamModal = (function () {

    // ── Paleta de íconos por tipo ──────────────────────────────────────────────
    const icons = {
        error: {
            bg: '#FEE2E2',
            color: '#EF4444',
            svg: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                  </svg>`
        },
        warning: {
            bg: '#FEF3C7',
            color: '#F59E0B',
            svg: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                  </svg>`
        },
        success: {
            bg: '#D1FAE5',
            color: '#10B981',
            svg: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                  </svg>`
        },
        info: {
            bg: '#DBEAFE',
            color: '#3B82F6',
            svg: `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                  </svg>`
        },
    };

    // ── Inyectar estilos base una sola vez ─────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('copifam-modal-styles')) return;
        const style = document.createElement('style');
        style.id = 'copifam-modal-styles';
        style.textContent = `
            #copifam-modal-overlay {
                position: fixed; inset: 0; z-index: 9999;
                background: rgba(0,0,0,.45);
                display: flex; align-items: center; justify-content: center;
                padding: 1rem;
                animation: cmFadeIn .18s ease;
            }
            @keyframes cmFadeIn  { from { opacity:0 } to { opacity:1 } }
            @keyframes cmSlideUp { from { opacity:0; transform:translateY(12px) scale(.97) }
                                    to  { opacity:1; transform:translateY(0)    scale(1)   } }
            #copifam-modal-box {
                background: #fff;
                border-radius: 1rem;
                box-shadow: 0 20px 60px rgba(0,0,0,.18);
                padding: 2rem 1.75rem 1.75rem;
                width: 100%; max-width: 24rem;
                text-align: center;
                animation: cmSlideUp .22s cubic-bezier(.34,1.56,.64,1);
            }
            .cm-icon-wrap {
                width: 3.25rem; height: 3.25rem;
                border-radius: 50%;
                display: inline-flex; align-items: center; justify-content: center;
                margin-bottom: 1.1rem;
            }
            .cm-title {
                font-size: 1.05rem; font-weight: 700;
                color: #111827; margin-bottom: .45rem; line-height: 1.3;
            }
            .cm-text {
                font-size: .875rem; color: #6B7280;
                line-height: 1.55; margin-bottom: 1.6rem;
            }
            .cm-buttons {
                display: flex; gap: .75rem; justify-content: center;
            }
            .cm-btn {
                flex: 1; height: 2.5rem; border-radius: .6rem;
                font-size: .875rem; font-weight: 600;
                cursor: pointer; border: none; transition: opacity .15s, transform .1s;
                font-family: inherit;
            }
            .cm-btn:hover  { opacity: .88; }
            .cm-btn:active { transform: scale(.97); }
            .cm-btn-cancel {
                background: #F3F4F6; color: #374151;
                border: 1px solid #E5E7EB;
            }
            .cm-btn-confirm {
                color: #fff;
            }
        `;
        document.head.appendChild(style);
    }

    // ── API pública ────────────────────────────────────────────────────────────
    function fire(opts = {}) {
        injectStyles();

        const {
            title       = 'Atención',
            text        = '',
            icon        = 'info',
            confirmText = 'Aceptar',
            cancelText  = null,      // Si es null, no se muestra el botón cancelar
            confirmColor = '#ff1498',
            onConfirm   = null,
            onCancel    = null,
        } = opts;

        const iconData = icons[icon] || icons.info;

        // Botón cancelar (opcional)
        const cancelBtn = cancelText
            ? `<button id="cm-cancel-btn" class="cm-btn cm-btn-cancel">${cancelText}</button>`
            : '';

        const overlay = document.createElement('div');
        overlay.id = 'copifam-modal-overlay';
        overlay.innerHTML = `
            <div id="copifam-modal-box">
                <div class="cm-icon-wrap" style="background:${iconData.bg}; color:${iconData.color}">
                    ${iconData.svg}
                </div>
                <p class="cm-title">${title}</p>
                <p class="cm-text">${text}</p>
                <div class="cm-buttons">
                    ${cancelBtn}
                    <button id="cm-confirm-btn" class="cm-btn cm-btn-confirm"
                            style="background:${confirmColor}">${confirmText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        function close() {
            overlay.style.animation = 'cmFadeIn .15s ease reverse';
            setTimeout(() => overlay.remove(), 150);
        }

        // Cerrar al hacer clic en el overlay (fuera del box)
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { close(); if (onCancel) onCancel(); }
        });

        document.getElementById('cm-confirm-btn').addEventListener('click', function () {
            close();
            if (onConfirm) onConfirm();
        });

        if (cancelText) {
            document.getElementById('cm-cancel-btn').addEventListener('click', function () {
                close();
                if (onCancel) onCancel();
            });
        }

        // Cerrar con Escape
        function onKey(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); if (onCancel) onCancel(); }
        }
        document.addEventListener('keydown', onKey);
    }

    return { fire };

})();
