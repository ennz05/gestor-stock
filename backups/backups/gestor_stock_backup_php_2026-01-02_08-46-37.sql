-- Backup creado el: 2026-01-02 08:46:37
-- Base de datos: gestor_stock

SET FOREIGN_KEY_CHECKS=0;

--
-- Estructura de tabla para `caja`
--

CREATE TABLE `caja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `estado` enum('abierta','cerrada') DEFAULT 'abierta',
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fecha_apertura` (`fecha_apertura`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_cierre` (`fecha_cierre`),
  KEY `idx_caja_fecha_apertura_cierre` (`fecha_apertura`,`fecha_cierre`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `caja`
--

INSERT INTO `caja` VALUES('1', '2025-12-26 04:34:43', '2025-12-26 12:34:43', '500.00', '1250.50', 'cerrada', 'Jornada normal, ventas promedio');
INSERT INTO `caja` VALUES('2', '2025-12-27 04:34:43', '2025-12-27 12:34:43', '500.00', '1430.75', 'cerrada', 'Día con buena venta');
INSERT INTO `caja` VALUES('3', '2025-12-28 04:34:43', '2025-12-28 12:34:43', '500.00', '980.25', 'cerrada', 'Día con pocas ventas');
INSERT INTO `caja` VALUES('4', '2025-12-29 04:34:43', '2025-12-29 12:34:43', '500.00', '1670.30', 'cerrada', 'Fin de semana, excelente venta');
INSERT INTO `caja` VALUES('5', '2025-12-30 04:34:43', '2025-12-30 12:34:43', '500.00', '1120.60', 'cerrada', 'Ventas normales');
INSERT INTO `caja` VALUES('6', '2025-12-31 04:34:43', '2025-12-31 12:34:43', '500.00', '890.45', 'cerrada', 'Día lento');
INSERT INTO `caja` VALUES('7', '2026-01-01 04:34:43', '2026-01-01 12:34:43', '500.00', '1560.80', 'cerrada', 'Ventas recuperadas');
INSERT INTO `caja` VALUES('8', '2026-01-02 04:34:43', '2026-01-02 08:35:55', '500.00', '6000.00', 'cerrada', 'Inicio de jornada actual | CIERRE: ');
INSERT INTO `caja` VALUES('9', '2026-01-02 08:36:07', '2026-01-02 08:45:44', '700.00', '10000.00', 'cerrada', ' | CIERRE: ');
INSERT INTO `caja` VALUES('10', '2026-01-02 08:46:28', NULL, '800.00', NULL, 'abierta', '');

--
-- Estructura de tabla para `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`),
  KEY `idx_clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` VALUES('1', 'porcentaje_ganancia', '5', 'Porcentaje de ganancia aplicado al precio de compra para calcular precio de venta', '2026-01-02 04:43:47');
INSERT INTO `configuracion` VALUES('2', 'usuario_default', 'Administrador', 'Usuario por defecto para movimientos de inventario', '2026-01-02 04:34:43');

--
-- Estructura de tabla para `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario` varchar(100) DEFAULT 'Administrador',
  PRIMARY KEY (`id`),
  KEY `idx_producto_id` (`producto_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha_movimiento` (`fecha_movimiento`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_movimientos_tipo_fecha` (`tipo`,`fecha_movimiento`),
  CONSTRAINT `movimientos_inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movimientos_inventario`
--

INSERT INTO `movimientos_inventario` VALUES('1', '1', 'entrada', '100', 'Compra semanal', '2025-12-26 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('2', '2', 'entrada', '50', 'Compra semanal', '2025-12-26 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('3', '3', 'entrada', '30', 'Compra semanal', '2025-12-26 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('4', '1', 'salida', '50', 'Venta', '2025-12-27 04:34:43', 'Vendedor 1');
INSERT INTO `movimientos_inventario` VALUES('5', '2', 'salida', '20', 'Venta', '2025-12-27 04:34:43', 'Vendedor 1');
INSERT INTO `movimientos_inventario` VALUES('6', '4', 'entrada', '200', 'Compra mensual', '2025-12-28 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('7', '1', 'salida', '30', 'Venta', '2025-12-28 04:34:43', 'Vendedor 2');
INSERT INTO `movimientos_inventario` VALUES('8', '5', 'entrada', '80', 'Compra quincenal', '2025-12-29 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('9', '3', 'salida', '10', 'Venta', '2025-12-29 04:34:43', 'Vendedor 1');
INSERT INTO `movimientos_inventario` VALUES('10', '6', 'entrada', '70', 'Compra quincenal', '2025-12-30 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('11', '2', 'salida', '15', 'Venta', '2025-12-30 04:34:43', 'Vendedor 2');
INSERT INTO `movimientos_inventario` VALUES('12', '4', 'salida', '100', 'Venta', '2025-12-31 04:34:43', 'Vendedor 1');
INSERT INTO `movimientos_inventario` VALUES('13', '7', 'entrada', '50', 'Compra semanal', '2025-12-31 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('14', '1', 'ajuste', '5', 'Conteo físico', '2026-01-01 04:34:43', 'Supervisor');
INSERT INTO `movimientos_inventario` VALUES('15', '8', 'entrada', '90', 'Compra mensual', '2026-01-01 04:34:43', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('16', '4', 'salida', '20', 'danado', '2026-01-02 04:37:06', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('17', '4', 'entrada', '200', 'compra', '2026-01-02 04:37:34', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('18', '11', 'entrada', '100', 'compra', '2026-01-02 04:44:47', 'Administrador');
INSERT INTO `movimientos_inventario` VALUES('19', '11', 'salida', '14', 'venta', '2026-01-02 04:45:18', 'Administrador');

--
-- Estructura de tabla para `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_barras` varchar(100) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_compra` decimal(10,2) DEFAULT 0.00,
  `precio_venta` decimal(10,2) DEFAULT 0.00,
  `stock_actual` int(11) DEFAULT 0,
  `stock_minimo` int(11) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_barras` (`codigo_barras`),
  KEY `idx_codigo_barras` (`codigo_barras`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_stock` (`stock_actual`),
  KEY `idx_fecha_creacion` (`fecha_creacion`),
  KEY `idx_productos_stock_minimo` (`stock_minimo`),
  KEY `idx_productos_precio_venta` (`precio_venta`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` VALUES('1', '7501006558602', 'Coca-Cola 600ml', 'Refresco de cola en botella de 600ml', '12.50', '13.75', '50', '10', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('2', '7501055300909', 'Sabritas Original 45g', 'Papas fritas sabor original 45g', '8.00', '8.80', '30', '5', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('3', '7501032903308', 'Galletas Marías Gamesa', 'Galletas Marías paquete 612g', '25.00', '27.50', '20', '3', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('4', '7501001303930', 'Agua Bonafont 1L', 'Agua purificada botella 1 litro', '6.50', '7.15', '280', '20', '2026-01-02 04:34:43', '2026-01-02 04:37:34');
INSERT INTO `productos` VALUES('5', '7501006558620', 'Coca-Cola Sin Azúcar 600ml', 'Refresco de cola sin azúcar 600ml', '13.00', '14.30', '40', '8', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('6', '7501006558610', 'Coca-Cola Light 600ml', 'Refresco de cola light 600ml', '13.00', '14.30', '35', '7', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('7', '7501055312345', 'Sabritas Adobadas 45g', 'Papas fritas sabor adobadas 45g', '8.50', '9.35', '25', '5', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('8', '7501032912345', 'Galletas Emperador Chocolate', 'Galletas con chocolate 240g', '18.00', '19.80', '45', '9', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('9', '7501001312345', 'Agua Epura 1L', 'Agua natural botella 1 litro', '5.50', '6.05', '80', '15', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('10', '7501055323456', 'Ruffles Queso 45g', 'Papas fritas onduladas sabor queso 45g', '9.00', '9.90', '28', '6', '2026-01-02 04:34:43', '2026-01-02 04:34:43');
INSERT INTO `productos` VALUES('11', '7501001303931', 'Cocaaa', 'aa', '80.00', '84.00', '86', '0', '2026-01-02 04:42:39', '2026-01-02 04:45:18');

--
-- Estructura de tabla para `vista_movimientos_diarios`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_movimientos_diarios` AS select cast(`m`.`fecha_movimiento` as date) AS `fecha`,count(0) AS `total_movimientos`,sum(case when `m`.`tipo` = 'entrada' then `m`.`cantidad` else 0 end) AS `total_entradas`,sum(case when `m`.`tipo` = 'salida' then `m`.`cantidad` else 0 end) AS `total_salidas`,sum(case when `m`.`tipo` = 'entrada' then `m`.`cantidad` * (select `productos`.`precio_compra` from `productos` where `productos`.`id` = `m`.`producto_id`) else 0 end) AS `valor_entradas`,sum(case when `m`.`tipo` = 'salida' then `m`.`cantidad` * (select `productos`.`precio_venta` from `productos` where `productos`.`id` = `m`.`producto_id`) else 0 end) AS `valor_salidas` from `movimientos_inventario` `m` group by cast(`m`.`fecha_movimiento` as date) order by cast(`m`.`fecha_movimiento` as date) desc;

--
-- Volcado de datos para la tabla `vista_movimientos_diarios`
--

INSERT INTO `vista_movimientos_diarios` VALUES('2026-01-02', '4', '300', '34', '9300.00', '1319.00');
INSERT INTO `vista_movimientos_diarios` VALUES('2026-01-01', '2', '90', '0', '1620.00', '0.00');
INSERT INTO `vista_movimientos_diarios` VALUES('2025-12-31', '2', '50', '100', '425.00', '715.00');
INSERT INTO `vista_movimientos_diarios` VALUES('2025-12-30', '2', '70', '15', '910.00', '132.00');
INSERT INTO `vista_movimientos_diarios` VALUES('2025-12-29', '2', '80', '10', '1040.00', '275.00');
INSERT INTO `vista_movimientos_diarios` VALUES('2025-12-28', '2', '200', '30', '1300.00', '412.50');
INSERT INTO `vista_movimientos_diarios` VALUES('2025-12-27', '2', '0', '70', '0.00', '863.50');
INSERT INTO `vista_movimientos_diarios` VALUES('2025-12-26', '3', '180', '0', '2400.00', '0.00');

--
-- Estructura de tabla para `vista_stock_bajo`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_stock_bajo` AS select `p`.`id` AS `id`,`p`.`codigo_barras` AS `codigo_barras`,`p`.`nombre` AS `nombre`,`p`.`descripcion` AS `descripcion`,`p`.`precio_compra` AS `precio_compra`,`p`.`precio_venta` AS `precio_venta`,`p`.`stock_actual` AS `stock_actual`,`p`.`stock_minimo` AS `stock_minimo`,`p`.`fecha_creacion` AS `fecha_creacion`,`p`.`fecha_actualizacion` AS `fecha_actualizacion`,`p`.`stock_actual` <= `p`.`stock_minimo` AS `es_stock_bajo`,`p`.`stock_actual` = 0 AS `es_stock_agotado`,`p`.`stock_actual` * `p`.`precio_compra` AS `valor_compra_total`,`p`.`stock_actual` * `p`.`precio_venta` AS `valor_venta_total` from `productos` `p` where `p`.`stock_actual` <= `p`.`stock_minimo` or `p`.`stock_actual` = 0 order by `p`.`stock_actual`;

--
-- Volcado de datos para la tabla `vista_stock_bajo`
--


--
-- Estructura de tabla para `vista_top_productos`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_top_productos` AS select `p`.`id` AS `id`,`p`.`nombre` AS `nombre`,`p`.`codigo_barras` AS `codigo_barras`,sum(case when `m`.`tipo` = 'salida' then `m`.`cantidad` else 0 end) AS `total_vendido`,count(distinct case when `m`.`tipo` = 'salida' then cast(`m`.`fecha_movimiento` as date) end) AS `dias_con_venta`,sum(case when `m`.`tipo` = 'salida' then `m`.`cantidad` * `p`.`precio_venta` else 0 end) AS `valor_total_vendido` from (`productos` `p` left join `movimientos_inventario` `m` on(`p`.`id` = `m`.`producto_id`)) where `m`.`fecha_movimiento` >= current_timestamp() - interval 30 day group by `p`.`id`,`p`.`nombre`,`p`.`codigo_barras` order by sum(case when `m`.`tipo` = 'salida' then `m`.`cantidad` else 0 end) desc;

--
-- Volcado de datos para la tabla `vista_top_productos`
--

INSERT INTO `vista_top_productos` VALUES('4', 'Agua Bonafont 1L', '7501001303930', '120', '2', '858.00');
INSERT INTO `vista_top_productos` VALUES('1', 'Coca-Cola 600ml', '7501006558602', '80', '2', '1100.00');
INSERT INTO `vista_top_productos` VALUES('2', 'Sabritas Original 45g', '7501055300909', '35', '2', '308.00');
INSERT INTO `vista_top_productos` VALUES('11', 'Cocaaa', '7501001303931', '14', '1', '1176.00');
INSERT INTO `vista_top_productos` VALUES('3', 'Galletas Marías Gamesa', '7501032903308', '10', '1', '275.00');
INSERT INTO `vista_top_productos` VALUES('6', 'Coca-Cola Light 600ml', '7501006558610', '0', '0', '0.00');
INSERT INTO `vista_top_productos` VALUES('5', 'Coca-Cola Sin Azúcar 600ml', '7501006558620', '0', '0', '0.00');
INSERT INTO `vista_top_productos` VALUES('8', 'Galletas Emperador Chocolate', '7501032912345', '0', '0', '0.00');
INSERT INTO `vista_top_productos` VALUES('7', 'Sabritas Adobadas 45g', '7501055312345', '0', '0', '0.00');

SET FOREIGN_KEY_CHECKS=1;
