<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$termino = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

if (empty($termino) || strlen($termino) < 2) {
    echo json_encode([]);
    exit;
}

$query = "SELECT id, nombre, codigo_barras, precio_venta, stock_actual 
         FROM productos 
         WHERE codigo_barras LIKE :codigo 
            OR nombre LIKE :nombre 
            AND stock_actual > 0
         ORDER BY 
            CASE 
                WHEN codigo_barras = :codigo_exacto THEN 1
                WHEN nombre LIKE :nombre_exacto THEN 2
                WHEN nombre LIKE :nombre_inicio THEN 3
                ELSE 4
            END,
            nombre ASC
         LIMIT :limit";

$stmt = $db->prepare($query);

$codigo_like = "%$termino%";
$nombre_like = "%$termino%";
$nombre_exacto = $termino;
$nombre_inicio = "$termino%";
$codigo_exacto = $termino;

$stmt->bindParam(':codigo', $codigo_like);
$stmt->bindParam(':nombre', $nombre_like);
$stmt->bindParam(':codigo_exacto', $codigo_exacto);
$stmt->bindParam(':nombre_exacto', $nombre_exacto);
$stmt->bindParam(':nombre_inicio', $nombre_inicio);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formatear resultados para autocompletado
$sugerencias = [];
foreach ($resultados as $producto) {
    $sugerencias[] = [
        'id' => $producto['id'],
        'nombre' => $producto['nombre'],
        'codigo_barras' => $producto['codigo_barras'],
        'precio' => $producto['precio_venta'],
        'stock' => $producto['stock_actual'],
        'display' => "{$producto['nombre']} ({$producto['codigo_barras']}) - $" . number_format($producto['precio_venta'], 2)
    ];
}

echo json_encode($sugerencias);
?>