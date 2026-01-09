<?php
include '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

$mensaje = '';
$tipo_mensaje = '';
$porcentaje_ganancia = $functions->getPorcentajeGanancia();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Calcular precio de venta automáticamente si no se especifica
        $precio_compra = $_POST['precio_compra'] ?: 0;
        $precio_venta = $_POST['precio_venta'] ?: $functions->calcularPrecioVenta($precio_compra);
        
        $query = "INSERT INTO productos 
                 (codigo_barras, nombre, descripcion, precio_compra, precio_venta, stock_actual, stock_minimo) 
                 VALUES 
                 (:codigo_barras, :nombre, :descripcion, :precio_compra, :precio_venta, :stock_actual, :stock_minimo)";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':codigo_barras', $_POST['codigo_barras']);
        $stmt->bindParam(':nombre', $_POST['nombre']);
        $stmt->bindParam(':descripcion', $_POST['descripcion']);
        $stmt->bindParam(':precio_compra', $precio_compra);
        $stmt->bindParam(':precio_venta', $precio_venta);
        $stmt->bindParam(':stock_actual', $_POST['stock_actual']);
        $stmt->bindParam(':stock_minimo', $_POST['stock_minimo']);
        
        if ($stmt->execute()) {
            $mensaje = "Producto agregado exitosamente!";
            $tipo_mensaje = "success";
            
            // Limpiar campos excepto código de barras
            $_POST['nombre'] = '';
            $_POST['descripcion'] = '';
            $_POST['precio_compra'] = '';
            $_POST['precio_venta'] = '';
            $_POST['stock_actual'] = '0';
            $_POST['stock_minimo'] = '0';
        } else {
            $mensaje = "Error al agregar el producto.";
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
    <h2>Agregar Nuevo Producto</h2>
    
    <div class="config-info">
        <span class="badge">Porcentaje ganancia: <?php echo $porcentaje_ganancia; ?>%</span>
        <a href="../configuracion/porcentaje.php" class="btn-link">Cambiar</a>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" class="product-form" id="productForm">
        <div class="form-group">
            <label for="codigo_barras">Código de Barras:</label>
            <input type="text" id="codigo_barras" name="codigo_barras" class="barcode-input" 
                   placeholder="Escanee el código de barras" required
                   value="<?php echo isset($_POST['codigo_barras']) ? htmlspecialchars($_POST['codigo_barras']) : ''; ?>">
            <small>Use el lector de código de barras o ingréselo manualmente</small>
        </div>
        
        <div class="form-group">
            <label for="nombre">Nombre del Producto:</label>
            <input type="text" id="nombre" name="nombre" required
                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <textarea id="descripcion" name="descripcion" rows="3"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="precio_compra">Precio de Compra:</label>
                <div class="input-group">
                    <span class="input-addon">$</span>
                    <input type="number" id="precio_compra" name="precio_compra" 
                           step="0.01" min="0" 
                           value="<?php echo isset($_POST['precio_compra']) ? htmlspecialchars($_POST['precio_compra']) : ''; ?>">
                </div>
                <small>Precio al que compra el producto</small>
            </div>
            
            <div class="form-group">
                <label for="precio_venta">Precio de Venta:</label>
                <div class="input-group">
                    <span class="input-addon">$</span>
                    <input type="number" id="precio_venta" name="precio_venta" 
                           step="0.01" min="0"
                           value="<?php echo isset($_POST['precio_venta']) ? htmlspecialchars($_POST['precio_venta']) : ''; ?>">
                </div>
                <small>Se calculará automáticamente (+<?php echo $porcentaje_ganancia; ?>%)</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="stock_actual">Stock Actual:</label>
                <input type="number" id="stock_actual" name="stock_actual" 
                       value="<?php echo isset($_POST['stock_actual']) ? htmlspecialchars($_POST['stock_actual']) : '0'; ?>" min="0">
            </div>
            
            <div class="form-group">
                <label for="stock_minimo">Stock Mínimo:</label>
                <input type="number" id="stock_minimo" name="stock_minimo" 
                       value="<?php echo isset($_POST['stock_minimo']) ? htmlspecialchars($_POST['stock_minimo']) : '0'; ?>" min="0">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Guardar Producto</button>
            <button type="button" onclick="calcularPrecioVenta()" class="btn">Calcular Precio Venta</button>
            <a href="listar.php" class="btn">Cancelar</a>
        </div>
    </form>
</div>

<script>
// Obtener porcentaje desde PHP
const porcentajeGanancia = <?php echo $porcentaje_ganancia; ?>;

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
            <p>Compra: $${compra.toFixed(2)}</p>
            <p>Ganancia (${porcentajeGanancia}%): $${ganancia.toFixed(2)}</p>
            <p>Venta: $${venta.toFixed(2)}</p>
            <small>Ganancia real: ${porcentajeReal}%</small>
        </div>
    `;
}

// Calcular automáticamente cuando cambie el precio de compra
document.getElementById('precio_compra').addEventListener('blur', function() {
    const precioVentaInput = document.getElementById('precio_venta');
    // Solo calcular si el campo de venta está vacío
    if (!precioVentaInput.value) {
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

.calculo-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    margin: 1rem 0;
    border-left: 4px solid #28a745;
}

.calculo-detalle h4 {
    margin-top: 0;
    color: #333;
}

.calculo-detalle p {
    margin: 0.25rem 0;
    color: #555;
}
</style>

<?php include '../../includes/footer.php'; ?>