<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador Sistema de Stock</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .install-container { background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 100%; max-width: 900px; overflow: hidden; }
        .install-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .install-header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .install-header p { font-size: 1.1rem; opacity: 0.9; }
        .install-content { padding: 40px; }
        .step { margin-bottom: 30px; padding: 25px; border-radius: 10px; background: #f8f9fa; border-left: 5px solid #667eea; }
        .step h3 { color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .step-icon { font-size: 1.5rem; }
        .message { padding: 15px; border-radius: 8px; margin: 15px 0; font-weight: 500; }
        .success { background: #d4ffd4; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #ffd4d4; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: transform 0.3s, box-shadow 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: 'Courier New', monospace; margin: 15px 0; }
        .progress-bar { width: 100%; height: 10px; background: #e9ecef; border-radius: 5px; margin: 20px 0; overflow: hidden; }
        .progress { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 5px; transition: width 0.5s; }
        .step-list { list-style: none; }
        .step-list li { padding: 10px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .step-list li:last-child { border-bottom: none; }
        .status { padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
        .status-pending { background: #ffc107; color: #856404; }
        .status-success { background: #28a745; color: white; }
        .status-error { background: #dc3545; color: white; }
        .sql-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🚀 Instalador Sistema de Stock</h1>
            <p>Configuración completa de la base de datos y tablas necesarias</p>
        </div>
        
        <div class="install-content">
            <?php
            require_once 'config/database.php';
            
            $database = new Database();
            
            // Primero conectarnos sin base de datos para crearla
            $conn = null;
            try {
                // Conectar sin seleccionar base de datos
                $conn = new PDO("mysql:host=localhost", "root", "");
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo '<div class="message success">✅ Conexión a MySQL establecida correctamente</div>';
                
                // Verificar si la base de datos existe
                $stmt = $conn->query("SHOW DATABASES LIKE 'gestor_stock'");
                $db_exists = $stmt->rowCount() > 0;
                
                if ($db_exists) {
                    echo '<div class="message info">📊 Base de datos <strong>gestor_stock</strong> ya existe</div>';
                    $conn->query("USE gestor_stock");
                } else {
                    echo '<div class="message warning">⚠️ Base de datos <strong>gestor_stock</strong> no existe, será creada</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="message error">❌ Error de conexión: ' . $e->getMessage() . '</div>';
                echo '<p>Verifica las credenciales en config/database.php</p>';
                echo '<p>Verifica que MySQL esté ejecutándose en XAMPP</p>';
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['install'])) {
                echo '<div class="step">';
                echo '<h3><span class="step-icon">⚙️</span> Creando base de datos y tablas...</h3>';
                
                // Leer archivo SQL
                $sql_file = 'data/stock.sql';
                if (!file_exists($sql_file)) {
                    echo '<div class="message error">❌ No se encontró el archivo SQL: ' . $sql_file . '</div>';
                } else {
                    $sql = file_get_contents($sql_file);
                    
                    // Dividir por sentencias
                    $queries = explode(';', $sql);
                    $total_queries = count($queries);
                    $success_count = 0;
                    $error_count = 0;
                    $errors_list = [];
                    
                    echo '<div class="progress-bar"><div class="progress" id="installProgress" style="width: 0%"></div></div>';
                    echo '<div id="installStatus">Procesando 0 de ' . $total_queries . ' consultas...</div>';
                    
                    ob_flush();
                    flush();
                    
                    foreach ($queries as $index => $query) {
                        $query = trim($query);
                        if (!empty($query)) {
                            try {
                                $stmt = $conn->prepare($query);
                                if ($stmt->execute()) {
                                    $success_count++;
                                }
                            } catch (Exception $e) {
                                // Ignorar errores de "database already exists" y similares
                                $error_msg = $e->getMessage();
                                if (!strpos($error_msg, 'already exists') && 
                                    !strpos($error_msg, 'DROP DATABASE IF EXISTS') &&
                                    !strpos($error_msg, 'Unknown database')) {
                                    $error_count++;
                                    $errors_list[] = 'Consulta ' . ($index + 1) . ': ' . $error_msg;
                                } else {
                                    $success_count++; // Contar como éxito si es error esperado
                                }
                            }
                        }
                        
                        // Actualizar progreso
                        $progress = (($index + 1) / $total_queries) * 100;
                        echo '<script>document.getElementById("installProgress").style.width = "' . $progress . '%";</script>';
                        echo '<script>document.getElementById("installStatus").innerHTML = "Procesando ' . ($index + 1) . ' de ' . $total_queries . ' consultas...";</script>';
                        ob_flush();
                        flush();
                        usleep(50000); // Pequeña pausa para ver el progreso
                    }
                    
                    echo '<div class="message ' . ($error_count == 0 ? 'success' : 'warning') . '">';
                    echo '<h4>Resultado de la instalación:</h4>';
                    echo '<p>✅ Consultas exitosas: ' . $success_count . '</p>';
                    echo '<p>❌ Errores críticos: ' . $error_count . '</p>';
                    
                    if ($error_count > 0) {
                        echo '<p>Detalles de errores críticos:</p>';
                        echo '<ul>';
                        foreach ($errors_list as $error) {
                            echo '<li>' . $error . '</li>';
                        }
                        echo '</ul>';
                    }
                    
                    if ($success_count > 0) {
                        echo '<p>✅ Instalación completada exitosamente!</p>';
                        
                        // Verificar tablas creadas
                        try {
                            $stmt = $conn->query("SHOW TABLES");
                            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            echo '<p>📊 Tablas creadas: ' . count($tables) . '</p>';
                            echo '<ul>';
                            foreach ($tables as $table) {
                                echo '<li>' . $table . '</li>';
                            }
                            echo '</ul>';
                        } catch (Exception $e) {
                            // Ignorar error
                        }
                        
                        echo '<a href="index.php" class="btn btn-success">Ir al Sistema</a>';
                    }
                    
                    echo '</div>';
                }
                echo '</div>';
                
            } else {
            ?>
            
            <div class="step">
                <h3><span class="step-icon">📋</span> Verificación del sistema</h3>
                <ul class="step-list">
                    <?php
                    $checks = [
                        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                        'MySQL Extension' => extension_loaded('pdo_mysql'),
                        'config/database.php' => file_exists('config/database.php'),
                        'data/stock.sql' => file_exists('data/stock.sql'),
                        'Permisos de escritura' => is_writable('.'),
                    ];
                    
                    foreach ($checks as $check => $result) {
                        echo '<li>';
                        echo '<span>' . $check . '</span>';
                        echo '<span class="status ' . ($result ? 'status-success' : 'status-error') . '">';
                        echo $result ? '✅ OK' : '❌ FALLO';
                        echo '</span>';
                        echo '</li>';
                    }
                    ?>
                </ul>
            </div>
            
            <div class="step">
                <h3><span class="step-icon">🗄️</span> Configuración de base de datos</h3>
                <div class="sql-box">
                    <p><strong>Configuración actual (config/database.php):</strong></p>
                    <pre><?php
                    $config_content = file_get_contents('config/database.php');
                    echo htmlspecialchars($config_content);
                    ?></pre>
                    
                    <p><strong>Base de datos:</strong> gestor_stock (será creada)</p>
                    <p><strong>Host:</strong> localhost</p>
                    <p><strong>Usuario:</strong> root</p>
                    <p><strong>Contraseña:</strong> [vacío]</p>
                    <p><strong>Tablas a crear:</strong></p>
                    <ul>
                        <li>productos</li>
                        <li>movimientos_inventario</li>
                        <li>caja</li>
                        <li>configuracion</li>
                    </ul>
                </div>
            </div>
            
            <div class="step">
                <h3><span class="step-icon">🚀</span> Instalación</h3>
                <p>Esta acción creará la base de datos, todas las tablas necesarias y datos de ejemplo.</p>
                <p><strong>⚠️ ADVERTENCIA:</strong> Si la base de datos ya existe, se agregarán las tablas faltantes. Los datos existentes se mantendrán.</p>
                
                <form method="post" action="" onsubmit="return confirm('¿Está seguro de proceder con la instalación?');">
                    <button type="submit" name="install" class="btn">
                        <span class="step-icon">⚡</span> Iniciar Instalación Completa
                    </button>
                </form>
                
                <p style="margin-top: 20px;">
                    <small>Si prefieres hacerlo manualmente, ejecuta el archivo <strong>data/stock.sql</strong> en phpMyAdmin.</small>
                </p>
            </div>
            
            <?php } ?>
        </div>
    </div>
</body>
</html>