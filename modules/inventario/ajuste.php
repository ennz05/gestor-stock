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
    $query = "SELECT id, nombre, stock_actual FROM productos WHERE codigo_barras = :codigo_barras";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':codigo_barras', $_POST['codigo_barras']);
    $stmt->execute();
    $producto_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto_info) {
        $mensaje = "❌ Producto no encontrado. Verifique el código de barras.";
        $tipo_mensaje = "error";
    }
}

// Procesar ajuste de inventario
if (isset($_POST['registrar_ajuste'])) {
    try {
        $query_producto = "SELECT id, nombre, stock_actual FROM productos WHERE id = :producto_id";
        $stmt_producto = $db->prepare($query_producto);
        $stmt_producto->bindParam(':producto_id', $_POST['producto_id']);
        $stmt_producto->execute();
        $producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            $nuevo_stock = $_POST['nuevo_stock'];
            $diferencia = $nuevo_stock - $producto['stock_actual'];
            $tipo_ajuste = $diferencia >= 0 ? 'entrada' : 'salida';
            $cantidad_absoluta = abs($diferencia);
            
            // Solo registrar ajuste si hay diferencia
            if ($diferencia != 0) {
                // Registrar movimiento
                $query_movimiento = "INSERT INTO movimientos_inventario 
                                    (producto_id, tipo, cantidad, motivo, usuario) 
                                    VALUES 
                                    (:producto_id, :tipo, :cantidad, :motivo, :usuario)";
                $stmt_movimiento = $db->prepare($query_movimiento);
                $stmt_movimiento->bindParam(':producto_id', $_POST['producto_id']);
                $stmt_movimiento->bindParam(':tipo', $tipo_ajuste);
                $stmt_movimiento->bindParam(':cantidad', $cantidad_absoluta);
                $stmt_movimiento->bindParam(':motivo', $_POST['motivo']);
                $stmt_movimiento->bindParam(':usuario', $_POST['usuario']);
                
                // Actualizar stock
                $query_stock = "UPDATE productos SET stock_actual = :nuevo_stock WHERE id = :producto_id";
                $stmt_stock = $db->prepare($query_stock);
                $stmt_stock->bindParam(':nuevo_stock', $nuevo_stock);
                $stmt_stock->bindParam(':producto_id', $_POST['producto_id']);
                
                if ($stmt_movimiento->execute() && $stmt_stock->execute()) {
                    $mensaje = "✅ Ajuste registrado exitosamente!<br>";
                    $mensaje .= "Producto: " . $producto['nombre'] . "<br>";
                    $mensaje .= "Stock anterior: " . $producto['stock_actual'] . "<br>";
                    $mensaje .= "Stock nuevo: " . $nuevo_stock . "<br>";
                    $mensaje .= "<strong>Diferencia: " . ($diferencia >= 0 ? '+' : '') . $diferencia . "</strong>";
                    $tipo_mensaje = "success";
                    
                    // Resetear formulario
                    $_POST['codigo_barras'] = '';
                    $producto_info = null;
                }
            } else {
                $mensaje = "ℹ️ No hay diferencia entre el stock actual y el nuevo stock.";
                $tipo_mensaje = "info";
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
    <h2>📊 Ajuste de Inventario</h2>
    
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
            <small>Escanee el producto que desea ajustar</small>
        </div>
    </form>
    
    <!-- Formulario de ajuste (solo si se encontró producto) -->
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
                    <label>🆔 ID:</label>
                    <span>#<?php echo $producto_info['id']; ?></span>
                </div>
            </div>
        </div>
        
        <form method="post" action="" class="inventory-form" id="ajusteForm">
            <input type="hidden" name="producto_id" value="<?php echo $producto_info['id']; ?>">
            <input type="hidden" name="codigo_barras" value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
            
            <div class="form-group">
                <label for="nuevo_stock">📦 Nuevo Stock (Cantidad real):</label>
                <div class="input-group">
                    <input type="number" id="nuevo_stock" name="nuevo_stock" 
                           value="<?php echo $producto_info['stock_actual']; ?>" min="0" required
                           oninput="calcularDiferencia()">
                </div>
                <small>Ingrese la cantidad real después del conteo físico</small>
                
                <div class="diferencia-info" id="diferenciaContainer">
                    <!-- Se llenará con JavaScript -->
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="motivo">📝 Motivo del Ajuste:</label>
                    <select id="motivo" name="motivo" required>
                        <option value="">Seleccione un motivo</option>
                        <option value="inventario_fisico">📋 Inventario Físico</option>
                        <option value="conteo_ciclico">🔄 Conteo Cíclico</option>
                        <option value="error_sistema">💻 Error de Sistema</option>
                        <option value="mercancias_perdidas">❓ Mercancías Perdidas</option>
                        <option value="robo">🚨 Robo/Hurto</option>
                        <option value="merma">📉 Merma Natural</option>
                        <option value="otros">📌 Otros</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="usuario">👤 Responsable:</label>
                    <input type="text" id="usuario" name="usuario" 
                           value="<?php echo $functions->getUsuarioDefault(); ?>" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="registrar_ajuste" class="btn btn-warning">
                    <span style="margin-right: 8px;">📊</span> Registrar Ajuste
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
function calcularDiferencia() {
    const stockActual = <?php echo $producto_info ? $producto_info['stock_actual'] : 0; ?>;
    const nuevoStock = parseInt(document.getElementById('nuevo_stock').value) || 0;
    const diferencia = nuevoStock - stockActual;
    
    let clase = '';
    let icono = '';
    let texto = '';
    
    if (diferencia > 0) {
        clase = 'diferencia-positiva';
        icono = '📈';
        texto = 'Aumento';
    } else if (diferencia < 0) {
        clase = 'diferencia-negativa';
        icono = '📉';
        texto = 'Disminución';
    } else {
        clase = 'diferencia-cero';
        icono = '✅';
        texto = 'Sin cambios';
    }
    
    const diferenciaContainer = document.getElementById('diferenciaContainer');
    diferenciaContainer.innerHTML = `
        <div class="diferencia ${clase}">
            ${icono} <strong>Diferencia:</strong> ${diferencia >= 0 ? '+' : ''}${diferencia}
            <small>(${texto})</small>
        </div>
    `;
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
    
    // Calcular diferencia inicial
    if (document.getElementById('nuevo_stock')) {
        calcularDiferencia();
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
    border-left: 4px solid #ffc107;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    animation: fadeIn 0.5s ease;
}

.product-found h3 {
    margin-top: 0;
    color: #ffc107;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stock-current {
    font-weight: bold;
    color: #007bff;
    font-size: 1.2rem;
}

.diferencia-info {
    margin-top: 1rem;
}

.diferencia {
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    font-size: 1.1rem;
    margin: 1rem 0;
    animation: fadeIn 0.3s ease;
}

.diferencia-positiva {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.diferencia-negativa {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.diferencia-cero {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
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