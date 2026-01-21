<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar caja abierta
$caja_abierta = $functions->isCajaAbierta();

// Configurar paginación
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$filtro_cliente = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
$filtro_fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir consulta con filtros
$where = [];
$params = [];

if (!empty($filtro_cliente)) {
    $where[] = "v.cliente LIKE :cliente";
    $params[':cliente'] = "%$filtro_cliente%";
}

if (!empty($filtro_fecha)) {
    $where[] = "DATE(v.fecha) = :fecha";
    $params[':fecha'] = $filtro_fecha;
}

if (!empty($filtro_estado)) {
    $where[] = "v.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total de ventas para paginación
$query_total = "SELECT COUNT(*) as total FROM ventas v $where_clause";
$stmt_total = $db->prepare($query_total);
foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_ventas = $stmt_total->fetchColumn();
$total_paginas = ceil($total_ventas / $por_pagina);

// Obtener ventas
$query_ventas = "SELECT v.*, 
                        COUNT(vd.id) as total_items,
                        DATE_FORMAT(v.fecha, '%d/%m/%Y %H:%i') as fecha_formateada
                 FROM ventas v 
                 LEFT JOIN ventas_detalle vd ON v.id = vd.venta_id 
                 $where_clause 
                 GROUP BY v.id 
                 ORDER BY v.fecha DESC 
                 LIMIT :offset, :limit";

$stmt_ventas = $db->prepare($query_ventas);

// Vincular parámetros
foreach ($params as $key => $value) {
    $stmt_ventas->bindValue($key, $value);
}
$stmt_ventas->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_ventas->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
$stmt_ventas->execute();
$ventas = $stmt_ventas->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$query_estadisticas = "SELECT 
                        COUNT(*) as total_ventas,
                        SUM(total) as total_ingresos,
                        AVG(total) as promedio_venta,
                        MIN(fecha) as primera_venta,
                        MAX(fecha) as ultima_venta
                       FROM ventas";
$stmt_stats = $db->prepare($query_estadisticas);
$stmt_stats->execute();
$estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="container">
    <div class="ventas-header">
        <h2>📋 Historial de Ventas</h2>
        <div class="header-actions">
            <a href="nueva.php" class="btn btn-success">
                <i class="fas fa-cart-plus"></i> Nueva Venta
            </a>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-sync"></i> Actualizar
            </a>
        </div>
    </div>
    
    <!-- Estadísticas rápidas -->
    <div class="estadisticas-ventas">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <h4><?php echo number_format($estadisticas['total_ventas']); ?></h4>
                <p>Ventas Totales</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h4>$<?php echo number_format($estadisticas['total_ingresos'], 2); ?></h4>
                <p>Total Ingresos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <h4>$<?php echo number_format($estadisticas['promedio_venta'], 2); ?></h4>
                <p>Promedio por Venta</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar"></i></div>
            <div class="stat-info">
                <h4><?php echo $total_paginas; ?></h4>
                <p>Páginas</p>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-ventas">
        <h4>🔍 Filtros de Búsqueda</h4>
        <form method="get" action="" class="filtros-form">
            <div class="filtros-row">
                <div class="form-group">
                    <label for="cliente">Cliente:</label>
                    <input type="text" id="cliente" name="cliente" 
                           value="<?php echo htmlspecialchars($filtro_cliente); ?>"
                           placeholder="Buscar por cliente...">
                </div>
                
                <div class="form-group">
                    <label for="fecha">Fecha:</label>
                    <input type="date" id="fecha" name="fecha" 
                           value="<?php echo htmlspecialchars($filtro_fecha); ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="completada" <?php echo $filtro_estado == 'completada' ? 'selected' : ''; ?>>Completada</option>
                        <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="cancelada" <?php echo $filtro_estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Lista de ventas -->
    <div class="ventas-lista">
        <?php if (count($ventas) > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td>#<?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo $venta['fecha_formateada']; ?></td>
                        <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                        <td><?php echo $venta['total_items']; ?></td>
                        <td class="total-venta">$<?php echo number_format($venta['total'], 2); ?></td>
                        <td>
                            <?php
                            $badge_class = '';
                            switch ($venta['estado']) {
                                case 'completada': $badge_class = 'success'; break;
                                case 'pendiente': $badge_class = 'warning'; break;
                                case 'cancelada': $badge_class = 'danger'; break;
                            }
                            ?>
                            <span class="badge badge-<?php echo $badge_class; ?>">
                                <?php echo ucfirst($venta['estado']); ?>
                            </span>
                        </td>
                        <td class="acciones">
                            <a href="ticket.php?id=<?php echo $venta['id']; ?>" 
                               target="_blank" 
                               class="btn btn-info btn-sm"
                               title="Ver Ticket">
                                <i class="fas fa-receipt"></i>
                            </a>
                            
                            <?php if ($venta['estado'] == 'pendiente'): ?>
                            <button onclick="completarVenta(<?php echo $venta['id']; ?>)" 
                                    class="btn btn-success btn-sm"
                                    title="Marcar como Completada">
                                <i class="fas fa-check"></i>
                            </button>
                            
                            <button onclick="cancelarVenta(<?php echo $venta['id']; ?>)" 
                                    class="btn btn-danger btn-sm"
                                    title="Cancelar Venta">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <nav>
                <ul class="pagination">
                    <?php if ($pagina > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $pagina-1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : ''; ?>">
                            &laquo; Anterior
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina - 2);
                    $fin = min($total_paginas, $pagina + 2);
                    
                    for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $pagina+1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['pagina' => ''])) : ''; ?>">
                            Siguiente &raquo;
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="paginacion-info">
                Mostrando <?php echo count($ventas); ?> de <?php echo $total_ventas; ?> ventas
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-ventas">
            <i class="fas fa-file-invoice fa-3x"></i>
            <h4>No hay ventas registradas</h4>
            <p>Comienza creando tu primera venta múltiple</p>
            <a href="nueva.php" class="btn btn-success btn-lg">
                <i class="fas fa-cart-plus"></i> Crear Primera Venta
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function completarVenta(id) {
    if (confirm('¿Marcar esta venta como COMPLETADA?')) {
        fetch('acciones.php?action=completar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Venta completada correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error de conexión');
        });
    }
}

function cancelarVenta(id) {
    if (confirm('¿Cancelar esta venta? Esta acción restaurará el stock de los productos.')) {
        fetch('acciones.php?action=cancelar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Venta cancelada correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error de conexión');
        });
    }
}

// Exportar a CSV
function exportarCSV() {
    let csv = [];
    let rows = document.querySelectorAll('table tr');
    
    for (let row of rows) {
        let cells = row.querySelectorAll('th, td');
        let rowData = [];
        
        for (let cell of cells) {
            if (!cell.classList.contains('acciones')) {
                let data = cell.innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                data = data.replace(/"/g, '""');
                rowData.push('"' + data + '"');
            }
        }
        
        csv.push(rowData.join(','));
    }
    
    let csvContent = csv.join('\n');
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, 'ventas.csv');
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = 'ventas_' + new Date().toISOString().slice(0,10) + '.csv';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>

<style>
.ventas-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #eee;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.estadisticas-ventas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2rem;
    color: #007bff;
    background: #e7f3ff;
    padding: 1rem;
    border-radius: 10px;
}

.stat-info h4 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.filtros-ventas {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.filtros-ventas h4 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #333;
}

.filtros-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filtros-form .form-group {
    display: flex;
    flex-direction: column;
}

.filtros-form label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

.filtros-form input,
.filtros-form select {
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 1rem;
}

.ventas-lista {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.total-venta {
    font-weight: bold;
    color: #28a745;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.acciones {
    display: flex;
    gap: 0.5rem;
}

.paginacion {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
}

.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 0.5rem;
}

.page-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    color: #007bff;
    text-decoration: none;
    background: white;
}

.page-link:hover {
    background: #f8f9fa;
}

.page-item.active .page-link {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.paginacion-info {
    color: #6c757d;
    font-size: 0.9rem;
}

.no-ventas {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.no-ventas i {
    margin-bottom: 1rem;
    color: #dee2e6;
}

.no-ventas h4 {
    margin-bottom: 0.5rem;
    color: #495057;
}

.no-ventas p {
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .ventas-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .filtros-row {
        grid-template-columns: 1fr;
    }
    
    .table {
        display: block;
        overflow-x: auto;
    }
    
    .paginacion {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>