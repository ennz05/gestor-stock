// Funciones globales de la aplicación

document.addEventListener('DOMContentLoaded', function() {
    // Manejo del escáner de código de barras
    const barcodeInputs = document.querySelectorAll('.barcode-input');
    
    barcodeInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                const codigo = this.value.trim();
                if(codigo) {
                    buscarPorCodigoBarras(codigo);
                    this.value = '';
                }
            }
        });
    });
    
    // Auto-focus en campos de búsqueda
    const searchInput = document.querySelector('input[type="search"], .search-input');
    if (searchInput) {
        searchInput.focus();
    }
    
    // Confirmar eliminaciones
    const deleteButtons = document.querySelectorAll('a[href*="eliminar"], button[onclick*="confirm"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de realizar esta acción?')) {
                e.preventDefault();
            }
        });
    });
    
    // Validar formularios numéricos
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    });
});

function buscarPorCodigoBarras(codigo) {
    // Intentar buscar en la página actual primero
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('productos')) {
        window.location.href = `listar.php?buscar=${codigo}`;
    } else if (currentPage.includes('inventario')) {
        // Para páginas de inventario, simular el submit del formulario
        const form = document.querySelector('form');
        if (form && form.querySelector('input[name="codigo_barras"]')) {
            form.querySelector('input[name="codigo_barras"]').value = codigo;
            form.submit();
        } else {
            window.location.href = `../productos/listar.php?buscar=${codigo}`;
        }
    } else {
        // Por defecto, buscar en productos
        window.location.href = `modules/productos/listar.php?buscar=${codigo}`;
    }
}

function confirmarEliminacion() {
    return confirm('¿Está seguro de que desea eliminar este registro?');
}

function confirmarMovimiento() {
    return confirm('¿Confirmar este movimiento de inventario?');
}

function calcularTotal() {
    const cantidad = parseFloat(document.getElementById('cantidad').value) || 0;
    const precio = parseFloat(document.getElementById('precio').value) || 0;
    const total = cantidad * precio;
    
    const totalElement = document.getElementById('total');
    if (totalElement) {
        totalElement.textContent = '$' + total.toFixed(2);
    }
    
    return total;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('es-UY', {
        style: 'currency',
        currency: 'UYU'
    }).format(value);
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Estilos para la notificación
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-width: 300px;
        max-width: 400px;
    `;
    
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)';
    } else if (type === 'error') {
        notification.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
    } else if (type === 'warning') {
        notification.style.background = 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)';
    } else {
        notification.style.background = 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)';
    }
    
    notification.querySelector('button').style.cssText = `
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        margin-left: 15px;
    `;
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Añadir estilos CSS para la animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Función para exportar tabla a CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        for (let cell of cells) {
            // Omitir columnas de acciones
            if (!cell.classList.contains('actions')) {
                let data = cell.innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                data = data.replace(/"/g, '""');
                rowData.push('"' + data + '"');
            }
        }
        
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Función para imprimir reportes
function printReport(elementId) {
    const printContent = document.getElementById(elementId);
    if (!printContent) return;
    
    const originalContents = document.body.innerHTML;
    document.body.innerHTML = printContent.innerHTML;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

// Manejo de fechas en formularios
function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

// Inicializar funciones al cargar
window.onload = function() {
    setDefaultDates();
    
    // Configurar tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.title;
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 0.85rem;
                z-index: 10000;
                white-space: nowrap;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
            tooltip.style.left = (rect.left + (rect.width - tooltip.offsetWidth) / 2) + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
            }
        });
    });
};