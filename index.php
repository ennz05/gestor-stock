<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar estado de caja
$caja_abierta = $functions->isCajaAbierta();
$_SESSION['caja_abierta'] = $caja_abierta;

// Manejar mensajes
$mensaje = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'caja_abierta':
            $mensaje = 'Caja abierta correctamente';
            break;
        case 'caja_cerrada':
            $diferencia = isset($_GET['diferencia']) ? $_GET['diferencia'] : 0;
            $mensaje = 'Caja cerrada. Diferencia: $' . number_format($diferencia, 2);
            break;
    }
}

// Redirigir si no hay caja abierta
if (!$caja_abierta && basename($_SERVER['PHP_SELF']) != 'apertura.php') {
    header("Location: modules/caja/apertura.php");
    exit;
}

// Obtener estadísticas
$query_stats = "SELECT 
    COUNT(*) as total_productos,
    SUM(stock_actual) as total_stock,
    SUM(CASE WHEN stock_actual <= stock_minimo THEN 1 ELSE 0 END) as stock_bajo
    FROM productos";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Obtener últimas ventas del día
$query_ventas = "SELECT p.nombre, m.cantidad, p.precio_venta, m.fecha_movimiento
                 FROM movimientos_inventario m
                 JOIN productos p ON m.producto_id = p.id
                 WHERE m.tipo = 'salida' 
                 AND DATE(m.fecha_movimiento) = CURDATE()
                 ORDER BY m.fecha_movimiento DESC
                 LIMIT 10";
$stmt_ventas = $db->prepare($query_ventas);
$stmt_ventas->execute();
$ultimas_ventas = $stmt_ventas->fetchAll(PDO::FETCH_ASSOC);

// Calcular ventas del día
$total_ventas_dia = 0;
foreach ($ultimas_ventas as $venta) {
    $total_ventas_dia += $venta['cantidad'] * $venta['precio_venta'];
}

// Procesar búsqueda por código de barras O nombre del producto
$codigo_buscado = '';
$producto_encontrado = null;
$productos_encontrados = []; // Nueva variable para múltiples resultados

if (isset($_GET['buscar'])) {
    $codigo_buscado = trim($_GET['buscar']);
    if (!empty($codigo_buscado)) {
        // Buscar por código de barras O nombre (con LIKE para búsqueda parcial)
        $query_buscar = "SELECT * FROM productos 
                        WHERE codigo_barras = :codigo 
                           OR nombre LIKE :nombre 
                        LIMIT 10"; // Limitar a 10 resultados
        
        $stmt_buscar = $db->prepare($query_buscar);
        $stmt_buscar->bindParam(':codigo', $codigo_buscado);
        
        // Agregar % para búsqueda parcial en el nombre
        $termino_nombre = "%" . $codigo_buscado . "%";
        $stmt_buscar->bindParam(':nombre', $termino_nombre);
        
        $stmt_buscar->execute();
        
        // Obtener TODOS los productos que coincidan (no solo el primero)
        $productos_encontrados = $stmt_buscar->fetchAll(PDO::FETCH_ASSOC);
        
        // Para compatibilidad con tu código existente, tomamos el primero si hay resultados
        if (count($productos_encontrados) > 0) {
            $producto_encontrado = $productos_encontrados[0];
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard">
    <!-- BARRA DE BÚSQUEDA POR CÓDIGO DE BARRAS -->
    <div class="barcode-scanner-section">
        <div class="scanner-container">
            <div class="scanner-icon">📷</div>
            <input type="text" 
                   id="barcode-scanner" 
                   class="barcode-input" 
                   placeholder="Escanea un código de barras o ingrésalo manualmente..."
                   autofocus
                   value="<?php echo htmlspecialchars($codigo_buscado); ?>">
            <button type="button" onclick="buscarProducto()" class="btn-scanner">🔍 Buscar</button>
        </div>
        
        <?php if ($codigo_buscado && count($productos_encontrados) > 0): ?>
        <div class="producto-encontrado">
            <h4><?php echo count($productos_encontrados); ?> producto(s) encontrado(s):</h4>
            
            <?php foreach ($productos_encontrados as $producto): ?>
            <div class="producto-info" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px dashed #eee;">
                <div class="producto-dato">
                    <span class="label">Código:</span>
                    <span class="value"><?php echo htmlspecialchars($producto['codigo_barras'] ?: 'Sin código'); ?></span>
                </div>
                <div class="producto-dato">
                    <span class="label">Nombre:</span>
                    <span class="value"><?php echo htmlspecialchars($producto['nombre']); ?></span>
                </div>
                <div class="producto-dato">
                    <span class="label">Stock:</span>
                    <span class="value <?php echo $producto['stock_actual'] <= $producto['stock_minimo'] ? 'stock-bajo' : 'stock-normal'; ?>">
                        <?php echo $producto['stock_actual']; ?>
                    </span>
                </div>
                <div class="producto-dato">
                    <span class="label">Precio:</span>
                    <span class="value precio">$<?php echo number_format($producto['precio_venta'], 2); ?></span>
                </div>
                <div class="producto-acciones">
                    <!-- Botón para vender individualmente (sistema antiguo) -->
                    <a href="modules/inventario/salida.php?producto_id=<?php echo $producto['id']; ?>" 
                       class="btn btn-success btn-small">
                        Vender 1 Producto
                    </a>
                    
                    <!-- Botón para agregar al carrito (nuevo sistema) -->
                    <button onclick="agregarAlCarrito(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')" 
                            class="btn btn-primary btn-small">
                        🛒 Agregar al Carrito
                    </button>
                    
                    <a href="modules/productos/editar.php?id=<?php echo $producto['id']; ?>" 
                       class="btn btn-warning btn-small">
                        Editar
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif ($codigo_buscado && count($productos_encontrados) === 0): ?>
        <div class="producto-no-encontrado">
            <h4>⚠️ Producto no encontrado</h4>
            <p>El código de barras "<?php echo htmlspecialchars($codigo_buscado); ?>" no existe en la base de datos.</p>
            <a href="modules/productos/agregar.php?codigo_barras=<?php echo urlencode($codigo_buscado); ?>" 
               class="btn btn-success">
                ➕ Agregar nuevo producto con este código
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="mensaje success">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <div class="welcome-section">
        <h2>Panel de Control</h2>
        <div class="status-caja <?php echo $caja_abierta ? 'abierta' : 'cerrada'; ?>">
            <span class="status-icon"><?php echo $caja_abierta ? '💰' : '🔒'; ?></span>
            <span class="status-text">Caja <?php echo $caja_abierta ? 'ABIERTA' : 'CERRADA'; ?></span>
            <?php if ($caja_abierta): ?>
                <span class="status-monto">Monto inicial: $<?php echo number_format($caja_abierta['monto_inicial'], 2); ?></span>
                <a href="modules/caja/cierre.php" class="btn btn-danger btn-small">Cerrar Caja</a>
            <?php else: ?>
                <a href="modules/caja/apertura.php" class="btn btn-success btn-small">Abrir Caja</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- NUEVA SECCIÓN: ACCESO AL SISTEMA DE VENTAS MÚLTIPLES -->
    <div class="nuevo-sistema-ventas" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                                            color: white; 
                                            padding: 1.5rem; 
                                            border-radius: 10px; 
                                            margin-bottom: 2rem;
                                            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">🛒 NUEVO: Sistema de Ventas Múltiples</h3>
                <p style="margin: 0; opacity: 0.9;">Vende varios productos a la vez con nuestro nuevo sistema de carrito</p>
            </div>
            <a href="modules/ventas/nueva.php" 
               style="background: white; 
                      color: #28a745; 
                      padding: 0.75rem 1.5rem; 
                      border-radius: 25px; 
                      text-decoration: none; 
                      font-weight: bold;
                      display: flex;
                      align-items: center;
                      gap: 0.5rem;
                      transition: all 0.3s;">
                <i class="fas fa-cart-plus"></i> Probar Ahora
            </a>
        </div>
    </div>
    
    <div class="quick-actions">
        <div class="action-card">
            <h3>📦 Gestión Rápida</h3>
            <div class="action-buttons">
                <a href="modules/productos/agregar.php" class="btn-action">
                    <span class="icon">➕</span>
                    <span>Agregar Producto</span>
                </a>
                <a href="modules/inventario/entrada.php" class="btn-action">
                    <span class="icon">⬆️</span>
                    <span>Entrada Stock</span>
                </a>
                <a href="modules/ventas/nueva.php" class="btn-action" style="background: #28a745; color: white;">
                    <span class="icon">🛒</span>
                    <span>Venta Múltiple</span>
                </a>
                <a href="modules/caja/cierre.php" class="btn-action">
                    <span class="icon">💰</span>
                    <span>Cerrar Caja</span>
                </a>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h4><?php echo $stats['total_productos']; ?></h4>
                    <p>Productos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h4><?php echo $stats['total_stock']; ?></h4>
                    <p>Unidades en Stock</p>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <h4><?php echo $stats['stock_bajo']; ?></h4>
                    <p>Stock Bajo</p>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h4>$<?php echo number_format($total_ventas_dia, 2); ?></h4>
                    <p>Ventas Hoy</p>
                </div>
            </div>
        </div>
    </div>
    
<div class="dashboard-sections">
    <div class="section">
        <h3>📈 Últimas Ventas del Día</h3>
        <div class="ventas-table">
            <?php if (count($ultimas_ventas) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Total</th>
                            <th>Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas_ventas as $venta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($venta['nombre']); ?></td>
                            <td><?php echo $venta['cantidad']; ?></td>
                            <td>$<?php echo number_format($venta['precio_venta'], 2); ?></td>
                            <td>$<?php echo number_format($venta['cantidad'] * $venta['precio_venta'], 2); ?></td>
                            <td><?php echo date('H:i', strtotime($venta['fecha_movimiento'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data">No hay ventas registradas hoy.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="section">
        <h3>⚡ Acciones Rápidas</h3>
        <div class="quick-links">
            <a href="modules/ventas/nueva.php" class="quick-link" style="background: #28a745; color: white;">
                <span class="link-icon">🛒</span>
                <span class="link-text">Venta Múltiple</span>
                <small style="color: rgba(255,255,255,0.8);">Nuevo sistema con carrito</small>
            </a>
            <a href="modules/ventas/index.php" class="quick-link" style="background: #17a2b8; color: white;">
                <span class="link-icon">📋</span>
                <span class="link-text">Historial Ventas</span>
                <small style="color: rgba(255,255,255,0.8);">Ver todas las ventas</small>
            </a>
            <a href="modules/inventario/salida.php" class="quick-link">
                <span class="link-icon">📤</span>
                <span class="link-text">Venta Individual</span>
                <small>Venta rápida de 1 producto</small>
            </a>
            <a href="backups/backup.php?action=backup" class="quick-link" onclick="return confirm('¿Generar backup de la base de datos?')">
                <span class="link-icon">💾</span>
                <span class="link-text">Hacer Backup</span>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('barcode-scanner');
    
    if(barcodeInput) {
        barcodeInput.focus();
        
        // Limpiar el campo cada 5 segundos para evitar códigos antiguos
        setInterval(function() {
            if(barcodeInput.value && !document.querySelector('.producto-encontrado') && !document.querySelector('.producto-no-encontrado')) {
                barcodeInput.value = '';
            }
        }, 5000);
        
        // Detectar cuando el escáner termine (normalmente envía un Enter)
        barcodeInput.addEventListener('keydown', function(e) {
            // Si es Enter (código 13) y hay texto
            if(e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                buscarProducto();
            }
            
            // Si es Escape (código 27) para limpiar
            if(e.key === 'Escape' || e.keyCode === 27) {
                this.value = '';
                this.focus();
            }
            
            // F2 para ir a venta múltiple
            if (e.key === 'F2' || e.keyCode === 113) {
                e.preventDefault();
                window.location.href = 'modules/ventas/nueva.php';
            }
            
            // F3 para buscar
            if (e.key === 'F3' || e.keyCode === 114) {
                e.preventDefault();
                this.focus();
                this.select();
            }
        });
        
        // Auto-enfocar cuando se hace clic en cualquier lugar
        document.addEventListener('click', function() {
            if(document.activeElement !== barcodeInput) {
                barcodeInput.focus();
            }
        });
    }
});

function buscarProducto() {
    const codigo = document.getElementById('barcode-scanner').value;
    if(codigo.trim()) {
        // Redirigir a la misma página con el parámetro de búsqueda
        window.location.href = `index.php?buscar=${encodeURIComponent(codigo)}`;
    } else {
        alert('Por favor, escanee un código de barras o ingréselo manualmente.');
    }
}

// Función para agregar producto al carrito
function agregarAlCarrito(productoId, nombreProducto) {
    // Preguntar cantidad
    const cantidad = prompt(`¿Cuántas unidades de "${nombreProducto}" desea agregar al carrito?`, "1");
    
    if (cantidad && !isNaN(cantidad) && parseInt(cantidad) > 0) {
        // Redirigir a la página de ventas con los parámetros
        window.location.href = `modules/ventas/nueva.php?agregar=${productoId}&cantidad=${cantidad}`;
    } else if (cantidad !== null) {
        alert('Por favor ingrese una cantidad válida.');
    }
}

// Función para agregar producto rápidamente
function agregarProductoRapido(codigo) {
    window.location.href = `modules/productos/agregar.php?codigo_barras=${encodeURIComponent(codigo)}`;
}

// Función para vender producto rápidamente
function venderProductoRapido(productoId) {
    window.location.href = `modules/inventario/salida.php?producto_id=${productoId}`;
}
</script>

<style>
/* Estilos para el buscador de código de barras */
.barcode-scanner-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.scanner-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 0.75rem;
    border-radius: 50px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.scanner-icon {
    font-size: 1.5rem;
    color: #667eea;
    margin-left: 0.5rem;
}

.barcode-input {
    flex: 1;
    border: none;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    outline: none;
    border-radius: 25px;
    background: #f8f9fa;
}

.barcode-input:focus {
    background: white;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}

.btn-scanner {
    background: #667eea;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-scanner:hover {
    background: #5a6fd8;
    transform: translateY(-1px);
}

/* Estilos para los resultados de búsqueda */
.producto-encontrado, .producto-no-encontrado {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    animation: slideDown 0.3s ease;
}

.producto-encontrado h4, .producto-no-encontrado h4 {
    margin-top: 0;
    color: #333;
    margin-bottom: 1rem;
}

.producto-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.producto-dato {
    display: flex;
    flex-direction: column;
}

.producto-dato .label {
    font-weight: bold;
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.producto-dato .value {
    color: #333;
    font-size: 1.1rem;
}

.stock-bajo {
    color: #dc3545;
    font-weight: bold;
}

.stock-normal {
    color: #28a745;
}

.precio {
    font-weight: bold;
    color: #28a745;
}

.producto-acciones {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-start;
    grid-column: 1 / -1;
    flex-wrap: wrap;
}

.producto-acciones .btn {
    min-width: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.producto-no-encontrado p {
    color: #666;
    margin-bottom: 1rem;
}

.producto-no-encontrado .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Animación para mostrar resultados */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estilos existentes */
.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #eee;
}

.status-caja {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: bold;
}

.status-caja.abierta {
    background: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
}

.status-caja.cerrada {
    background: #f8d7da;
    color: #721c24;
    border: 2px solid #f5c6cb;
}

.status-icon {
    font-size: 1.5rem;
}

.status-monto {
    font-weight: normal;
    font-size: 0.9rem;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.btn-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
}

.btn-action:hover {
    background: #007bff;
    color: white;
    border-color: #007bff;
    transform: translateY(-2px);
}

.btn-action .icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.dashboard-sections {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-top: 2rem;
}

.section {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.ventas-table {
    max-height: 300px;
    overflow-y: auto;
    margin-top: 1rem;
}

.ventas-table table {
    width: 100%;
    font-size: 0.9rem;
}

.ventas-table th {
    background: #f8f9fa;
    position: sticky;
    top: 0;
}

.ventas-table .table-footer {
    margin-top: 1rem;
    text-align: center;
}

.ventas-table .btn-link {
    color: #007bff;
    text-decoration: none;
    font-size: 0.9rem;
}

.ventas-table .btn-link:hover {
    text-decoration: underline;
}

.quick-links {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
}

.quick-link {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
}

.quick-link:hover {
    background: #007bff;
    color: white;
    transform: translateX(5px);
}

.quick-link .link-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.quick-link .link-text {
    font-weight: bold;
    font-size: 1rem;
}

.quick-link small {
    font-size: 0.85rem;
    opacity: 0.7;
    margin-top: 0.25rem;
}

.no-data {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
    font-style: italic;
}

/* Nuevos estilos para estadísticas */
.stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stat-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 1.5rem;
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 8px;
}

.stat-info h4 {
    margin: 0;
    font-size: 1.5rem;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-sections {
        grid-template-columns: 1fr;
    }
    
    .stats {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .producto-acciones {
        flex-direction: column;
    }
    
    .producto-acciones .btn {
        width: 100%;
    }
    
    .welcome-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>