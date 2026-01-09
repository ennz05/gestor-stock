<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar si ya hay caja abierta
if ($functions->isCajaAbierta()) {
    header("Location: ../../index.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar apertura de caja
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['abrir_caja'])) {
    $monto_inicial = $_POST['monto_inicial'];
    $observaciones = $_POST['observaciones'];
    $fecha_apertura = date('Y-m-d H:i:s');
    
    try {
        $query = "INSERT INTO caja (fecha_apertura, monto_inicial, observaciones, estado) 
                  VALUES (:fecha_apertura, :monto_inicial, :observaciones, 'abierta')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_apertura', $fecha_apertura);
        $stmt->bindParam(':monto_inicial', $monto_inicial);
        $stmt->bindParam(':observaciones', $observaciones);
        
        if ($stmt->execute()) {
            $_SESSION['caja_abierta'] = true;
            header("Location: ../../index.php?msg=caja_abierta");
            exit;
        } else {
            $mensaje = "❌ Error al abrir la caja";
            $tipo_mensaje = "error";
        }
    } catch(PDOException $e) {
        $mensaje = "❌ Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <div class="caja-form-container">
        <div class="caja-header">
            <h2>💰 Apertura de Caja</h2>
            <div class="caja-status closed">
                🔒 Caja Cerrada
            </div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>📌 Información importante:</h4>
            <ul>
                <li>✅ Debe abrir la caja al comenzar el día de trabajo</li>
                <li>💰 El monto inicial será el dinero con el que inicia operaciones</li>
                <li>⏰ Registre la hora exacta de apertura</li>
                <li>📝 Las observaciones ayudarán a llevar un mejor control</li>
                <li>🔒 No podrá realizar movimientos sin caja abierta</li>
            </ul>
        </div>
        
        <form method="post" action="" class="caja-form">
            <div class="form-group">
                <label for="monto_inicial">💰 Monto Inicial:</label>
                <div class="input-group">
                    <span class="input-addon">$</span>
                    <input type="number" id="monto_inicial" name="monto_inicial" 
                           step="0.01" min="0" value="0.00" required 
                           placeholder="0.00" autofocus>
                </div>
                <small>Ingrese el dinero con el que inicia la caja</small>
            </div>
            
            <div class="form-group">
                <label for="observaciones">📝 Observaciones:</label>
                <textarea id="observaciones" name="observaciones" rows="3" 
                          placeholder="Ej: Inicio de turno mañana, Fondo fijo, Dinero para cambio..."></textarea>
                <small>Opcional: ingrese cualquier información relevante</small>
            </div>
            
            <div class="info-adicional">
                <h4>📅 Información de la sesión:</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Fecha:</label>
                        <span><?php echo date('d/m/Y'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Hora:</label>
                        <span><?php echo date('H:i:s'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Usuario:</label>
                        <span><?php echo $functions->getUsuarioDefault(); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="abrir_caja" class="btn btn-success btn-large">
                    <span style="margin-right: 10px;">💰</span> Abrir Caja e Iniciar Jornada
                </button>
                <a href="../../index.php" class="btn">
                    <span style="margin-right: 5px;">↩️</span> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Formatear monto inicial automáticamente
document.getElementById('monto_inicial').addEventListener('blur', function() {
    let value = parseFloat(this.value);
    if (!isNaN(value)) {
        this.value = value.toFixed(2);
    }
});

// Enfocar el campo de monto inicial
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('monto_inicial').focus();
});
</script>

<style>
.caja-form-container {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.caja-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #eee;
}

.caja-header h2 {
    margin: 0;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.caja-status {
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.caja-status.closed {
    background: #f8d7da;
    color: #721c24;
    border: 2px solid #f5c6cb;
}

.info-box {
    background: #e7f3ff;
    padding: 1.5rem;
    border-radius: 10px;
    margin: 1.5rem 0;
    border-left: 4px solid #007bff;
}

.info-box h4 {
    margin-top: 0;
    color: #007bff;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box ul {
    margin: 0;
    padding-left: 1.5rem;
    color: #495057;
}

.info-box li {
    margin-bottom: 0.5rem;
}

.info-box li:last-child {
    margin-bottom: 0;
}

.caja-form {
    margin-top: 1.5rem;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-addon {
    background: #e9ecef;
    padding: 0.75rem 1rem;
    border: 1px solid #ced4da;
    color: #495057;
    font-weight: 500;
    border-radius: 6px 0 0 6px;
    border-right: none;
}

.input-group input {
    border-radius: 0 6px 6px 0;
    flex: 1;
}

.info-adicional {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    margin: 1.5rem 0;
    border: 1px solid #e9ecef;
}

.info-adicional h4 {
    margin-top: 0;
    color: #495057;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.info-item {
    text-align: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.info-item label {
    display: block;
    font-weight: bold;
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.info-item span {
    color: #333;
    font-size: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid #eee;
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .caja-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .info-grid {
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