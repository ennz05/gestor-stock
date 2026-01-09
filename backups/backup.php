<?php
session_start();

/**
 * Script de backup para la base de datos
 * Ejecutar manualmente o programar con cron
 */

class BackupManager {
    private $db_host = "localhost";
    private $db_user = "root";
    private $db_pass = "";
    private $db_name = "gestor_stock";
    private $backup_dir = __DIR__ . "/backups/";

    public function __construct() {
        // Crear directorio de backups si no existe
        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }

    public function createBackup() {
        try {
            $filename = $this->db_name . "_backup_" . date("Y-m-d_H-i-s") . ".sql";
            $filepath = $this->backup_dir . $filename;
            
            // Comando para MySQL en XAMPP (Windows)
            $command = '"C:\xampp\mysql\bin\mysqldump.exe"'; // Ruta completa para XAMPP
            
            // Verificar si existe mysqldump en la ruta de XAMPP
            if (!file_exists($command)) {
                // Intentar con mysqldump en el PATH
                $command = "mysqldump";
            }
            
            $command .= " --user={$this->db_user}";
            
            if (!empty($this->db_pass)) {
                $command .= " --password={$this->db_pass}";
            }
            
            $command .= " --host={$this->db_host} {$this->db_name} > \"{$filepath}\"";
            
            // Ejecutar comando
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                // Registrar backup en log
                $log_message = "Backup creado exitosamente: " . $filename . " (" . 
                              $this->formatBytes(filesize($filepath)) . ") - " . date("Y-m-d H:i:s");
                $this->logBackup($log_message);
                
                // Limpiar backups antiguos (más de 30 días)
                $this->cleanOldBackups();
                
                return [
                    'success' => true,
                    'message' => $log_message,
                    'filename' => $filename,
                    'filesize' => $this->formatBytes(filesize($filepath))
                ];
            } else {
                // Intentar método alternativo usando PHP
                return $this->createBackupPHP();
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al crear backup: " . $e->getMessage()
            ];
        }
    }
    
    private function createBackupPHP() {
        try {
            // Conexión a la base de datos
            $conn = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
            
            if ($conn->connect_error) {
                return [
                    'success' => false,
                    'message' => "Error de conexión: " . $conn->connect_error
                ];
            }
            
            $filename = $this->db_name . "_backup_php_" . date("Y-m-d_H-i-s") . ".sql";
            $filepath = $this->backup_dir . $filename;
            
            $tables = array();
            $result = $conn->query("SHOW TABLES");
            
            while($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            
            $sql = "-- Backup creado el: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Base de datos: " . $this->db_name . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach($tables as $table) {
                // Obtener estructura de la tabla
                $result = $conn->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch_row();
                $sql .= "--\n-- Estructura de tabla para `$table`\n--\n\n";
                $sql .= $row[1] . ";\n\n";
                
                // Obtener datos de la tabla
                $sql .= "--\n-- Volcado de datos para la tabla `$table`\n--\n\n";
                $result2 = $conn->query("SELECT * FROM `$table`");
                
                while($row2 = $result2->fetch_assoc()) {
                    $sql .= "INSERT INTO `$table` VALUES(";
                    $values = array();
                    
                    foreach($row2 as $value) {
                        if ($value === null) {
                            $values[] = "NULL";
                        } else {
                            $values[] = "'" . $conn->real_escape_string($value) . "'";
                        }
                    }
                    
                    $sql .= implode(", ", $values) . ");\n";
                }
                
                $sql .= "\n";
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Guardar archivo
            if (file_put_contents($filepath, $sql)) {
                $log_message = "Backup creado exitosamente (PHP): " . $filename . " (" . 
                              $this->formatBytes(filesize($filepath)) . ") - " . date("Y-m-d H:i:s");
                $this->logBackup($log_message);
                
                $this->cleanOldBackups();
                
                return [
                    'success' => true,
                    'message' => $log_message,
                    'filename' => $filename,
                    'filesize' => $this->formatBytes(filesize($filepath))
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Error al guardar el archivo de backup"
                ];
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error en backup PHP: " . $e->getMessage()
            ];
        }
    }

    private function logBackup($message) {
        $log_file = $this->backup_dir . "backup_log.txt";
        $log_entry = date("Y-m-d H:i:s") . " - " . $message . PHP_EOL;
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function cleanOldBackups($days = 30) {
        try {
            $files = glob($this->backup_dir . "*.sql");
            $now = time();
            $deleted_count = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($now - filemtime($file) >= $days * 24 * 60 * 60) {
                        unlink($file);
                        $deleted_count++;
                    }
                }
            }
            
            if ($deleted_count > 0) {
                $this->logBackup("Se eliminaron $deleted_count backups antiguos (más de $days días)");
            }
            
        } catch (Exception $e) {
            // No hacer nada si falla la limpieza
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public function listBackups() {
        $backups = glob($this->backup_dir . "*.sql");
        $backup_list = [];
        
        foreach ($backups as $file) {
            if (is_file($file)) {
                $backup_list[] = [
                    'filename' => basename($file),
                    'filesize' => $this->formatBytes(filesize($file)),
                    'date' => date('d/m/Y H:i:s', filemtime($file)),
                    'path' => $file
                ];
            }
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($backup_list, function($a, $b) {
            return filemtime($b['path']) - filemtime($a['path']);
        });
        
        return $backup_list;
    }
}

// Procesar acción
if (isset($_GET['action'])) {
    $backupManager = new BackupManager();
    
    if ($_GET['action'] == 'backup') {
        $result = $backupManager->createBackup();
        
        if (php_sapi_name() === 'cli') {
            // Ejecución por línea de comandos
            echo json_encode($result, JSON_PRETTY_PRINT);
        } else {
            // Ejecución web
            if ($result['success']) {
                echo "<script>
                    alert('✅ " . $result['message'] . "');
                    window.location.href='../../index.php';
                </script>";
            } else {
                echo "<script>
                    alert('❌ " . $result['message'] . "');
                    window.location.href='../../index.php';
                </script>";
            }
        }
        
    } elseif ($_GET['action'] == 'list') {
        $backups = $backupManager->listBackups();
        
        if (php_sapi_name() === 'cli') {
            print_r($backups);
        } else {
            echo json_encode($backups, JSON_PRETTY_PRINT);
        }
    }
    
} else {
    // Página de gestión de backups
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Backups</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f0f2f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
            .btn { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; margin: 5px; }
            .btn:hover { background: #2980b9; }
            .btn-success { background: #28a745; }
            .btn-success:hover { background: #218838; }
            .btn-danger { background: #dc3545; }
            .btn-danger:hover { background: #c82333; }
            .message { padding: 15px; border-radius: 5px; margin: 15px 0; }
            .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .backup-list { margin-top: 20px; }
            .backup-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 10px; background: #f8f9fa; }
            .backup-info { flex: 1; }
            .backup-actions { display: flex; gap: 10px; }
            .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
            .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #3498db; }
            .stat-card h3 { margin: 0; font-size: 2rem; color: #2c3e50; }
            .stat-card p { margin: 5px 0 0 0; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>💾 Gestión de Backups</h1>
            
            <?php
            $backupManager = new BackupManager();
            $backups = $backupManager->listBackups();
            $total_size = 0;
            
            foreach ($backups as $backup) {
                $size = str_replace([' KB', ' MB', ' GB', ' TB'], '', $backup['filesize']);
                $unit = substr($backup['filesize'], -2);
                
                switch ($unit) {
                    case 'KB': $total_size += $size * 1024; break;
                    case 'MB': $total_size += $size * 1024 * 1024; break;
                    case 'GB': $total_size += $size * 1024 * 1024 * 1024; break;
                    case 'TB': $total_size += $size * 1024 * 1024 * 1024 * 1024; break;
                    default: $total_size += $size; break;
                }
            }
            
            $formatted_size = $backupManager->formatBytes($total_size);
            ?>
            
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo count($backups); ?></h3>
                    <p>Backups Totales</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $formatted_size; ?></h3>
                    <p>Espacio Utilizado</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count($backups) > 0 ? $backups[0]['date'] : 'N/A'; ?></h3>
                    <p>Último Backup</p>
                </div>
            </div>
            
            <div class="actions">
                <a href="?action=backup" class="btn btn-success" 
                   onclick="return confirm('¿Generar un nuevo backup de la base de datos?')">
                    🔄 Crear Nuevo Backup
                </a>
                <a href="../../index.php" class="btn">🏠 Volver al Sistema</a>
            </div>
            
            <div class="backup-list">
                <h3>📋 Lista de Backups</h3>
                
                <?php if (count($backups) > 0): ?>
                    <?php foreach ($backups as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <strong><?php echo $backup['filename']; ?></strong><br>
                            <small>📅 <?php echo $backup['date']; ?> | 📦 <?php echo $backup['filesize']; ?></small>
                        </div>
                        <div class="backup-actions">
                            <a href="download.php?file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-success" target="_blank">
                                ⬇️ Descargar
                            </a>
                            <a href="?action=delete&file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-danger" 
                               onclick="return confirm('¿Eliminar este backup?')">
                                🗑️ Eliminar
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="message info">
                        No hay backups disponibles. Cree el primer backup usando el botón anterior.
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="info">
                <h3>📌 Información Importante</h3>
                <ul>
                    <li>Los backups se guardan automáticamente en la carpeta <code>backups/backups/</code></li>
                    <li>Se eliminan automáticamente los backups con más de 30 días</li>
                    <li>Recomendamos descargar y guardar copias externas regularmente</li>
                    <li>El sistema mantiene un log de todos los backups realizados</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>