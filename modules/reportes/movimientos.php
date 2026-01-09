<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Parámetros de filtro
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$tipo_movimiento = isset($_GET['tipo_movimiento']) ? $_GET['tipo_movimiento'] : 'todos';
$producto_id = isset($_GET['producto_id']) ? $_GET['producto_id'] : '';

// Construir consulta
$query = "SELECT m.*, p.nombre as producto_nombre, p.codigo_barras, p.precio_venta
          FROM movimientos_inventario m 
          JOIN productos p ON m.producto_id = p.id 
          WHERE DATE(m.fecha_movimiento) BETWEEN :fecha_inicio AND :fecha_fin";
$params = [
    ':fecha_inicio' => $fecha_inicio,
    ':fecha_fin' => $fecha_fin
];

if ($tipo_movimiento !== 'todos') {
    $query .= " AND m.tipo = :tipo";
    $params[':tipo'] = $tipo_movimiento;
}

if (!empty($producto_id)) {
    $query .= " AND m.producto_id = :producto_id";
    $params[':producto_id'] = $producto_id;
}

$query .= " ORDER BY m.fecha_movimiento DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de productos para filtro
$query_productos = "SELECT id, nombre, codigo_barras FROM productos ORDER BY nombre";
$stmt_productos = $db->prepare($query_productos);
$stmt_productos->execute();
$productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$total_entradas = 0;
$total_salidas = 0;
$valor_entradas = 0;
$valor_salidas = 0;

foreach ($movimientos as $mov) {
    $valor = $mov['cantidad'] * $mov['precio_venta'];
    
    if ($mov['tipo'] === 'entrada') {
        $total_entradas += $mov['cantidad'];
        $valor_entradas += $valor;
    } else {
        $total_salidas += $mov['cantidad'];
        $valor_salidas += $valor;
    }
}

$diferencia = $total_entradas - $total_salidas;
$valor_diferencia = $valor_entradas - $valor_salidas;

include '../../includes/header.php';
?>

<div class="container">
    <h2>📋 Reporte de Movimientos</h2>
    
    <!-- Filtros -->
    <div class="report-controls">
        <form method="get" action="" class="filters-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="fecha_inicio">📅 Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="fecha_fin">📅 Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="tipo_movimiento">📊 Tipo Movimiento:</label>
                    <select id="tipo_movimiento" name="tipo_movimiento">
                        <option value="todos" <?php echo $tipo_movimiento == 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="entrada" <?php echo $tipo_movimiento == 'entrada' ? 'selected' : ''; ?>>Entradas</option>
                        <option value="salida" <?php echo $tipo_movimiento == 'salida' ? 'selected' : ''; ?>>Salidas</option>
                        <option value="ajuste" <?php echo $tipo_movimiento == 'ajuste' ? 'selected' : ''; ?>>Ajustes</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="producto_id">📦 Producto:</label>
                    <select id="producto_id" name="producto_id">
                        <option value="">Todos los productos</option>
                        <?php foreach ($productos as $prod): ?>
                            <option value="<?php echo $prod['id']; ?>" 
                                <?php echo $producto_id == $prod['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prod['codigo_barras'] . ' - ' . $prod['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group actions">
                    <button type="submit" class="btn btn-primary">
                        <span style="margin-right: 5px;">🔍</span> Filtrar
                    </button>
                    <button type="button" onclick="exportToExcel()" class="btn btn-success">
                        <span style="margin-right: 5px;">📥</span> Exportar
                    </button>
                    <a href="stock.php" class="btn">
                        <span style="margin-right: 5px;">📊</span> Stock
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon">📈</div>
            <div class="stat-info">
                <h3><?php echo count($movimientos); ?></h3>
                <p>Total Movimientos</p>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">⬆️</div>
            <div class="stat-info">
                <h3><?php echo $total_entradas; ?></h3>
                <p>Total Entradas</p>
                <small>$<?php echo number_format($valor_entradas, 2); ?></small>
            </div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-icon">⬇️</div>
            <div class="stat-info">
                <h3><?php echo $total_salidas; ?></h3>
                <p>Total Salidas</p>
                <small>$<?php echo number_format($valor_salidas, 2); ?></small>
            </div>
        </div>
        
        <div class="stat-card <?php echo $diferencia >= 0 ? 'warning' : 'danger'; ?>">
            <div class="stat-icon">⚖️</div>
            <div class="stat-info">
                <h3><?php echo $diferencia; ?></h3>
                <p>Diferencia</p>
                <small>$<?php echo number_format($valor_diferencia, 2); ?></small>
            </div>
        </div>
    </div>
    
    <!-- Tabla de movimientos -->
    <div class="report-table" id="movimientosTable">
        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Producto</th>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                    <th>Motivo</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movimientos as $movimiento): 
                    $total = $movimiento['cantidad'] * $movimiento['precio_venta'];
                ?>
                <tr>
                    <td>
                        <?php echo date('d/m/Y', strtotime($movimiento['fecha_movimiento'])); ?>
                        <br>
                        <small><?php echo date('H:i:s', strtotime($movimiento['fecha_movimiento'])); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></strong>
                    </td>
                    <td>
                        <span class="codigo-barras">
                            <?php echo htmlspecialchars($movimiento['codigo_barras']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="movimiento-tipo <?php echo $movimiento['tipo']; ?>">
                            <?php 
                            $tipos = [
                                'entrada' => '📥 Entrada',
                                'salida' => '📤 Salida', 
                                'ajuste' => '📊 Ajuste'
                            ];
                            echo $tipos[$movimiento['tipo']]; 
                            ?>
                        </span>
                    </td>
                    <td class="<?php echo $movimiento['tipo'] === 'entrada' ? 'entrada' : 'salida'; ?>">
                        <?php echo $movimiento['cantidad']; ?>
                    </td>
                    <td>$<?php echo number_format($movimiento['precio_venta'], 2); ?></td>
                    <td>
                        <strong>$<?php echo number_format($total, 2); ?></strong>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($movimiento['motivo']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($movimiento['usuario']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($movimientos) === 0): ?>
            <div class="no-data">
                <p>📭 No se encontraron movimientos en el período seleccionado.</p>
                <a href="?fecha_inicio=<?php echo date('Y-m-d'); ?>&fecha_fin=<?php echo date('Y-m-d'); ?>" class="btn">
                    Ver movimientos de hoy
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Resumen -->
    <div class="report-summary">
        <h4>📝 Resumen del Período</h4>
        <div class="summary-grid">
            <div class="summary-item">
                <label>Período:</label>
                <span><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></span>
            </div>
            <div class="summary-item">
                <label>Fecha de generación:</label>
                <span><?php echo date('d/m/Y H:i:s'); ?></span>
            </div>
            <div class="summary-item">
                <label>Total movimientos:</label>
                <span><?php echo count($movimientos); ?></span>
            </div>
            <div class="summary-item">
                <label>Valor entradas:</label>
                <span class="entrada">$<?php echo number_format($valor_entradas, 2); ?></span>
            </div>
            <div class="summary-item">
                <label>Valor salidas:</label>
                <span class="salida">$<?php echo number_format($valor_salidas, 2); ?></span>
            </div>
            <div class="summary-item">
                <label>Balance:</label>
                <span class="<?php echo $valor_diferencia >= 0 ? 'entrada' : 'salida'; ?>">
                    $<?php echo number_format($valor_diferencia, 2); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('movimientosTable').getElementsByTagName('table')[0];
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
    const filename = `movimientos_${fecha}.csv`;
    
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

// Establecer fecha fin como hoy por defecto
document.addEventListener('DOMContentLoaded', function() {
    const fechaFinInput = document.getElementById('fecha_fin');
    if (fechaFinInput && !fechaFinInput.value) {
        fechaFinInput.value = new Date().toISOString().split('T')[0];
    }
});
</script>

<style>
.movimiento-tipo {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.movimiento-tipo.entrada {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.movimiento-tipo.salida {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.movimiento-tipo.ajuste {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.entrada {
    color: #28a745;
    font-weight: bold;
}

.salida {
    color: #dc3545;
    font-weight: bold;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
}

@media (max-width: 1200px) {
    .summary-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .report-table {
        overflow-x: auto;
    }
    
    .report-table table {
        min-width: 1000px;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>