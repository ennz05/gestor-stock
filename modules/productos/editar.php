<?php
include '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

$mensaje = '';
$tipo_mensaje = '';

// Obtener datos del producto
$producto = null;
if (isset($_GET['id'])) {
    $query = "SELECT * FROM productos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe el producto, redirigir
if (!$producto) {
    header("Location: listar.php");
    exit;
}

// Obtener configuración
$porcentaje_default = $functions->getPorcentajeGanancia();

// Procesar el formulario de edición
if ($_POST) {
    try {
        // Calcular precio de venta automáticamente si no se especifica o si cambió el precio de compra
        $precio_compra = $_POST['precio_compra'] ?: 0;
        $precio_venta = $_POST['precio_venta'] ?: $functions->calcularPrecioVenta($precio_compra);
        
        $query = "UPDATE productos SET 
                 codigo_barras = :codigo_barras,
                 nombre = :nombre,
                 descripcion = :descripcion,
                 precio_compra = :precio_compra,
                 precio_venta = :precio_venta,
                 stock_actual = :stock_actual,
                 stock_minimo = :stock_minimo,
                 fecha_actualizacion = CURRENT_TIMESTAMP
                 WHERE id = :id";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':codigo_barras', $_POST['codigo_barras']);
        $stmt->bindParam(':nombre', $_POST['nombre']);
        $stmt->bindParam(':descripcion', $_POST['descripcion']);
        $stmt->bindParam(':precio_compra', $precio_compra);
        $stmt->bindParam(':precio_venta', $precio_venta);
        $stmt->bindParam(':stock_actual', $_POST['stock_actual']);
        $stmt->bindParam(':stock_minimo', $_POST['stock_minimo']);
        $stmt->bindParam(':id', $_GET['id']);
        
        if ($stmt->execute()) {
            $mensaje = "Producto actualizado exitosamente!";
            $tipo_mensaje = "success";
            
            // Actualizar datos del producto en la variable
            $producto = array_merge($producto, $_POST);
            $producto['precio_compra'] = $precio_compra;
            $producto['precio_venta'] = $precio_venta;
        } else {
            $mensaje = "Error al actualizar el producto.";
            $tipo_mensaje = "error";
        }
    } catch(PDOException $exception) {
        if ($exception->getCode() == 23000) {
            $mensaje = "Error: El código de barras ya existe.";
        } else {
            $mensaje = "Error: " . $exception->getMessage();
        }
        $tipo_mensaje = "error";
    }
}
?>

<div class="container">
    <h2>Editar Producto</h2>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <div class="config-info">
        <span class="badge">Porcentaje ganancia: <?php echo $porcentaje_default; ?>%</span>
        <a href="../configuracion/porcentaje.php" class="btn-link">Cambiar</a>
    </div>
    
    <form method="post" action="" class="product-form" id="productForm">
        <div class="form-group">
            <label for="codigo_barras">Código de Barras:</label>
            <input type="text" id="codigo_barras" name="codigo_barras" class="barcode-input" 
                   value="<?php echo htmlspecialchars($producto['codigo_barras']); ?>" 
                   placeholder="Escanee el código de barras" required>
            <small>Use el lector de código de barras o ingréselo manualmente</small>
        </div>
        
        <div class="form-group">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" 
                   value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="precio_compra">Precio de Compra:</label>
                <div class="input-group">
                    <span class="input-addon">$</span>
                    <input type="number" id="precio_compra" name="precio_compra" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($producto['precio_compra']); ?>"
                           onchange="recalcularPrecioVenta()">
                </div>
                <small>Precio al que compra el producto</small>
            </div>
            
            <div class="form-group">
                <label for="precio_venta">Precio de Venta:</label>
                <div class="input-group">
                    <span class="input-addon">$</span>
                    <input type="number" id="precio_venta" name="precio_venta" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($producto['precio_venta']); ?>">
                </div>
                <small>
                    <button type="button" onclick="calcularPrecioVenta()" class="btn-small">
                        Calcular con <?php echo $porcentaje_default; ?>%
                    </button>
                </small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="stock_actual">Stock Actual:</label>
                <input type="number" id="stock_actual" name="stock_actual" min="0"
                       value="<?php echo htmlspecialchars($producto['stock_actual']); ?>">
            </div>
            
            <div class="form-group">
                <label for="stock_minimo">Stock Mínimo:</label>
                <input type="number" id="stock_minimo" name="stock_minimo" min="0"
                       value="<?php echo htmlspecialchars($producto['stock_minimo']); ?>">
            </div>
        </div>
        
        <div class="product-info">
            <h4>Información Adicional</h4>
            <div class="info-grid">
                <div class="info-item">
                    <label>Fecha de Creación:</label>
                    <span><?php echo date('d/m/Y H:i', strtotime($producto['fecha_creacion'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Última Actualización:</label>
                    <span><?php echo date('d/m/Y H:i', strtotime($producto['fecha_actualizacion'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Código Barras:</label>
                    <span class="codigo-barras"><?php echo htmlspecialchars($producto['codigo_barras']); ?></span>
                </div>
                <div class="info-item">
                    <label>ID Producto:</label>
                    <span>#<?php echo $producto['id']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Actualizar Producto</button>
            <button type="button" onclick="calcularPrecioVenta()" class="btn">Recalcular Precio</button>
            <a href="listar.php" class="btn">Cancelar</a>
            <a href="eliminar.php?id=<?php echo $producto['id']; ?>" class="btn btn-danger" 
               onclick="return confirm('¿Está seguro de eliminar este producto?')">Eliminar Producto</a>
        </div>
    </form>
</div>

<script>
// Obtener porcentaje desde PHP
const porcentajeGanancia = <?php echo $porcentaje_default; ?>;

function calcularPrecioVenta() {
    const precioCompra = parseFloat(document.getElementById('precio_compra').value) || 0;
    const precioVentaInput = document.getElementById('precio_venta');
    
    if (precioCompra > 0) {
        const precioVenta = precioCompra * (1 + (porcentajeGanancia / 100));
        precioVentaInput.value = precioVenta.toFixed(2);
        mostrarCalculo(precioCompra, precioVenta);
    } else {
        alert('Ingrese un precio de compra primero');
    }
}

function recalcularPrecioVenta() {
    if (confirm('¿Desea recalcular el precio de venta con el nuevo precio de compra?')) {
        calcularPrecioVenta();
    }
}

function mostrarCalculo(compra, venta) {
    // Crear o actualizar mensaje de cálculo
    let calculoDiv = document.getElementById('calculoInfo');
    if (!calculoDiv) {
        calculoDiv = document.createElement('div');
        calculoDiv.id = 'calculoInfo';
        calculoDiv.className = 'calculo-info';
        document.querySelector('.form-row').after(calculoDiv);
    }
    
    const ganancia = venta - compra;
    const porcentajeReal = ((ganancia / compra) * 100).toFixed(2);
    
    calculoDiv.innerHTML = `
        <div class="calculo-detalle">
            <h4>Detalle del cálculo:</h4>
            <div class="calculo-grid">
                <div class="calculo-item">
                    <label>Compra:</label>
                    <span>$${compra.toFixed(2)}</span>
                </div>
                <div class="calculo-item">
                    <label>Ganancia (${porcentajeGanancia}%):</label>
                    <span>$${ganancia.toFixed(2)}</span>
                </div>
                <div class="calculo-item">
                    <label>Venta:</label>
                    <span class="precio-final">$${venta.toFixed(2)}</span>
                </div>
                <div class="calculo-item">
                    <label>Ganancia real:</label>
                    <span>${porcentajeReal}%</span>
                </div>
            </div>
        </div>
    `;
}

// Calcular automáticamente al cargar si hay precio de compra
document.addEventListener('DOMContentLoaded', function() {
    const precioCompra = parseFloat(document.getElementById('precio_compra').value) || 0;
    const precioVenta = parseFloat(document.getElementById('precio_venta').value) || 0;
    
    if (precioCompra > 0 && precioVenta === 0) {
        calcularPrecioVenta();
    }
});
</script>

<style>
.config-info {
    background: #e7f3ff;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.badge {
    background: #007bff;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: bold;
}

.btn-link {
    color: #007bff;
    text-decoration: none;
    font-size: 0.9rem;
}

.btn-link:hover {
    text-decoration: underline;
}

.btn-small {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    margin-top: 0.25rem;
}

.btn-small:hover {
    background: #545b62;
}

.product-form {
    margin-top: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-addon {
    background: #e9ecef;
    padding: 0.5rem 1rem;
    border: 1px solid #ced4da;
    color: #495057;
}

.input-addon:first-child {
    border-radius: 4px 0 0 4px;
    border-right: none;
}

.input-group input {
    border-radius: 0 4px 4px 0;
    flex: 1;
}

.product-info {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border-left: 4px solid #007bff;
}

.product-info h4 {
    margin-top: 0;
    color: #333;
    margin-bottom: 1rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-item label {
    font-weight: bold;
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.info-item span {
    color: #333;
    font-size: 1rem;
}

.codigo-barras {
    font-family: monospace;
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
}

.calculo-info {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border-left: 4px solid #28a745;
}

.calculo-detalle h4 {
    margin-top: 0;
    color: #333;
    margin-bottom: 1rem;
}

.calculo-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.calculo-item {
    text-align: center;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.calculo-item label {
    display: block;
    font-weight: bold;
    color: #666;
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.calculo-item span {
    font-size: 1rem;
    color: #333;
}

.precio-final {
    font-weight: bold;
    color: #28a745;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .form-row,
    .info-grid,
    .calculo-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>