<?php
include '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = '';
$tipo_mensaje = '';

// Obtener configuración actual
$query = "SELECT * FROM configuracion";
$stmt = $db->prepare($query);
$stmt->execute();
$configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir a array asociativo
$config = [];
foreach ($configuraciones as $item) {
    $config[$item['clave']] = $item['valor'];
}

// Procesar actualización
if ($_POST) {
    try {
        foreach ($_POST['config'] as $clave => $valor) {
            $query = "UPDATE configuracion SET valor = :valor WHERE clave = :clave";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':valor', $valor);
            $stmt->bindParam(':clave', $clave);
            $stmt->execute();
        }
        
        $mensaje = "Configuración actualizada exitosamente!";
        $tipo_mensaje = "success";
        
        // Recargar configuración
        $stmt->execute();
        $configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $config = [];
        foreach ($configuraciones as $item) {
            $config[$item['clave']] = $item['valor'];
        }
    } catch(PDOException $exception) {
        $mensaje = "Error: " . $exception->getMessage();
        $tipo_mensaje = "error";
    }
}
?>

<div class="container">
    <h2>Configuración del Sistema</h2>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" class="config-form">
        <div class="config-section">
            <h3>Configuración de Precios</h3>
            
            <div class="form-group">
                <label for="porcentaje_ganancia_default">
                    Porcentaje de Ganancia por Defecto (%):
                    <small>Este porcentaje se aplicará a nuevos productos</small>
                </label>
                <input type="number" id="porcentaje_ganancia_default" name="config[porcentaje_ganancia_default]" 
                       step="0.01" min="0" max="500" 
                       value="<?php echo htmlspecialchars($config['porcentaje_ganancia_default'] ?? '10'); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="config[calcular_venta_auto]" value="1" 
                           <?php echo ($config['calcular_venta_auto'] ?? '1') == '1' ? 'checked' : ''; ?>>
                    Calcular automáticamente precio de venta al modificar precio de compra
                </label>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Guardar Configuración</button>
            <a href="../productos/listar.php" class="btn">Cancelar</a>
        </div>
    </form>
</div>

<style>
.config-section {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.config-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 0.5rem;
    width: auto;
}
</style>

<?php include '../../includes/footer.php'; ?>