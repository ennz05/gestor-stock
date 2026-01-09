<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Parámetros de filtro
$filtro_stock = isset($_GET['filtro_stock']) ? $_GET['filtro_stock'] : 'todos';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'nombre';

// Construir consulta base
$query = "SELECT * FROM productos WHERE 1=1";
$params = [];

// Aplicar filtros
if ($filtro_stock === 'bajo') {
    $query .= " AND stock_actual <= stock_minimo AND stock_actual > 0";
} elseif ($filtro_stock === 'agotado') {
    $query .= " AND stock_actual = 0";
} elseif ($filtro_stock === 'sobrestock') {
    $query .= " AND stock_actual > stock_minimo * 2";
} elseif ($filtro_stock === 'minimo') {
    $query .= " AND stock_minimo > 0";
}

// Aplicar orden
switch ($orden) {
    case 'nombre':
        $query .= " ORDER BY nombre";
        break;
    case 'stock_asc':
        $query .= " ORDER BY stock_actual";
        break;
    case 'stock_desc':
        $query .= " ORDER BY stock_actual DESC";
        break;
    case 'codigo':
        $query .= " ORDER BY codigo_barras";
        break;
    case 'precio_asc':
        $query .= " ORDER BY precio_venta";
        break;
    case 'precio_desc':
        $query .= " ORDER BY precio_venta DESC";
        break;
    default:
        $query .= " ORDER BY nombre";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_productos = count($productos);
$stock_bajo = 0;
$stock_agotado = 0;
$valor_total_compra = 0;
$valor_total_venta = 0;

foreach ($productos as $producto) {
    if ($producto['stock_actual'] <= $producto['stock_minimo'] && $producto['stock_actual'] > 0) {
        $stock_bajo++;
    }
    if ($producto['stock_actual'] == 0) {
        $stock_agotado++;
    }
    $valor_total_compra += $producto['stock_actual'] * $producto['precio_compra'];
    $valor_total_venta += $producto['stock_actual'] * $producto['precio_venta'];
}

include '../../includes/header.php';
?>

<div class="container">
    <h2>📊 Reporte de Stock</h2>
    
    <!-- Filtros y controles -->
    <div class="report-controls">
        <form method="get" action="" class="filters-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filtro_stock">📋 Filtrar por Stock:</label>
                    <select id="filtro_stock" name="filtro_stock" onchange="this.form.submit()">
                        <option value="todos" <?php echo $filtro_stock == 'todos' ? 'selected' : ''; ?>>Todos los Productos</option>
                        <option value="bajo" <?php echo $filtro_stock == 'bajo' ? 'selected' : ''; ?>>⚠️ Stock Bajo</option>
                        <option value="agotado" <?php echo $filtro_stock == 'agotado' ? 'selected' : ''; ?>>❌ Stock Agotado</option>
                        <option value="sobrestock" <?php echo $filtro_stock == 'sobrestock' ? 'selected' : ''; ?>>📈 Sobrestock</option>
                        <option value="minimo" <?php echo $filtro_stock == 'minimo' ? 'selected' : ''; ?>>🎯 Con Stock Mínimo</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="orden">🔢 Ordenar por:</label>
                    <select id="orden" name="orden" onchange="this.form.submit()">
                        <option value="nombre" <?php echo $orden == 'nombre' ? 'selected' : ''; ?>>Nombre (A-Z)</option>
                        <option value="codigo" <?php echo $orden == 'codigo' ? 'selected' : ''; ?>>Código Barras</option>
                        <option value="stock_asc" <?php echo $orden == 'stock_asc' ? 'selected' : ''; ?>>Stock (Menor a Mayor)</option>
                        <option value="stock_desc" <?php echo $orden == 'stock_desc' ? 'selected' : ''; ?>>Stock (Mayor a Menor)</option>
                        <option value="precio_asc" <?php echo $orden == 'precio_asc' ? 'selected' : ''; ?>>Precio (Menor a Mayor)</option>
                        <option value="precio_desc" <?php echo $orden == 'precio_desc' ? 'selected' : ''; ?>>Precio (Mayor a Menor)</option>
                    </select>
                </div>
                
                <div class="filter-group actions">
                    <button type="button" onclick="window.print()" class="btn btn-info">
                        <span style="margin-right: 5px;">🖨️</span> Imprimir
                    </button>
                    <button type="button" onclick="exportToExcel()" class="btn btn-success">
                        <span style="margin-right: 5px;">📥</span> Exportar Excel
                    </button>
                    <a href="movimientos.php" class="btn">
                        <span style="margin-right: 5px;">📋</span> Movimientos
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-info">
                <h3><?php echo $total_productos; ?></h3>
                <p>Productos</p>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">⚠️</div>
            <div class="stat-info">
                <h3><?php echo $stock_bajo; ?></h3>
                <p>Stock Bajo</p>
            </div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-icon">❌</div>
            <div class="stat-info">
                <h3><?php echo $stock_agotado; ?></h3>
                <p>Stock Agotado</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">💰</div>
            <div class="stat-info">
                <h3>$<?php echo number_format($valor_total_venta, 2); ?></h3>
                <p>Valor Total</p>
            </div>
        </div>
    </div>
    
    <!-- Tabla de productos -->
    <div class="report-table" id="reportTable">
        <table>
            <thead>
                <tr>
                    <th>Código Barras</th>
                    <th>Producto</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Precio Compra</th>
                    <th>Precio Venta</th>
                    <th>Valor Compra</th>
                    <th>Valor Venta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): 
                    $valor_compra = $producto['stock_actual'] * $producto['precio_compra'];
                    $valor_venta = $producto['stock_actual'] * $producto['precio_venta'];
                    $estado = '';
                    $clase_estado = '';
                    $icono = '';
                    
                    if ($producto['stock_actual'] == 0) {
                        $estado = 'Agotado';
                        $clase_estado = 'agotado';
                        $icono = '❌';
                    } elseif ($producto['stock_actual'] <= $producto['stock_minimo']) {
                        $estado = 'Stock Bajo';
                        $clase_estado = 'bajo';
                        $icono = '⚠️';
                    } else {
                        $estado = 'Normal';
                        $clase_estado = 'normal';
                        $icono = '✅';
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
                            <br><small><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 40)); ?>...</small>
                        <?php endif; ?>
                    </td>
                    <td class="stock-cell <?php echo $clase_estado; ?>">
                        <?php echo $producto['stock_actual']; ?>
                    </td>
                    <td><?php echo $producto['stock_minimo']; ?></td>
                    <td>$<?php echo number_format($producto['precio_compra'], 2); ?></td>
                    <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                    <td>$<?php echo number_format($valor_compra, 2); ?></td>
                    <td>
                        <strong>$<?php echo number_format($valor_venta, 2); ?></strong>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $clase_estado; ?>">
                            <?php echo $icono; ?> <?php echo $estado; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($productos) === 0): ?>
            <div class="no-data">
                <p>📭 No se encontraron productos con los filtros seleccionados.</p>
                <a href="?filtro_stock=todos" class="btn">Ver todos los productos</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Resumen -->
    <div class="report-summary">
        <h4>📝 Resumen del Reporte</h4>
        <div class="summary-grid">
            <div class="summary-item">
                <label>Productos mostrados:</label>
                <span><?php echo count($productos); ?> de <?php echo $total_productos; ?></span>
            </div>
            <div class="summary-item">
                <label>Fecha de generación:</label>
                <span><?php echo date('d/m/Y H:i:s'); ?></span>
            </div>
            <div class="summary-item">
                <label>Valor total compra:</label>
                <span>$<?php echo number_format($valor_total_compra, 2); ?></span>
            </div>
            <div class="summary-item">
                <label>Valor total venta:</label>
                <span>$<?php echo number_format($valor_total_venta, 2); ?></span>
            </div>
            <div class="summary-item">
                <label>Ganancia potencial:</label>
                <span class="ganancia-potencial">$<?php echo number_format($valor_total_venta - $valor_total_compra, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('reportTable').getElementsByTagName('table')[0];
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        for (let cell of cells) {
            let data = cell.innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            rowData.push('"' + data + '"');
        }
        
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    const fecha = new Date().toISOString().slice(0,10);
    const filename = `stock_report_${fecha}.csv`;
    
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

// Estilos para impresión
window.addEventListener('beforeprint', function() {
    document.querySelector('.report-controls').style.display = 'none';
    document.querySelector('.stats-cards').style.display = 'none';
    document.querySelector('.report-summary').style.display = 'none';
});

window.addEventListener('afterprint', function() {
    document.querySelector('.report-controls').style.display = 'block';
    document.querySelector('.stats-cards').style.display = 'grid';
    document.querySelector('.report-summary').style.display = 'block';
});
</script>

<style>
.report-controls {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

.filter-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group select {
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    background: white;
}

.filter-group.actions {
    display: flex;
    flex-direction: row;
    gap: 0.5rem;
    align-items: flex-end;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid #007bff;
}

.stat-card.warning {
    border-left-color: #ffc107;
}

.stat-card.danger {
    border-left-color: #dc3545;
}

.stat-card.success {
    border-left-color: #28a745;
}

.stat-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.stat-card.warning .stat-icon {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
}

.stat-card.danger .stat-icon {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.stat-card.success .stat-icon {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
}

.stat-info h3 {
    font-size: 1.5rem;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.stat-info p {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0;
}

.report-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.report-table table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    position: sticky;
    top: 0;
}

.report-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e9ecef;
}

.report-table tr:hover {
    background: #f8f9fa;
}

.stock-cell {
    font-weight: bold;
    text-align: center;
    min-width: 80px;
}

.stock-cell.normal {
    color: #28a745;
}

.stock-cell.bajo {
    color: #ffc107;
}

.stock-cell.agotado {
    color: #dc3545;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
}

.status-badge.normal {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-badge.bajo {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-badge.agotado {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.report-summary {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 2rem;
    border: 1px solid #e9ecef;
}

.report-summary h4 {
    margin-top: 0;
    color: #495057;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
}

.summary-item label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.ganancia-potencial {
    color: #28a745;
    font-weight: bold;
}

@media print {
    .report-controls,
    .stats-cards,
    .report-summary,
    .filter-group.actions {
        display: none !important;
    }
    
    .report-table {
        box-shadow: none;
        border: 1px solid #ccc;
    }
    
    body {
        font-size: 12px;
    }
    
    .report-table th {
        background: #ccc !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
    }
}

@media (max-width: 1024px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-cards {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .report-table {
        overflow-x: auto;
    }
    
    .report-table table {
        min-width: 800px;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>