/**
 * Módulo 0702 - Shopping de Precios
 * Comparativa Avanzada: Colores, Promedios y Márgenes.
 * Forzamos disponibilidad en el objeto window para Alpine.js
 */
window.shoppingModule = function() {
    return {
        cargandoDt: false,
        totalRegistros: 0,
        _dtShopping: null,

        // Punto de entrada seguro para Alpine.js
        setup() {
            this.$nextTick(() => {
                setTimeout(() => this.initDataTable(), 300);
            });
        },

        initDataTable() {
            const tableEl = document.getElementById('tbl-shopping');
            if (!tableEl) return;

            if ($.fn.DataTable.isDataTable('#tbl-shopping')) {
                $('#tbl-shopping').DataTable().destroy();
            }

            this._dtShopping = $('#tbl-shopping').DataTable({
                processing: false,
                serverSide: true,
                pageLength: 25,
                ajax: {
                    url: '/mercadeo/shopping/search',
                    type: 'GET',
                    beforeSend: () => { this.cargandoDt = true; },
                    dataSrc: (json) => {
                        this.cargandoDt = false;
                        this.totalRegistros = json.recordsTotal || 0;
                        return json.data || [];
                    },
                    error: (err) => {
                        this.cargandoDt = false;
                        console.error('Error DataTable:', err);
                    }
                },
                columns: [
                    { data: 'codigo', className: 'font-medium text-gray-700' },
                    { data: 'ean', className: 'text-gray-500' },
                    { 
                        data: 'nombre_producto', 
                        className: 'font-semibold text-gray-800 uppercase text-[9px]',
                        render: (data) => `<span title="${data}">${(data || '').length > 30 ? data.substring(0, 30) + '...' : (data || '')}</span>`
                    },
                    { 
                        data: 'precio_copifam', 
                        className: 'text-right font-bold text-primary',
                        render: (data) => this.formatCurrency(Math.round(parseFloat(data) || 0))
                    },
                    { 
                        data: 'precio_rebaja', 
                        render: (data, type, row) => this.renderPrice(row, 'rebaja')
                    },
                    { 
                        data: 'precio_pasteur', 
                        render: (data, type, row) => this.renderPrice(row, 'pasteur')
                    },
                    { 
                        data: 'precio_alemana', 
                        render: (data, type, row) => this.renderPrice(row, 'alemana')
                    },
                    { 
                        data: 'precio_colsubsidio', 
                        render: (data, type, row) => this.renderPrice(row, 'colsubsidio')
                    },
                    { 
                        data: null, 
                        className: 'text-right font-bold text-gray-700 bg-gray-50/50',
                        render: (row) => {
                            const m = this.calculateMetrics(row);
                            return m ? this.formatCurrency(m.bestPrice) : '—';
                        }
                    },
                    { 
                        data: null, 
                        className: 'text-right text-gray-500 italic',
                        render: (row) => {
                            const m = this.calculateMetrics(row);
                            return m ? this.formatCurrency(m.average) : '—';
                        }
                    },
                    { 
                        data: null, 
                        className: 'text-right font-semibold',
                        render: (row) => {
                            const m = this.calculateMetrics(row);
                            if (!m || m.vsBest === null) return '—';
                            const color = m.vsBest > 0 ? 'text-red-600' : 'text-green-600';
                            return `<span class="${color}">${(m.vsBest > 0 ? '+' : '') + this.formatCurrency(m.vsBest)}</span>`;
                        }
                    },
                    { 
                        data: null, 
                        className: 'text-right font-semibold',
                        render: (row) => {
                            const m = this.calculateMetrics(row);
                            if (!m || m.vsAvg === null) return '—';
                            const color = m.vsAvg > 0 ? 'text-red-500' : 'text-green-500';
                            return `<span class="${color}">${(m.vsAvg > 0 ? '+' : '') + this.formatCurrency(m.vsAvg)}</span>`;
                        }
                    },
                    { 
                        data: null, 
                        className: 'text-center',
                        render: (row) => {
                            const m = this.calculateMetrics(row);
                            if (!m || m.margin === null) return '—';
                            const color = m.margin <= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; // Ajustado: si margen es <= 0 es positivo (Copifam más barato)
                            // Corrección: El usuario dijo "POSITIVO = MÁS CARO".
                            // Margen = (Copifam - Mejor) / Copifam.
                            // Si Copifam > Mejor -> Margen > 0 -> MÁS CARO -> ROJO.
                            const isExpensive = m.margin > 0;
                            const badgeColor = isExpensive ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
                            return `<span class="px-2 py-0.5 rounded-full font-bold text-[10px] ${badgeColor}">${m.margin.toFixed(1)}%</span>`;
                        }
                    }
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                dom: 'rt<"flex items-center justify-between px-5 py-3 border-t border-gray-50"ip>',
                order: [[2, 'asc']], 
                autoWidth: false,
            });

            const self = this;
            $('#sh-search-input').off('keyup').on('keyup', function(e) {
                if (e.key === 'Enter') self.buscar();
            });
        },

        calculateMetrics(row) {
            const competitors = [
                { val: parseFloat(row.precio_rebaja) || 0, desc: parseFloat(row.descuento_rebaja) || 0 },
                { val: parseFloat(row.precio_pasteur) || 0, desc: parseFloat(row.descuento_pasteur) || 0 },
                { val: parseFloat(row.precio_alemana) || 0, desc: parseFloat(row.descuento_alemana) || 0 },
                { val: parseFloat(row.precio_colsubsidio) || 0, desc: parseFloat(row.descuento_colsubsidio) || 0 }
            ];

            const validPrices = competitors
                .map(c => (c.desc > 0 && c.desc < c.val) ? c.desc : c.val)
                .filter(p => p > 0);

            if (validPrices.length === 0) return null;

            const bestPrice = Math.min(...validPrices);
            const average = validPrices.reduce((a, b) => a + b, 0) / validPrices.length;
            const copifam = parseFloat(row.precio_copifam) || 0;

            return {
                bestPrice,
                average,
                vsBest: copifam > 0 ? (copifam - bestPrice) : null,
                vsAvg: copifam > 0 ? (copifam - average) : null,
                margin: (copifam > 0 && bestPrice > 0) ? ((copifam - bestPrice) / copifam) * 100 : null
            };
        },

        buscar() {
            const input = document.getElementById('sh-search-input');
            const val = input ? input.value : '';
            if (this._dtShopping) {
                this._dtShopping.search(val).draw();
            }
        },

        formatCurrency(num) {
            if (num === null || num === undefined) return '—';
            const sign = num < 0 ? '-' : '';
            const absNum = Math.abs(num);
            if (absNum == 0) return '<span class="text-gray-300">$ 0</span>';
            return sign + new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0
            }).format(absNum);
        },

        renderPrice(row, farmacia) {
            const normal = parseFloat(row['precio_' + farmacia]) || 0;
            const discount = parseFloat(row['descuento_' + farmacia]) || 0;
            
            if (normal == 0) return '<div class="text-center text-gray-300">—</div>';
            
            const m = this.calculateMetrics(row);
            const currentPrice = (discount > 0 && discount < normal) ? discount : normal;
            const isBest = m && currentPrice === m.bestPrice;
            
            let bgClass = isBest ? 'bg-green-50' : '';
            let html = `<div class="flex flex-col items-center py-1 ${bgClass}">`;
            
            if (discount > 0 && discount < normal) {
                html += `<span class="line-through text-[8px] text-gray-400">${this.formatCurrency(normal)}</span>`;
                html += `<span class="font-bold ${isBest ? 'text-green-700' : 'text-green-600'}">${this.formatCurrency(discount)}</span>`;
            } else {
                html += `<span class="font-semibold ${isBest ? 'text-green-700' : 'text-gray-700'}">${this.formatCurrency(normal)}</span>`;
            }
            
            html += `</div>`;
            return html;
        }
    };
};
