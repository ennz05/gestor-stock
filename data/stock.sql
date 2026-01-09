-- Sistema de Gestión de Stock - Base de Datos
-- Versión 1.0

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS gestor_stock 
DEFAULT CHARACTER SET utf8mb4 
DEFAULT COLLATE utf8mb4_unicode_ci;

USE gestor_stock;

-- Tabla de productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_barras VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio_compra DECIMAL(10,2) DEFAULT 0.00,
    precio_venta DECIMAL(10,2) DEFAULT 0.00,
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo_barras (codigo_barras),
    INDEX idx_nombre (nombre),
    INDEX idx_stock (stock_actual),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de movimientos de inventario
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    tipo ENUM('entrada', 'salida', 'ajuste') NOT NULL,
    cantidad INT NOT NULL,
    motivo VARCHAR(255),
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100) DEFAULT 'Administrador',
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto_id (producto_id),
    INDEX idx_tipo (tipo),
    INDEX idx_fecha_movimiento (fecha_movimiento),
    INDEX idx_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor VARCHAR(255),
    descripcion TEXT,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de caja
CREATE TABLE IF NOT EXISTS caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_apertura DATETIME NOT NULL,
    fecha_cierre DATETIME,
    monto_inicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monto_final DECIMAL(10,2),
    estado ENUM('abierta', 'cerrada') DEFAULT 'abierta',
    observaciones TEXT,
    INDEX idx_fecha_apertura (fecha_apertura),
    INDEX idx_estado (estado),
    INDEX idx_fecha_cierre (fecha_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración por defecto
INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
('porcentaje_ganancia', '10', 'Porcentaje de ganancia aplicado al precio de compra para calcular precio de venta'),
('usuario_default', 'Administrador', 'Usuario por defecto para movimientos de inventario');

-- Insertar datos de ejemplo para productos
INSERT IGNORE INTO productos (codigo_barras, nombre, descripcion, precio_compra, precio_venta, stock_actual, stock_minimo) VALUES
('7501006558602', 'Coca-Cola 600ml', 'Refresco de cola en botella de 600ml', 12.50, 13.75, 50, 10),
('7501055300909', 'Sabritas Original 45g', 'Papas fritas sabor original 45g', 8.00, 8.80, 30, 5),
('7501032903308', 'Galletas Marías Gamesa', 'Galletas Marías paquete 612g', 25.00, 27.50, 20, 3),
('7501001303930', 'Agua Bonafont 1L', 'Agua purificada botella 1 litro', 6.50, 7.15, 100, 20),
('7501006558620', 'Coca-Cola Sin Azúcar 600ml', 'Refresco de cola sin azúcar 600ml', 13.00, 14.30, 40, 8),
('7501006558610', 'Coca-Cola Light 600ml', 'Refresco de cola light 600ml', 13.00, 14.30, 35, 7),
('7501055312345', 'Sabritas Adobadas 45g', 'Papas fritas sabor adobadas 45g', 8.50, 9.35, 25, 5),
('7501032912345', 'Galletas Emperador Chocolate', 'Galletas con chocolate 240g', 18.00, 19.80, 45, 9),
('7501001312345', 'Agua Epura 1L', 'Agua natural botella 1 litro', 5.50, 6.05, 80, 15),
('7501055323456', 'Ruffles Queso 45g', 'Papas fritas onduladas sabor queso 45g', 9.00, 9.90, 28, 6);

-- Insertar datos de ejemplo para movimientos (últimos 7 días)
INSERT IGNORE INTO movimientos_inventario (producto_id, tipo, cantidad, motivo, fecha_movimiento, usuario) VALUES
(1, 'entrada', 100, 'Compra semanal', DATE_SUB(NOW(), INTERVAL 7 DAY), 'Administrador'),
(2, 'entrada', 50, 'Compra semanal', DATE_SUB(NOW(), INTERVAL 7 DAY), 'Administrador'),
(3, 'entrada', 30, 'Compra semanal', DATE_SUB(NOW(), INTERVAL 7 DAY), 'Administrador'),
(1, 'salida', 50, 'Venta', DATE_SUB(NOW(), INTERVAL 6 DAY), 'Vendedor 1'),
(2, 'salida', 20, 'Venta', DATE_SUB(NOW(), INTERVAL 6 DAY), 'Vendedor 1'),
(4, 'entrada', 200, 'Compra mensual', DATE_SUB(NOW(), INTERVAL 5 DAY), 'Administrador'),
(1, 'salida', 30, 'Venta', DATE_SUB(NOW(), INTERVAL 5 DAY), 'Vendedor 2'),
(5, 'entrada', 80, 'Compra quincenal', DATE_SUB(NOW(), INTERVAL 4 DAY), 'Administrador'),
(3, 'salida', 10, 'Venta', DATE_SUB(NOW(), INTERVAL 4 DAY), 'Vendedor 1'),
(6, 'entrada', 70, 'Compra quincenal', DATE_SUB(NOW(), INTERVAL 3 DAY), 'Administrador'),
(2, 'salida', 15, 'Venta', DATE_SUB(NOW(), INTERVAL 3 DAY), 'Vendedor 2'),
(4, 'salida', 100, 'Venta', DATE_SUB(NOW(), INTERVAL 2 DAY), 'Vendedor 1'),
(7, 'entrada', 50, 'Compra semanal', DATE_SUB(NOW(), INTERVAL 2 DAY), 'Administrador'),
(1, 'ajuste', 5, 'Conteo físico', DATE_SUB(NOW(), INTERVAL 1 DAY), 'Supervisor'),
(8, 'entrada', 90, 'Compra mensual', DATE_SUB(NOW(), INTERVAL 1 DAY), 'Administrador');

-- Insertar datos de ejemplo para caja (última semana)
INSERT IGNORE INTO caja (fecha_apertura, fecha_cierre, monto_inicial, monto_final, estado, observaciones) VALUES
(DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY) + INTERVAL 8 HOUR, 500.00, 1250.50, 'cerrada', 'Jornada normal, ventas promedio'),
(DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY) + INTERVAL 8 HOUR, 500.00, 1430.75, 'cerrada', 'Día con buena venta'),
(DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY) + INTERVAL 8 HOUR, 500.00, 980.25, 'cerrada', 'Día con pocas ventas'),
(DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 8 HOUR, 500.00, 1670.30, 'cerrada', 'Fin de semana, excelente venta'),
(DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 8 HOUR, 500.00, 1120.60, 'cerrada', 'Ventas normales'),
(DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 8 HOUR, 500.00, 890.45, 'cerrada', 'Día lento'),
(DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY) + INTERVAL 8 HOUR, 500.00, 1560.80, 'cerrada', 'Ventas recuperadas'),
(NOW(), NULL, 500.00, NULL, 'abierta', 'Inicio de jornada actual');

-- Crear vistas útiles para reportes
CREATE OR REPLACE VIEW vista_stock_bajo AS
SELECT p.*,
       (p.stock_actual <= p.stock_minimo) as es_stock_bajo,
       (p.stock_actual = 0) as es_stock_agotado,
       (p.stock_actual * p.precio_compra) as valor_compra_total,
       (p.stock_actual * p.precio_venta) as valor_venta_total
FROM productos p
WHERE p.stock_actual <= p.stock_minimo OR p.stock_actual = 0
ORDER BY p.stock_actual ASC;

CREATE OR REPLACE VIEW vista_movimientos_diarios AS
SELECT DATE(fecha_movimiento) as fecha,
       COUNT(*) as total_movimientos,
       SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END) as total_entradas,
       SUM(CASE WHEN tipo = 'salida' THEN cantidad ELSE 0 END) as total_salidas,
       SUM(CASE WHEN tipo = 'entrada' THEN cantidad * 
           (SELECT precio_compra FROM productos WHERE id = m.producto_id) ELSE 0 END) as valor_entradas,
       SUM(CASE WHEN tipo = 'salida' THEN cantidad * 
           (SELECT precio_venta FROM productos WHERE id = m.producto_id) ELSE 0 END) as valor_salidas
FROM movimientos_inventario m
GROUP BY DATE(fecha_movimiento)
ORDER BY fecha DESC;

CREATE OR REPLACE VIEW vista_top_productos AS
SELECT p.id, p.nombre, p.codigo_barras,
       SUM(CASE WHEN m.tipo = 'salida' THEN m.cantidad ELSE 0 END) as total_vendido,
       COUNT(DISTINCT CASE WHEN m.tipo = 'salida' THEN DATE(m.fecha_movimiento) END) as dias_con_venta,
       SUM(CASE WHEN m.tipo = 'salida' THEN m.cantidad * p.precio_venta ELSE 0 END) as valor_total_vendido
FROM productos p
LEFT JOIN movimientos_inventario m ON p.id = m.producto_id
WHERE m.fecha_movimiento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY p.id, p.nombre, p.codigo_barras
ORDER BY total_vendido DESC;

-- Crear procedimientos almacenados
DELIMITER //

CREATE PROCEDURE sp_actualizar_precio_venta(IN porcentaje DECIMAL(5,2))
BEGIN
    UPDATE productos 
    SET precio_venta = precio_compra * (1 + (porcentaje / 100)),
        fecha_actualizacion = NOW()
    WHERE precio_compra > 0;
END //

CREATE PROCEDURE sp_generar_reporte_mensual(IN mes INT, IN anio INT)
BEGIN
    SELECT 
        p.nombre,
        p.codigo_barras,
        SUM(CASE WHEN m.tipo = 'entrada' THEN m.cantidad ELSE 0 END) as entradas,
        SUM(CASE WHEN m.tipo = 'salida' THEN m.cantidad ELSE 0 END) as salidas,
        p.stock_actual as stock_final,
        SUM(CASE WHEN m.tipo = 'salida' THEN m.cantidad * p.precio_venta ELSE 0 END) as valor_ventas
    FROM productos p
    LEFT JOIN movimientos_inventario m ON p.id = m.producto_id 
        AND MONTH(m.fecha_movimiento) = mes 
        AND YEAR(m.fecha_movimiento) = anio
    GROUP BY p.id, p.nombre, p.codigo_barras, p.stock_actual
    ORDER BY valor_ventas DESC;
END //

DELIMITER ;

-- Crear triggers para auditoría
DELIMITER //

CREATE TRIGGER tr_auditar_movimientos
AFTER INSERT ON movimientos_inventario
FOR EACH ROW
BEGIN
    -- Aquí se podría insertar en una tabla de auditoría
    -- Por ahora solo actualizamos el stock automáticamente
    IF NEW.tipo = 'entrada' THEN
        UPDATE productos 
        SET stock_actual = stock_actual + NEW.cantidad,
            fecha_actualizacion = NOW()
        WHERE id = NEW.producto_id;
    ELSEIF NEW.tipo = 'salida' THEN
        UPDATE productos 
        SET stock_actual = stock_actual - NEW.cantidad,
            fecha_actualizacion = NOW()
        WHERE id = NEW.producto_id;
    END IF;
END //

DELIMITER ;

-- Crear índices adicionales para mejorar rendimiento
CREATE INDEX idx_productos_stock_minimo ON productos(stock_minimo);
CREATE INDEX idx_productos_precio_venta ON productos(precio_venta);
CREATE INDEX idx_movimientos_tipo_fecha ON movimientos_inventario(tipo, fecha_movimiento);
CREATE INDEX idx_caja_fecha_apertura_cierre ON caja(fecha_apertura, fecha_cierre);

-- Mensaje de éxito
SELECT '✅ Base de datos creada exitosamente!' as mensaje;
SELECT CONCAT('📦 Productos: ', COUNT(*)) as resumen FROM productos;
SELECT CONCAT('📊 Movimientos: ', COUNT(*)) as resumen FROM movimientos_inventario;
SELECT CONCAT('💰 Registros de caja: ', COUNT(*)) as resumen FROM caja;