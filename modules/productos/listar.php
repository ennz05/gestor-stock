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

// Búsqueda
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';
$where = '';
$params = [];

if (!empty($buscar)) {
    $where = "WHERE codigo_barras LIKE :buscar OR nombre LIKE :buscar_nombre OR descripcion LIKE :buscar_desc";
    $params = [
        ':buscar' => "%$buscar%",
        ':buscar_nombre' => "%$buscar%",
        ':buscar_desc' => "%$buscar%"
    ];
}

// Consulta productos
$query = "SELECT * FROM productos $where ORDER BY nombre";
$stmt = $db->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_productos = count($productos);
$stock_bajo = 0;
$valor_total = 0;

foreach ($productos as $producto) {
    if ($producto['stock_actual'] <= $producto['stock_minimo']) {
        $stock_bajo++;
    }
    $valor_total += $producto['stock_actual'] * $producto['precio_compra'];
}

include '../../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2>📦 Gestión de Productos</h2>
        <a href="agregar.php" class="btn btn-success">
            <span style="margin-right: 5px;">➕</span> Agregar Nuevo Producto
        </a>
    </div>

    <!-- Estadísticas rápidas -->
    <div class="stats-bar">
        <div class="stat">
            <strong>Total Productos:</strong> <?php echo $total_productos; ?>
        </div>
        <div class="stat">
            <strong>Productos con Stock Bajo:</strong> 
            <span class="low-stock-count"><?php echo $stock_bajo; ?></span>
        </div>
        <div class="stat">
            <strong>Valor total inventario:</strong> 
            <span class="valor-total">$<?php echo number_format($valor_total, 2); ?></span>
        </div>
    </div>

    <!-- Barra de búsqueda -->
    <div class="search-bar">
        <form method="get" action="">
            <div class="search-group">
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" 
                       placeholder="Buscar por código de barras, nombre o descripción..." 
                       class="barcode-input" autofocus>
                <button type="submit" class="btn">
                    <span style="margin-right: 5px;">🔍</span> Buscar
                </button>
                <?php if (!empty($buscar)): ?>
                    <a href="listar.php" class="btn">
                        <span style="margin-right: 5px;">🗑️</span> Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lista de productos -->
    <div class="productos-table">
        <?php if (count($productos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Código Barras</th>
                        <th>Nombre</th>
                        <th>Precio Compra</th>
                        <th>Precio Venta</th>
                        <th>Stock</th>
                        <th>Stock Mínimo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): 
                        $estado = '';
                        $clase_estado = '';
                        
                        if ($producto['stock_actual'] == 0) {
                            $estado = 'Agotado';
                            $clase_estado = 'agotado';
                        } elseif ($producto['stock_actual'] <= $producto['stock_minimo']) {
                            $estado = 'Stock Bajo';
                            $clase_estado = 'bajo';
                        } else {
                            $estado = 'Normal';
                            $clase_estado = 'normal';
                        }
                    ?>
                    <tr>
                        <td>
                            <span class="codigo-barras">
                                <?php echo htmlspecialchars($producto['codigo_barras']); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                            <?php if ($producto['descripcion']): ?>
                                <br><small><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>$<?php echo number_format($producto['precio_compra'], 2); ?></td>
                        <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                        <td>
                            <span class="stock <?php echo $clase_estado; ?>">
                                <?php echo $producto['stock_actual']; ?>
                            </span>
                        </td>
                        <td><?php echo $producto['stock_minimo']; ?></td>
                        <td>
                            <span class="status-badge <?php echo $clase_estado; ?>">
                                <?php echo $estado; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="editar.php?id=<?php echo $producto['id']; ?>" class="btn btn-warning btn-small" title="Editar">
                                ✏️
                            </a>
                            <a href="eliminar.php?id=<?php echo $producto['id']; ?>" class="btn btn-danger btn-small" 
                               onclick="return confirm('¿Está seguro de eliminar este producto?')" title="Eliminar">
                                🗑️
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>No se encontraron productos.</p>
                <a href="agregar.php" class="btn btn-success">Agregar Primer Producto</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #eee;
}

.stats-bar {
    display: flex;
    justify-content: space-between;
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}

.stat {
    color: #495057;
    font-size: 0.95rem;
}

.low-stock-count {
    color: #dc3545;
    font-weight: bold;
}

.valor-total {
    color: #28a745;
    font-weight: bold;
}

.search-bar {
    margin-bottom: 2rem;
}

.search-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.search-group input {
    flex: 1;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 1rem;
}

.codigo-barras {
    font-family: 'Courier New', monospace;
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
}

.stock {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: bold;
    display: inline-block;
    min-width: 40px;
    text-align: center;
}

.stock.normal {
    background: #d4edda;
    color: #155724;
}

.stock.bajo {
    background: #fff3cd;
    color: #856404;
}

.stock.agotado {
    background: #f8d7da;
    color: #721c24;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    display: inline-block;
}

.status-badge.normal {
    background: #d4edda;
    color: #155724;
}

.status-badge.bajo {
    background: #fff3cd;
    color: #856404;
}

.status-badge.agotado {
    background: #f8d7da;
    color: #721c24;
}

.actions {
    display: flex;
    gap: 0.5rem;
}

.actions .btn-small {
    padding: 0.25rem;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.productos-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.productos-table table {
    width: 100%;
    border-collapse: collapse;
}

.productos-table th {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}

.productos-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #dee2e6;
}

.productos-table tr:hover {
    background: #f8f9fa;
}
</style>

<?php include '../../includes/footer.php'; ?>