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

// Procesar entrada de inventario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['buscar_producto'])) {
        // Buscar producto por código de barras
        $query = "SELECT id, nombre, stock_actual, precio_compra, precio_venta 
                  FROM productos WHERE codigo_barras = :codigo_barras";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':codigo_barras', $_POST['codigo_barras']);
        $stmt->execute();
        $producto_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto_info) {
            $mensaje = "❌ Producto no encontrado. Verifique el código de barras.";
            $tipo_mensaje = "error";
        }
    } elseif (isset($_POST['registrar_entrada'])) {
        try {
            $query_producto = "SELECT id, nombre, stock_actual FROM productos WHERE id = :producto_id";
            $stmt_producto = $db->prepare($query_producto);
            $stmt_producto->bindParam(':producto_id', $_POST['producto_id']);
            $stmt_producto->execute();
            $producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                // Registrar movimiento
                $query_movimiento = "INSERT INTO movimientos_inventario 
                                    (producto_id, tipo, cantidad, motivo, usuario) 
                                    VALUES 
                                    (:producto_id, 'entrada', :cantidad, :motivo, :usuario)";
                $stmt_movimiento = $db->prepare($query_movimiento);
                $stmt_movimiento->bindParam(':producto_id', $_POST['producto_id']);
                $stmt_movimiento->bindParam(':cantidad', $_POST['cantidad']);
                $stmt_movimiento->bindParam(':motivo', $_POST['motivo']);
                $stmt_movimiento->bindParam(':usuario', $_POST['usuario']);
                
                // Actualizar stock
                $query_stock = "UPDATE productos SET stock_actual = stock_actual + :cantidad WHERE id = :producto_id";
                $stmt_stock = $db->prepare($query_stock);
                $stmt_stock->bindParam(':cantidad', $_POST['cantidad']);
                $stmt_stock->bindParam(':producto_id', $_POST['producto_id']);
                
                if ($stmt_movimiento->execute() && $stmt_stock->execute()) {
                    $mensaje = "✅ Entrada registrada exitosamente para: " . $producto['nombre'];
                    $tipo_mensaje = "success";
                    
                    // Calcular nuevo total
                    $nuevo_total = $producto['stock_actual'] + $_POST['cantidad'];
                    
                    // Resetear formulario pero mantener código de barras
                    $_POST['cantidad'] = '';
                    $_POST['motivo'] = '';
                    $_POST['usuario'] = $functions->getUsuarioDefault();
                    $producto_info = null;
                    
                    $mensaje .= ". Stock anterior: " . $producto['stock_actual'] . " | Stock nuevo: " . $nuevo_total;
                }
            } else {
                $mensaje = "❌ Producto no encontrado.";
                $tipo_mensaje = "error";
            }
        } catch(PDOException $exception) {
            $mensaje = "❌ Error: " . $exception->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <h2>📥 Entrada de Inventario</h2>
    
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
            <small>Escanee el código del producto o ingréselo manualmente</small>
        </div>
    </form>
    
    <!-- Formulario de entrada (solo si se encontró producto) -->
    <?php if ($producto_info): ?>
    <div class="product-found">
        <h3>✅ Producto Encontrado</h3>
        <div class="product-details">
            <div class="detail-grid">
                <div class="detail-item">
                    <label>🏷️ Producto:</label>
                    <span><?php echo htmlspecialchars($producto_info['nombre']); ?></span>
                </div>
                <div class="detail-item">
                    <label>📦 Stock Actual:</label>
                    <span class="stock-current"><?php echo $producto_info['stock_actual']; ?></span>
                </div>
                <div class="detail-item">
                    <label>💰 Precio Compra:</label>
                    <span>$<?php echo number_format($producto_info['precio_compra'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <label>🏷️ Precio Venta:</label>
                    <span>$<?php echo number_format($producto_info['precio_venta'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <form method="post" action="" class="inventory-form">
            <input type="hidden" name="producto_id" value="<?php echo $producto_info['id']; ?>">
            <input type="hidden" name="codigo_barras" value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="cantidad">📦 Cantidad a Ingresar:</label>
                    <input type="number" id="cantidad" name="cantidad" min="1" value="1" required>
                    <small>Ingrese la cantidad que está recibiendo</small>
                </div>
                
                <div class="form-group">
                    <label for="motivo">📝 Motivo:</label>
                    <select id="motivo" name="motivo" required>
                        <option value="">Seleccione un motivo</option>
                        <option value="compra">🛒 Compra</option>
                        <option value="devolucion">↩️ Devolución Cliente</option>
                        <option value="transferencia">🔄 Transferencia Entrante</option>
                        <option value="produccion">🏭 Producción</option>
                        <option value="ajuste_positivo">📈 Ajuste Positivo</option>
                        <option value="otros">📌 Otros</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="usuario">👤 Responsable:</label>
                <input type="text" id="usuario" name="usuario" 
                       value="<?php echo $functions->getUsuarioDefault(); ?>" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="registrar_entrada" class="btn btn-success">
                    <span style="margin-right: 8px;">📥</span> Registrar Entrada
                </button>
                <button type="button" onclick="resetForm()" class="btn">
                    <span style="margin-right: 8px;">🔄</span> Buscar Otro Producto
                </button>
                <a href="salida.php" class="btn">
                    <span style="margin-right: 8px;">📤</span> Ir a Salidas
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
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

.search-container {
    display: flex;
    gap: 0.5rem;
}

.search-container input {
    flex: 1;
}

.product-found {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    margin-top: 2rem;
    border-left: 4px solid #28a745;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.product-found h3 {
    margin-top: 0;
    color: #28a745;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.detail-item label {
    display: block;
    font-weight: bold;
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.detail-item span {
    color: #333;
    font-size: 1rem;
}

.stock-current {
    font-weight: bold;
    color: #007bff;
    font-size: 1.2rem;
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
    .detail-grid {
        grid-template-columns: 1fr 1fr;
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