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

$mensaje = '';
$tipo_mensaje = '';

// Obtener configuración actual
$query_config = "SELECT * FROM configuracion WHERE clave IN ('porcentaje_ganancia', 'usuario_default')";
$stmt_config = $db->prepare($query_config);
$stmt_config->execute();
$configs = $stmt_config->fetchAll(PDO::FETCH_ASSOC);

$config_data = [];
foreach ($configs as $config) {
    $config_data[$config['clave']] = $config;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
    try {
        // Actualizar porcentaje de ganancia
        if (isset($config_data['porcentaje_ganancia'])) {
            $query = "UPDATE configuracion SET valor = :valor WHERE clave = 'porcentaje_ganancia'";
        } else {
            $query = "INSERT INTO configuracion (clave, valor, descripcion) 
                      VALUES ('porcentaje_ganancia', :valor, 'Porcentaje de ganancia aplicado al precio de compra para calcular precio de venta')";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':valor', $_POST['porcentaje_ganancia']);
        $stmt->execute();
        
        // Actualizar usuario por defecto
        if (isset($config_data['usuario_default'])) {
            $query = "UPDATE configuracion SET valor = :valor WHERE clave = 'usuario_default'";
        } else {
            $query = "INSERT INTO configuracion (clave, valor, descripcion) 
                      VALUES ('usuario_default', :valor, 'Usuario por defecto para movimientos')";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':valor', $_POST['usuario_default']);
        $stmt->execute();
        
        $mensaje = "✅ Configuración actualizada exitosamente!";
        $tipo_mensaje = "success";
        
        // Actualizar datos en variable
        $config_data['porcentaje_ganancia']['valor'] = $_POST['porcentaje_ganancia'];
        $config_data['usuario_default']['valor'] = $_POST['usuario_default'];
        
    } catch(PDOException $exception) {
        $mensaje = "❌ Error al actualizar la configuración: " . $exception->getMessage();
        $tipo_mensaje = "error";
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <h2>⚙️ Configuración del Sistema</h2>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <div class="config-grid">
        <div class="config-card">
            <h3>📈 Configurar Porcentaje de Ganancia</h3>
            
            <div class="current-config">
                <div class="config-item">
                    <label>Porcentaje actual:</label>
                    <span class="config-value">
                        <?php echo isset($config_data['porcentaje_ganancia']) ? $config_data['porcentaje_ganancia']['valor'] : '10'; ?>%
                    </span>
                </div>
                <div class="config-description">
                    <p>Este porcentaje se aplica automáticamente al precio de compra para calcular el precio de venta cuando se agregan o editan productos.</p>
                </div>
            </div>
            
            <form method="post" action="" class="config-form">
                <div class="form-group">
                    <label for="porcentaje_ganancia">Nuevo Porcentaje de Ganancia:</label>
                    <div class="input-group">
                        <input type="number" id="porcentaje_ganancia" name="porcentaje_ganancia" 
                               step="0.1" min="0" max="500"
                               value="<?php echo isset($config_data['porcentaje_ganancia']) ? $config_data['porcentaje_ganancia']['valor'] : '10'; ?>" 
                               required>
                        <span class="input-addon">%</span>
                    </div>
                    <small>Ingrese el porcentaje de ganancia que desea aplicar</small>
                </div>
                
                <div class="example-box">
                    <h4>📝 Ejemplo de cálculo:</h4>
                    <div class="example-grid">
                        <div class="example-item">
                            <label>Precio de compra:</label>
                            <span>$100.00</span>
                        </div>
                        <div class="example-item">
                            <label>Porcentaje:</label>
                            <span id="ejemploPorcentaje">
                                <?php echo isset($config_data['porcentaje_ganancia']) ? $config_data['porcentaje_ganancia']['valor'] : '10'; ?>%
                            </span>
                        </div>
                        <div class="example-item">
                            <label>Ganancia:</label>
                            <span id="ejemploGanancia">
                                $<?php 
                                $porcentaje = isset($config_data['porcentaje_ganancia']) ? $config_data['porcentaje_ganancia']['valor'] : 10;
                                echo number_format(100 * ($porcentaje / 100), 2); 
                                ?>
                            </span>
                        </div>
                        <div class="example-item total">
                            <label>Precio de venta:</label>
                            <span id="ejemploVenta" class="precio-ejemplo">
                                $<?php 
                                $porcentaje = isset($config_data['porcentaje_ganancia']) ? $config_data['porcentaje_ganancia']['valor'] : 10;
                                echo number_format(100 * (1 + ($porcentaje / 100)), 2); 
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="usuario_default">👤 Usuario por Defecto:</label>
                    <input type="text" id="usuario_default" name="usuario_default" 
                           value="<?php echo isset($config_data['usuario_default']) ? $config_data['usuario_default']['valor'] : 'Administrador'; ?>" 
                           required>
                    <small>Usuario que aparecerá por defecto en los movimientos de inventario</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="actualizar" class="btn btn-success">
                        <span style="margin-right: 8px;">💾</span> Guardar Configuración
                    </button>
                    <a href="../productos/listar.php" class="btn">
                        <span style="margin-right: 8px;">📦</span> Ir a Productos
                    </a>
                </div>
            </form>
        </div>
        
        <div class="config-card">
            <h3>📊 Información del Sistema</h3>
            
            <div class="system-info">
                <div class="info-item">
                    <label>🔄 Estado de Caja:</label>
                    <span class="<?php echo $caja_abierta ? 'status-open' : 'status-closed'; ?>">
                        <?php echo $caja_abierta ? '💰 Abierta' : '🔒 Cerrada'; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <label>👤 Usuario actual:</label>
                    <span><?php echo isset($config_data['usuario_default']) ? $config_data['usuario_default']['valor'] : 'Administrador'; ?></span>
                </div>
                
                <div class="info-item">
                    <label>📦 Total productos:</label>
                    <span>
                        <?php 
                        $query = "SELECT COUNT(*) as total FROM productos";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo $result['total'];
                        ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <label>📊 Total movimientos:</label>
                    <span>
                        <?php 
                        $query = "SELECT COUNT(*) as total FROM movimientos_inventario";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo $result['total'];
                        ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <label>💾 Último backup:</label>
                    <span>
                        <?php
                        $backup_dir = '../../backups/backups/';
                        if (file_exists($backup_dir)) {
                            $files = glob($backup_dir . '*.sql');
                            if (!empty($files)) {
                                $latest_file = max($files);
                                echo date('d/m/Y H:i', filemtime($latest_file));
                            } else {
                                echo 'No hay backups';
                            }
                        } else {
                            echo 'No hay backups';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <label>🖥️ Versión PHP:</label>
                    <span><?php echo phpversion(); ?></span>
                </div>
                
                <div class="info-item">
                    <label>🗄️ Base de datos:</label>
                    <span>MySQL</span>
                </div>
            </div>
            
            <div class="system-actions">
                <h4>🔧 Acciones del Sistema</h4>
                <div class="action-buttons">
                    <a href="../caja/apertura.php" class="btn-action">
                        <span class="icon">💰</span>
                        <span>Abrir Caja</span>
                    </a>
                    <a href="../../backups/backup.php?action=backup" class="btn-action" 
                       onclick="return confirm('¿Generar backup de la base de datos?')">
                        <span class="icon">💾</span>
                        <span>Backup</span>
                    </a>
                    <a href="../../index.php" class="btn-action">
                        <span class="icon">🏠</span>
                        <span>Inicio</span>
                    </a>
                    <a href="../reportes/stock.php" class="btn-action">
                        <span class="icon">📊</span>
                        <span>Reportes</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Actualizar ejemplo en tiempo real
document.getElementById('porcentaje_ganancia').addEventListener('input', function() {
    const porcentaje = parseFloat(this.value) || 0;
    const precioCompra = 100;
    const ganancia = precioCompra * (porcentaje / 100);
    const precioVenta = precioCompra + ganancia;
    
    document.getElementById('ejemploPorcentaje').textContent = porcentaje + '%';
    document.getElementById('ejemploGanancia').textContent = '$' + ganancia.toFixed(2);
    document.getElementById('ejemploVenta').textContent = '$' + precioVenta.toFixed(2);
});
</script>

<style>
.config-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

.config-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.config-card h3 {
    margin-top: 0;
    color: #2c3e50;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.current-config {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.config-item label {
    font-weight: 600;
    color: #495057;
}

.config-value {
    font-weight: bold;
    color: #007bff;
    font-size: 1.2rem;
}

.config-description {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
    color: #6c757d;
    font-size: 0.9rem;
}

.example-box {
    background: #e7f3ff;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    border-left: 4px solid #007bff;
}

.example-box h4 {
    margin-top: 0;
    color: #007bff;
    margin-bottom: 1rem;
}

.example-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.example-item {
    text-align: center;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
}

.example-item.total {
    background: #d4edda;
    border: 2px solid #28a745;
}

.example-item label {
    display: block;
    font-weight: bold;
    color: #495057;
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.precio-ejemplo {
    font-weight: bold;
    color: #28a745;
    font-size: 1.1rem;
}

.system-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #eee;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item label {
    font-weight: 600;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-item span {
    color: #333;
}

.status-open {
    color: #28a745;
    font-weight: bold;
}

.status-closed {
    color: #dc3545;
    font-weight: bold;
}

.system-actions h4 {
    margin-top: 0;
    color: #495057;
    margin-bottom: 1rem;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s;
    text-align: center;
}

.btn-action:hover {
    background: #007bff;
    color: white;
    border-color: #007bff;
    transform: translateY(-2px);
}

.btn-action .icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
}

@media (max-width: 768px) {
    .config-grid {
        grid-template-columns: 1fr;
    }
    
    .example-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>