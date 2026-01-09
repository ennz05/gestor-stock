<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar caja abierta
$caja_abierta = $functions->isCajaAbierta();
$_SESSION['caja_abierta'] = $caja_abierta;

// Redirigir si no hay caja abierta
if (!$caja_abierta) {
    header("Location: ../caja/apertura.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$producto_info = null;

// Buscar producto cuando se escanea código
if (isset($_POST['buscar_producto'])) {
    $query = "SELECT id, nombre, stock_actual, precio_venta FROM productos WHERE codigo_barras = :codigo_barras";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':codigo_barras', $_POST['codigo_barras']);
    $stmt->execute();
    $producto_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto_info) {
        $mensaje = "❌ Producto no encontrado. Verifique el código de barras.";
        $tipo_mensaje = "error";
    }
}

// Procesar salida de inventario
if (isset($_POST['registrar_salida'])) {
    try {
        $query_producto = "SELECT id, nombre, stock_actual FROM productos WHERE id = :producto_id";
        $stmt_producto = $db->prepare($query_producto);
        $stmt_producto->bindParam(':producto_id', $_POST['producto_id']);
        $stmt_producto->execute();
        $producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            // Verificar stock suficiente
            if ($producto['stock_actual'] < $_POST['cantidad']) {
                $mensaje = "❌ Stock insuficiente. Stock actual: " . $producto['stock_actual'];
                $tipo_mensaje = "error";
            } else {
                // Calcular total
                $precio_venta = $_POST['precio_venta'];
                $cantidad = $_POST['cantidad'];
                $total = $precio_venta * $cantidad;
                
                // Registrar movimiento
                $query_movimiento = "INSERT INTO movimientos_inventario 
                                    (producto_id, tipo, cantidad, motivo, usuario) 
                                    VALUES 
                                    (:producto_id, 'salida', :cantidad, :motivo, :usuario)";
                $stmt_movimiento = $db->prepare($query_movimiento);
                $stmt_movimiento->bindParam(':producto_id', $_POST['producto_id']);
                $stmt_movimiento->bindParam(':cantidad', $cantidad);
                $stmt_movimiento->bindParam(':motivo', $_POST['motivo']);
                $stmt_movimiento->bindParam(':usuario', $_POST['usuario']);
                
                // Actualizar stock
                $query_stock = "UPDATE productos SET stock_actual = stock_actual - :cantidad WHERE id = :producto_id";
                $stmt_stock = $db->prepare($query_stock);
                $stmt_stock->bindParam(':cantidad', $cantidad);
                $stmt_stock->bindParam(':producto_id', $_POST['producto_id']);
                
                if ($stmt_movimiento->execute() && $stmt_stock->execute()) {
                    $mensaje = "✅ Venta registrada exitosamente!<br>";
                    $mensaje .= "Producto: " . $producto['nombre'] . "<br>";
                    $mensaje .= "Cantidad: " . $cantidad . "<br>";
                    $mensaje .= "Precio unitario: $" . number_format($precio_venta, 2) . "<br>";
                    $mensaje .= "<strong>Total: $" . number_format($total, 2) . "</strong>";
                    $tipo_mensaje = "success";
                    
                    // Resetear formulario
                    $_POST['codigo_barras'] = '';
                    $producto_info = null;
                }
            }
        }
    } catch(PDOException $exception) {
        $mensaje = "❌ Error: " . $exception->getMessage();
        $tipo_mensaje = "error";
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <h2>📤 Venta de Productos</h2>
    
    <div class="status-info">
        <span class="badge">💰 Caja Abierta: $<?php echo number_format($caja_abierta['monto_inicial'], 2); ?></span>
        <span class="badge">👤 Usuario: <?php echo $functions->getUsuarioDefault(); ?></span>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulario de búsqueda -->
    <form method="post" action="" class="search-form">
        <div class="form-group">
            <label for="codigo_barras">📋 Código de Barras:</label>
            <div class="search-container">
                <input type="text" id="codigo_barras" name="codigo_barras" class="barcode-input" 
                       placeholder="Escanee el código de barras" required autofocus
                       value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
                <button type="submit" name="buscar_producto" class="btn">
                    <span style="margin-right: 5px;">🔍</span> Buscar
                </button>
            </div>
            <small>Escanee el producto que desea vender</small>
        </div>
    </form>
    
    <!-- Formulario de venta (solo si se encontró producto) -->
    <?php if ($producto_info): ?>
    <div class="product-found">
        <h3>✅ Producto Listo para Vender</h3>
        <div class="product-details">
            <div class="detail-grid">
                <div class="detail-item">
                    <label>🏷️ Producto:</label>
                    <span><?php echo htmlspecialchars($producto_info['nombre']); ?></span>
                </div>
                <div class="detail-item">
                    <label>📦 Stock Disponible:</label>
                    <span class="stock-available"><?php echo $producto_info['stock_actual']; ?></span>
                </div>
                <div class="detail-item">
                    <label>🏷️ Precio Venta:</label>
                    <span>$<?php echo number_format($producto_info['precio_venta'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <label>🆔 ID:</label>
                    <span>#<?php echo $producto_info['id']; ?></span>
                </div>
            </div>
        </div>
        
        <form method="post" action="" class="inventory-form" id="ventaForm">
            <input type="hidden" name="producto_id" value="<?php echo $producto_info['id']; ?>">
            <input type="hidden" name="codigo_barras" value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="cantidad">📦 Cantidad a Vender:</label>
                    <input type="number" id="cantidad" name="cantidad" min="1" 
                           max="<?php echo $producto_info['stock_actual']; ?>" 
                           value="1" required oninput="calcularTotal()">
                    <small>Máximo disponible: <?php echo $producto_info['stock_actual']; ?></small>
                </div>
                
                <div class="form-group">
                    <label for="precio_venta">🏷️ Precio Unitario:</label>
                    <div class="input-group">
                        <span class="input-addon">$</span>
                        <input type="number" id="precio_venta" name="precio_venta" 
                               step="0.01" min="0" 
                               value="<?php echo $producto_info['precio_venta']; ?>" 
                               required oninput="calcularTotal()">
                    </div>
                    <small>Puede modificar el precio si es necesario</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="motivo">📝 Motivo:</label>
                    <select id="motivo" name="motivo" required>
                        <option value="">Seleccione un motivo</option>
                        <option value="venta">🛒 Venta</option>
                        <option value="devolucion_proveedor">↩️ Devolución a Proveedor</option>
                        <option value="danado">⚠️ Producto Dañado</option>
                        <option value="caducado">📅 Producto Caducado</option>
                        <option value="ajuste_negativo">📉 Ajuste Negativo</option>
                        <option value="transferencia">🔄 Transferencia Saliente</option>
                        <option value="otros">📌 Otros</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="usuario">👤 Responsable:</label>
                    <input type="text" id="usuario" name="usuario" 
                           value="<?php echo $functions->getUsuarioDefault(); ?>" required>
                </div>
            </div>
            
            <!-- Resumen de la venta -->
            <div class="resumen-venta">
                <h4>🧮 Resumen de la Venta</h4>
                <div class="resumen-grid">
                    <div class="resumen-item">
                        <label>Cantidad:</label>
                        <span id="resumenCantidad">1</span>
                    </div>
                    <div class="resumen-item">
                        <label>Precio Unitario:</label>
                        <span id="resumenPrecio">$<?php echo number_format($producto_info['precio_venta'], 2); ?></span>
                    </div>
                    <div class="resumen-item total">
                        <label>Total a Cobrar:</label>
                        <span id="resumenTotal" class="total-venta">$<?php echo number_format($producto_info['precio_venta'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="registrar_salida" class="btn btn-success">
                    <span style="margin-right: 8px;">💰</span> Confirmar Venta y Cobrar
                </button>
                <button type="button" onclick="resetForm()" class="btn">
                    <span style="margin-right: 8px;">🔄</span> Buscar Otro Producto
                </button>
                <a href="entrada.php" class="btn">
                    <span style="margin-right: 8px;">📥</span> Ir a Entradas
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function calcularTotal() {
    const cantidad = parseInt(document.getElementById('cantidad').value) || 0;
    const precio = parseFloat(document.getElementById('precio_venta').value) || 0;
    const total = cantidad * precio;
    
    // Actualizar resumen
    document.getElementById('resumenCantidad').textContent = cantidad;
    document.getElementById('resumenPrecio').textContent = '$' + precio.toFixed(2);
    document.getElementById('resumenTotal').textContent = '$' + total.toFixed(2);
    
    return total;
}

function resetForm() {
    document.getElementById('codigo_barras').value = '';
    document.getElementById('codigo_barras').focus();
    location.reload();
}

// Enfocar automáticamente el campo de código de barras
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('codigo_barras');
    if (barcodeInput) {
        barcodeInput.focus();
        
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('button[name="buscar_producto"]').click();
            }
        });
    }
    
    // Calcular total inicial
    if (document.getElementById('cantidad')) {
        calcularTotal();
    }
});
</script>

<style>
.status-info {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.status-info .badge {
    background: #28a745;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
}

.status-info .badge:last-child {
    background: #17a2b8;
}

.product-found {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    margin-top: 2rem;
    border-left: 4px solid #007bff;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    animation: fadeIn 0.5s ease;
}

.product-found h3 {
    margin-top: 0;
    color: #007bff;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stock-available {
    font-weight: bold;
    color: #28a745;
    font-size: 1.2rem;
}

.resumen-venta {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border: 2px solid #e9ecef;
}

.resumen-venta h4 {
    margin-top: 0;
    color: #495057;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.resumen-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1rem;
}

.resumen-item {
    text-align: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.resumen-item.total {
    background: #e7f3ff;
    border: 2px solid #007bff;
}

.resumen-item label {
    display: block;
    font-weight: bold;
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.resumen-item span {
    color: #333;
    font-size: 1.1rem;
}

.total-venta {
    font-weight: bold;
    color: #28a745;
    font-size: 1.3rem;
}

.inventory-form {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #eee;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
}

@media (max-width: 768px) {
    .detail-grid,
    .resumen-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>