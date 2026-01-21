<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar caja abierta
$caja_abierta = $functions->isCajaAbierta();
if (!$caja_abierta) {
    header("Location: ../caja/apertura.php");
    exit;
}

// Inicializar carrito en sesión si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Manejar acciones del carrito
$mensaje = '';
$tipo_mensaje = '';

// Agregar producto desde parámetros URL (para integración con index.php)
if (isset($_GET['agregar']) && isset($_GET['cantidad'])) {
    $producto_id = intval($_GET['agregar']);
    $cantidad = intval($_GET['cantidad']);
    
    if ($producto_id > 0 && $cantidad > 0) {
        // Verificar si el producto existe
        $query = "SELECT id, nombre, codigo_barras, precio_venta, stock_actual 
                 FROM productos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $producto_id);
        $stmt->execute();
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            // Verificar stock
            if ($producto['stock_actual'] < $cantidad) {
                $mensaje = "❌ Stock insuficiente para '{$producto['nombre']}'. Disponible: {$producto['stock_actual']}";
                $tipo_mensaje = 'error';
            } else {
                // Buscar si ya está en el carrito
                $encontrado = false;
                foreach ($_SESSION['carrito'] as &$item) {
                    if ($item['id'] == $producto_id) {
                        $nueva_cantidad = $item['cantidad'] + $cantidad;
                        if ($nueva_cantidad <= $producto['stock_actual']) {
                            $item['cantidad'] = $nueva_cantidad;
                            $item['subtotal'] = $nueva_cantidad * $item['precio'];
                            $mensaje = "✅ Producto actualizado en el carrito";
                        } else {
                            $mensaje = "❌ No hay suficiente stock para la cantidad solicitada";
                            $tipo_mensaje = 'error';
                        }
                        $encontrado = true;
                        break;
                    }
                }
                
                if (!$encontrado) {
                    $_SESSION['carrito'][] = [
                        'id' => $producto['id'],
                        'nombre' => $producto['nombre'],
                        'codigo_barras' => $producto['codigo_barras'],
                        'precio' => $producto['precio_venta'],
                        'cantidad' => $cantidad,
                        'subtotal' => $producto['precio_venta'] * $cantidad,
                        'stock_disponible' => $producto['stock_actual']
                    ];
                    $mensaje = "✅ Producto agregado al carrito desde búsqueda";
                }
                $tipo_mensaje = 'success';
                
                // Redirigir para limpiar URL
                header("Location: nueva.php");
                exit;
            }
        }
    }
}

if (isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'agregar':
            if (isset($_POST['producto_id'])) {
                $producto_id = intval($_POST['producto_id']);
                $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;
                
                // Verificar si el producto existe
                $query = "SELECT id, nombre, codigo_barras, precio_venta, stock_actual 
                         FROM productos WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $producto_id);
                $stmt->execute();
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($producto) {
                    // Verificar stock
                    if ($producto['stock_actual'] < $cantidad) {
                        $mensaje = "❌ Stock insuficiente para '{$producto['nombre']}'. Disponible: {$producto['stock_actual']}";
                        $tipo_mensaje = 'error';
                    } else {
                        // Buscar si ya está en el carrito
                        $encontrado = false;
                        foreach ($_SESSION['carrito'] as &$item) {
                            if ($item['id'] == $producto_id) {
                                $nueva_cantidad = $item['cantidad'] + $cantidad;
                                if ($nueva_cantidad <= $producto['stock_actual']) {
                                    $item['cantidad'] = $nueva_cantidad;
                                    $item['subtotal'] = $nueva_cantidad * $item['precio'];
                                    $mensaje = "✅ Producto actualizado en el carrito";
                                } else {
                                    $mensaje = "❌ No hay suficiente stock para la cantidad solicitada";
                                    $tipo_mensaje = 'error';
                                }
                                $encontrado = true;
                                break;
                            }
                        }
                        
                        if (!$encontrado) {
                            $_SESSION['carrito'][] = [
                                'id' => $producto['id'],
                                'nombre' => $producto['nombre'],
                                'codigo_barras' => $producto['codigo_barras'],
                                'precio' => $producto['precio_venta'],
                                'cantidad' => $cantidad,
                                'subtotal' => $producto['precio_venta'] * $cantidad,
                                'stock_disponible' => $producto['stock_actual']
                            ];
                            $mensaje = "✅ Producto agregado al carrito";
                        }
                        $tipo_mensaje = 'success';
                    }
                } else {
                    $mensaje = "❌ Producto no encontrado";
                    $tipo_mensaje = 'error';
                }
            }
            break;
            
        case 'actualizar':
            if (isset($_POST['indice']) && isset($_POST['cantidad'])) {
                $indice = intval($_POST['indice']);
                $nueva_cantidad = intval($_POST['cantidad']);
                
                if (isset($_SESSION['carrito'][$indice])) {
                    $producto = $_SESSION['carrito'][$indice];
                    
                    // Verificar stock disponible
                    $query = "SELECT stock_actual FROM productos WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $producto['id']);
                    $stmt->execute();
                    $stock_actual = $stmt->fetchColumn();
                    
                    if ($nueva_cantidad <= $stock_actual && $nueva_cantidad > 0) {
                        $_SESSION['carrito'][$indice]['cantidad'] = $nueva_cantidad;
                        $_SESSION['carrito'][$indice]['subtotal'] = $nueva_cantidad * $producto['precio'];
                        $mensaje = "✅ Cantidad actualizada";
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = "❌ Cantidad inválida. Stock disponible: $stock_actual";
                        $tipo_mensaje = 'error';
                    }
                }
            }
            break;
            
        case 'eliminar':
            if (isset($_POST['indice'])) {
                $indice = intval($_POST['indice']);
                if (isset($_SESSION['carrito'][$indice])) {
                    $nombre_producto = $_SESSION['carrito'][$indice]['nombre'];
                    array_splice($_SESSION['carrito'], $indice, 1);
                    $mensaje = "✅ '$nombre_producto' eliminado del carrito";
                    $tipo_mensaje = 'success';
                }
            }
            break;
            
        case 'vaciar':
            $_SESSION['carrito'] = [];
            $mensaje = "✅ Carrito vaciado";
            $tipo_mensaje = 'success';
            break;
    }
}

// Calcular totales del carrito
$total_carrito = 0;
$total_items = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_carrito += $item['subtotal'];
    $total_items += $item['cantidad'];
}

include '../../includes/header.php';
?>

<div class="container">
    <div class="ventas-header">
        <h2>🛒 Venta Múltiple - Sistema de Carrito</h2>
        <div class="status-info">
            <span class="badge">💰 Caja Abierta: $<?php echo number_format($caja_abierta['monto_inicial'], 2); ?></span>
            <span class="badge">📦 Productos en Carrito: <?php echo count($_SESSION['carrito']); ?></span>
            <span class="badge">🧮 Total Items: <?php echo $total_items; ?></span>
            <span class="badge total">💵 Total: $<?php echo number_format($total_carrito, 2); ?></span>
        </div>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <div class="ventas-container">
        <!-- Panel izquierdo: Búsqueda y agregar productos -->
        <div class="panel-busqueda">
            <h3>🔍 Buscar Productos</h3>
            <form method="get" action="" class="search-form" id="formBusqueda">
                <div class="form-group">
                    <input type="text" id="buscarProducto" name="buscar" 
                           placeholder="Código de barras o nombre del producto..." 
                           class="search-input" autofocus>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
            
            <div id="resultadosBusqueda" class="resultados-busqueda">
                <?php
                // Mostrar resultados de búsqueda si hay
                if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
                    $termino = trim($_GET['buscar']);
                    $query = "SELECT id, nombre, codigo_barras, precio_venta, stock_actual 
                             FROM productos 
                             WHERE codigo_barras LIKE :codigo 
                                OR nombre LIKE :nombre 
                             ORDER BY nombre LIMIT 10";
                    $stmt = $db->prepare($query);
                    $likeTerm = "%$termino%";
                    $stmt->bindParam(':codigo', $likeTerm);
                    $stmt->bindParam(':nombre', $likeTerm);
                    $stmt->execute();
                    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($resultados) > 0) {
                        echo '<h4>Resultados encontrados:</h4>';
                        foreach ($resultados as $producto) {
                            echo '<div class="producto-resultado">';
                            echo '<div class="producto-info">';
                            echo '<strong>' . htmlspecialchars($producto['nombre']) . '</strong><br>';
                            echo '<small>Código: ' . htmlspecialchars($producto['codigo_barras']) . '</small><br>';
                            echo '<small>Precio: $' . number_format($producto['precio_venta'], 2) . '</small><br>';
                            echo '<small>Stock: ' . $producto['stock_actual'] . '</small>';
                            echo '</div>';
                            echo '<form method="post" action="" class="form-agregar">';
                            echo '<input type="hidden" name="producto_id" value="' . $producto['id'] . '">';
                            echo '<input type="hidden" name="accion" value="agregar">';
                            echo '<div class="cantidad-agregar">';
                            echo '<input type="number" name="cantidad" value="1" min="1" max="' . $producto['stock_actual'] . '" style="width: 60px;">';
                            echo '<button type="submit" class="btn btn-success btn-sm">';
                            echo '<i class="fas fa-cart-plus"></i> Agregar';
                            echo '</button>';
                            echo '</div>';
                            echo '</form>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="no-results">No se encontraron productos.</p>';
                    }
                }
                ?>
            </div>
        </div>
        
        <!-- Panel derecho: Carrito de compras -->
        <div class="panel-carrito">
            <div class="carrito-header">
                <h3>🛍️ Carrito de Venta</h3>
                <?php if (count($_SESSION['carrito']) > 0): ?>
                <form method="post" action="" style="display: inline;">
                    <input type="hidden" name="accion" value="vaciar">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Vaciar todo el carrito?')">
                        <i class="fas fa-trash"></i> Vaciar Carrito
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (count($_SESSION['carrito']) == 0): ?>
                <div class="carrito-vacio">
                    <i class="fas fa-shopping-cart fa-3x"></i>
                    <h4>Carrito vacío</h4>
                    <p>Busca productos y agrégalos al carrito para comenzar una venta.</p>
                </div>
            <?php else: ?>
                <div class="carrito-items">
                    <table class="table carrito-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['carrito'] as $indice => $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['nombre']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['codigo_barras']); ?></small>
                                </td>
                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                <td>
                                    <form method="post" action="" class="form-cantidad">
                                        <input type="hidden" name="accion" value="actualizar">
                                        <input type="hidden" name="indice" value="<?php echo $indice; ?>">
                                        <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" 
                                               min="1" max="<?php echo $item['stock_disponible']; ?>" 
                                               style="width: 70px;" onchange="this.form.submit()">
                                    </form>
                                </td>
                                <td class="subtotal">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                <td>
                                    <form method="post" action="" style="display: inline;">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="indice" value="<?php echo $indice; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('¿Eliminar este producto del carrito?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3" class="text-right"><strong>Total General:</strong></td>
                                <td colspan="2" class="total-venta">
                                    <strong>$<?php echo number_format($total_carrito, 2); ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <!-- Formulario para finalizar venta -->
                    <div class="finalizar-venta">
                        <h4>💰 Finalizar Venta</h4>
                        <form method="post" action="procesar.php" id="formFinalizar">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cliente">👤 Cliente:</label>
                                    <input type="text" id="cliente" name="cliente" 
                                           placeholder="Nombre del cliente (opcional)" 
                                           value="CLIENTE GENERAL">
                                </div>
                                
                                <div class="form-group">
                                    <label for="metodo_pago">💳 Método de Pago:</label>
                                    <select id="metodo_pago" name="metodo_pago" required>
                                        <option value="efectivo">💵 Efectivo</option>
                                        <option value="tarjeta">💳 Tarjeta</option>
                                        <option value="transferencia">🏦 Transferencia</option>
                                        <option value="mixto">🔀 Mixto</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="observaciones">📝 Observaciones:</label>
                                    <textarea id="observaciones" name="observaciones" 
                                              placeholder="Notas adicionales..." 
                                              rows="2"></textarea>
                                </div>
                            </div>
                            
                            <div class="resumen-final">
                                <div class="resumen-item">
                                    <span>Productos:</span>
                                    <span><?php echo count($_SESSION['carrito']); ?></span>
                                </div>
                                <div class="resumen-item">
                                    <span>Total Items:</span>
                                    <span><?php echo $total_items; ?></span>
                                </div>
                                <div class="resumen-item total">
                                    <span>Total a Cobrar:</span>
                                    <span class="total-venta">$<?php echo number_format($total_carrito, 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="acciones-final">
                                <button type="submit" class="btn btn-success btn-lg" 
                                        onclick="return confirm('¿Confirmar venta por $<?php echo number_format($total_carrito, 2); ?>?')">
                                    <i class="fas fa-cash-register"></i> Confirmar y Cobrar Venta
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enfocar campo de búsqueda
    const buscarInput = document.getElementById('buscarProducto');
    if (buscarInput) {
        buscarInput.focus();
        
        // Búsqueda en tiempo real con AJAX (opcional)
        buscarInput.addEventListener('input', function() {
            const termino = this.value.trim();
            if (termino.length >= 2) {
                buscarProductosAJAX(termino);
            }
        });
    }
    
    // Confirmar antes de vaciar carrito
    const btnVaciar = document.querySelector('button[value="vaciar"]');
    if (btnVaciar) {
        btnVaciar.addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de vaciar todo el carrito?')) {
                e.preventDefault();
            }
        });
    }
});

function buscarProductosAJAX(termino) {
    // Implementación opcional para búsqueda en tiempo real
    console.log('Buscando:', termino);
}

function actualizarCantidad(indice, nuevaCantidad) {
    // Esta función se llama desde el onchange del input cantidad
    document.querySelector(`input[name="indice"][value="${indice}"]`).form.submit();
}
</script>

<style>
.ventas-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.ventas-header h2 {
    margin: 0 0 1rem 0;
    font-size: 1.8rem;
}

.status-info {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.status-info .badge {
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
}

.status-info .badge.total {
    background: rgba(40, 167, 69, 0.8);
    font-weight: bold;
}

.ventas-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
    margin-top: 2rem;
}

@media (max-width: 1024px) {
    .ventas-container {
        grid-template-columns: 1fr;
    }
}

.panel-busqueda, .panel-carrito {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}

.panel-busqueda h3, .panel-carrito h3 {
    margin-top: 0;
    color: #333;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.search-form .form-group {
    display: flex;
    gap: 0.5rem;
}

.search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
}

.search-input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.resultados-busqueda {
    max-height: 500px;
    overflow-y: auto;
    margin-top: 1.5rem;
}

.producto-resultado {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #dee2e6;
    transition: all 0.3s;
}

.producto-resultado:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.producto-info {
    flex: 1;
}

.form-agregar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cantidad-agregar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.carrito-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.carrito-vacio {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.carrito-vacio i {
    margin-bottom: 1rem;
    color: #dee2e6;
}

.carrito-vacio h4 {
    margin-bottom: 0.5rem;
    color: #495057;
}

.carrito-table {
    margin-bottom: 1.5rem;
}

.carrito-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.carrito-table td {
    vertical-align: middle;
}

.subtotal {
    font-weight: bold;
    color: #28a745;
}

.total-row {
    background: #f8f9fa;
    font-size: 1.1rem;
}

.total-venta {
    color: #28a745;
    font-weight: bold;
    font-size: 1.2rem;
}

.form-cantidad input[type="number"] {
    padding: 0.25rem 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    text-align: center;
}

.finalizar-venta {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    border: 2px solid #e9ecef;
}

.finalizar-venta h4 {
    margin-top: 0;
    color: #495057;
    margin-bottom: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 1rem;
}

.resumen-final {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border: 1px solid #dee2e6;
}

.resumen-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px dashed #dee2e6;
}

.resumen-item:last-child {
    border-bottom: none;
}

.resumen-item.total {
    font-weight: bold;
    font-size: 1.1rem;
    padding-top: 1rem;
    margin-top: 0.5rem;
    border-top: 2px solid #28a745;
}

.acciones-final {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
}

.acciones-final .btn {
    min-width: 200px;
}

.no-results {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
    font-style: italic;
}
</style>

<?php include '../../includes/footer.php'; ?>