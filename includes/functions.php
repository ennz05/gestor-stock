<?php
class Functions {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Verificar si una tabla existe
    private function tableExists($tableName) {
        try {
            $query = "SHOW TABLES LIKE :table";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':table', $tableName);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Crear tablas si no existen
    public function createMissingTables() {
        $tables = [
            'caja' => "CREATE TABLE IF NOT EXISTS caja (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fecha_apertura DATETIME NOT NULL,
                fecha_cierre DATETIME,
                monto_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
                monto_final DECIMAL(10,2),
                estado ENUM('abierta', 'cerrada') DEFAULT 'abierta',
                observaciones TEXT
            )",
            
            'configuracion' => "CREATE TABLE IF NOT EXISTS configuracion (
                id INT AUTO_INCREMENT PRIMARY KEY,
                clave VARCHAR(50) UNIQUE NOT NULL,
                valor VARCHAR(255),
                descripcion TEXT
            )"
        ];
        
        foreach ($tables as $table => $sql) {
            if (!$this->tableExists($table)) {
                try {
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute();
                    
                    // Insertar datos por defecto para configuracion
                    if ($table == 'configuracion') {
                        $default_data = [
                            ['porcentaje_ganancia', '10', 'Porcentaje de ganancia aplicado al precio de compra para calcular precio de venta'],
                            ['usuario_default', 'Administrador', 'Usuario por defecto para movimientos']
                        ];
                        
                        foreach ($default_data as $data) {
                            $query = "INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?)";
                            $stmt = $this->conn->prepare($query);
                            $stmt->execute($data);
                        }
                    }
                } catch(PDOException $e) {
                    error_log("Error creating table $table: " . $e->getMessage());
                }
            }
        }
    }
    
    // Obtener porcentaje de ganancia
    public function getPorcentajeGanancia() {
        $this->createMissingTables();
        
        try {
            $query = "SELECT valor FROM configuracion WHERE clave = 'porcentaje_ganancia'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? floatval($result['valor']) : 10;
        } catch(PDOException $e) {
            return 10;
        }
    }
    
    // Calcular precio de venta basado en porcentaje
    public function calcularPrecioVenta($precio_compra) {
        $porcentaje = $this->getPorcentajeGanancia();
        return $precio_compra * (1 + ($porcentaje / 100));
    }
    
    // Verificar si la caja está abierta
    public function isCajaAbierta() {
        $this->createMissingTables();
        
        try {
            $query = "SELECT * FROM caja WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Obtener usuario por defecto
    public function getUsuarioDefault() {
        $this->createMissingTables();
        
        try {
            $query = "SELECT valor FROM configuracion WHERE clave = 'usuario_default'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['valor'] : 'Administrador';
        } catch(PDOException $e) {
            return 'Administrador';
        }
    }
}
?>