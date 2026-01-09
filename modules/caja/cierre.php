<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Obtener caja actual abierta
$caja_actual = $functions->isCajaAbierta();
$_SESSION['caja_abierta'] = $caja_actual;

if (!$caja_actual) {
    header("Location: apertura.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Calcular ventas del día
$query_ventas = "SELECT SUM(m.cantidad * p.precio_venta) as total_ventas,
                        SUM(m.cantidad) as total_unidades
                 FROM movimientos_inventario m
                 JOIN productos p ON m.producto_id = p.id
                 WHERE m.tipo = 'salida' 
                 AND DATE(m.fecha_movimiento) = CURDATE()";
$stmt_ventas = $db->prepare($query_ventas);
$stmt_ventas->execute();
$ventas = $stmt_ventas->fetch(PDO::FETCH_ASSOC);
$total_ventas = $ventas['total_ventas'] ?: 0;
$total_unidades = $ventas['total_unidades'] ?: 0;

// Calcular entradas del día
$query_entradas = "SELECT SUM(m.cantidad) as total_entradas
                   FROM movimientos_inventario m
                   WHERE m.tipo = 'entrada' 
                   AND DATE(m.fecha_movimiento) = CURDATE()";
$stmt_entradas = $db->prepare($query_entradas);
$stmt_entradas->execute();
$entradas = $stmt_entradas->fetch(PDO::FETCH_ASSOC);
$total_entradas = $entradas['total_entradas'] ?: 0;

// Calcular monto estimado
$monto_estimado = $caja_actual['monto_inicial'] + $total_ventas;

// Procesar cierre de caja
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cerrar_caja'])) {
    $monto_final = $_POST['monto_final'];
    $observaciones_cierre = $_POST['observaciones_cierre'];
    $fecha_cierre = date('Y-m-d H:i:s');
    
    try {
        // Calcular diferencia
        $diferencia = $monto_final - $monto_estimado;
        
        $query = "UPDATE caja SET 
                  fecha_cierre = :fecha_cierre,
                  monto_final = :monto_final,
                  observaciones = CONCAT(COALESCE(observaciones, ''), ' | CIERRE: ', :observaciones),
                  estado = 'cerrada'
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_cierre', $fecha_cierre);
        $stmt->bindParam(':monto_final', $monto_final);
        $stmt->bindParam(':observaciones', $observaciones_cierre);
        $stmt->bindParam(':id', $caja_actual['id']);
        
        if ($stmt->execute()) {
            $_SESSION['caja_abierta'] = false;
            header("Location: ../../index.php?msg=caja_cerrada&diferencia=" . $diferencia);
            exit;
        } else {
            $mensaje = "❌ Error al cerrar la caja";
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
            <h2>🔒 Cierre de Caja</h2>
            <div class="caja-status open">
                💰 Caja Abierta
            </div>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="resumen-caja">
            <h3>📊 Resumen de la Jornada</h3>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <div class="resumen-icon">⏰</div>
                    <div class="resumen-info">
                        <label>Apertura:</label>
                        <span><?php echo date('d/m/Y H:i', strtotime($caja_actual['fecha_apertura'])); ?></span>
                    </div>
                </div>
                
                <div class="resumen-item">
                    <div class="resumen-icon">💰</div>
                    <div class="resumen-info">
                        <label>Monto inicial:</label>
                        <span class="monto-inicial">$<?php echo number_format($caja_actual['monto_inicial'], 2); ?></span>
                    </div>
                </div>
                
                <div class="resumen-item">
                    <div class="resumen-icon">📦</div>
                    <div class="resumen-info">
                        <label>Unidades vendidas:</label>
                        <span class="unidades-vendidas"><?php echo $total_unidades; ?></span>
                    </div>
                </div>
                
                <div class="resumen-item">
                    <div class="resumen-icon">💵</div>
                    <div class="resumen-info">
                        <label>Ventas del día:</label>
                        <span class="ventas-dia">$<?php echo number_format($total_ventas, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="resumen-total">
                <div class="total-estimado">
                    <h4>💼 Total Estimado en Caja:</h4>
                    <div class="monto-estimado">$<?php echo number_format($monto_estimado, 2); ?></div>
                    <small>Monto inicial + Ventas del día</small>
                </div>
            </div>
            
            <div class="info-box">
                <h4>📌 Instrucciones para el cierre:</h4>
                <ol>
                    <li>Cuente el dinero real que hay en caja</li>
                    <li>Ingrese el monto final real en el formulario</li>
                    <li>Revise la diferencia automáticamente calculada</li>
                    <li>Registre observaciones si hay sobrantes o faltantes</li>
                    <li>Confirme el cierre para finalizar la jornada</li>
                </ol>
            </div>
        </div>
        
        <form method="post" action="" class="caja-form">
            <div class="form-group">
                <label for="monto_final">💰 Monto Final Real:</label>
                <div class="input-group">
                    <span class="input-addon">$</span>
                    <input type="number" id="monto_final" name="monto_final" 
                           step="0.01" min="0" value="<?php echo number_format($monto_estimado, 2); ?>" 
                           required placeholder="0.00" autofocus
                           oninput="calcularDiferencia()">
                </div>
                <small>Cuente el dinero real que hay en caja e ingréselo aquí</small>
            </div>
            
            <div class="form-group">
                <label for="observaciones_cierre">📝 Observaciones del Cierre:</label>
                <textarea id="observaciones_cierre" name="observaciones_cierre" rows="3" 
                          placeholder="Ej: Todo en orden, Faltante de $X, Sobrante de $X, Billetes específicos..."></textarea>
                <small>Opcional: describa cualquier irregularidad</small>
            </div>
            
            <div class="diferencia-info" id="diferenciaContainer">
                <!-- Se llenará con JavaScript -->
            </div>
            
            <div class="form-actions">
                <button type="submit" name="cerrar_caja" class="btn btn-danger btn-large">
                    <span style="margin-right: 10px;">🔒</span> Confirmar Cierre y Finalizar Jornada
                </button>
                <a href="../../index.php" class="btn">
                    <span style="margin-right: 5px;">↩️</span> Volver sin Cerrar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const montoEstimado = <?php echo $monto_estimado; ?>;

function calcularDiferencia() {
    const montoFinal = parseFloat(document.getElementById('monto_final').value) || 0;
    const diferencia = montoFinal - montoEstimado;
    
    let clase = '';
    let icono = '';
    let texto = '';
    
    if (diferencia > 0) {
        clase = 'diferencia-positiva';
        icono = '📈';
        texto = 'Sobrante';
    } else if (diferencia < 0) {
        clase = 'diferencia-negativa';
        icono = '📉';
        texto = 'Faltante';
    } else {
        clase = 'diferencia-cero';
        icono = '✅';
        texto = 'Exacto';
    }
    
    const diferenciaContainer = document.getElementById('diferenciaContainer');
    diferenciaContainer.innerHTML = `
        <div class="diferencia ${clase}">
            <div class="diferencia-header">
                ${icono} <strong>Diferencia:</strong>
            </div>
            <div class="diferencia-monto">
                ${diferencia >= 0 ? '+' : ''}$${Math.abs(diferencia).toFixed(2)}
            </div>
            <div class="diferencia-texto">
                <small>(${texto})</small>
            </div>
        </div>
    `;
}

// Calcular diferencia inicial
document.addEventListener('DOMContentLoaded', function() {
    calcularDiferencia();
    document.getElementById('monto_final').focus();
});
</script>

<style>
.caja-form-container {
    max-width: 700px;
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

.caja-status.open {
    background: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.resumen-caja {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    border: 1px solid #e9ecef;
}

.resumen-caja h3 {
    margin-top: 0;
    color: #2c3e50;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.resumen-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.resumen-item {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.resumen-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.resumen-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.resumen-info label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.resumen-info span {
    color: #333;
    font-size: 1.1rem;
    font-weight: 500;
}

.monto-inicial {
    color: #28a745;
    font-weight: bold;
}

.unidades-vendidas {
    color: #007bff;
    font-weight: bold;
}

.ventas-dia {
    color: #17a2b8;
    font-weight: bold;
}

.resumen-total {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    margin-top: 1.5rem;
    border: 2px solid #28a745;
    text-align: center;
}

.total-estimado h4 {
    margin-top: 0;
    color: #495057;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.monto-estimado {
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
    margin: 0.5rem 0;
}

.info-box {
    background: #fff3cd;
    padding: 1.5rem;
    border-radius: 10px;
    margin-top: 1.5rem;
    border-left: 4px solid #ffc107;
}

.info-box h4 {
    margin-top: 0;
    color: #856404;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box ol {
    margin: 0;
    padding-left: 1.5rem;
    color: #856404;
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

.diferencia {
    padding: 1.5rem;
    border-radius: 12px;
    margin: 1.5rem 0;
    text-align: center;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.diferencia-header {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.diferencia-monto {
    font-size: 2rem;
    font-weight: bold;
    margin: 0.5rem 0;
}

.diferencia-texto {
    font-size: 0.9rem;
}

.diferencia-positiva {
    background: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
}

.diferencia-negativa {
    background: #f8d7da;
    color: #721c24;
    border: 2px solid #f5c6cb;
}

.diferencia-cero {
    background: #fff3cd;
    color: #856404;
    border: 2px solid #ffeaa7;
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
    
    .resumen-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .resumen-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>