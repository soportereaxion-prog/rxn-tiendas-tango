<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';

// Mock simple de CartService para no depender de PublicStoreContext ni Session en CLI
class MockCartService extends \App\Modules\Store\Services\CartService {
    private array $mockItems;
    private float $mockTotal;
    
    public function __construct(array $items, float $total) {
        $this->mockItems = $items;
        $this->mockTotal = $total;
    }
    public function getItems(): array { return $this->mockItems; }
    public function getTotal(): float { return $this->mockTotal; }
    public function clearCart(): void { echo "Carrito limpiado exitosamente.\n"; }
}

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) putenv($line);
    }
}

try {
    $db = App\Core\Database::getConnection();
    
    $empresa = $db->query("SELECT id, slug FROM empresas WHERE activa = 1 LIMIT 1")->fetch();
    if (!$empresa) die("No hay empresas activas.\n");
    
    $empresaId = (int) $empresa['id'];
    echo "Usando empresa ID: $empresaId ({$empresa['slug']})\n";

    $articulo = $db->query("SELECT id, nombre, precio, codigo_externo FROM articulos WHERE empresa_id = $empresaId AND activo = 1 LIMIT 1")->fetch();
    if (!$articulo) die("No hay articulos activos para la empresa $empresaId.\n");

    echo "Simulando carrito con Articulo: {$articulo['nombre']} (ID: {$articulo['id']})\n";

    $items = [
        $articulo['id'] => [
            'articulo_id' => $articulo['id'],
            'cantidad' => 2,
            'precio_unitario' => $articulo['precio'] ?: 100.0,
            'nombre' => $articulo['nombre']
        ]
    ];
    $total = 2 * ($articulo['precio'] ?: 100.0);

    $cartService = new MockCartService($items, $total);
    $checkoutService = new \App\Modules\Store\Services\CheckoutService($cartService);

    $clienteData = [
        'nombre' => 'Test',
        'apellido' => 'Automatizado',
        'email' => 'test' . time() . '@rxn.com',
        'documento' => '20' . time(),
        'telefono' => '1122334455',
        'direccion' => 'Calle Falsa 123',
        'localidad' => 'CABA',
        'provincia' => 'CABA',
        'codigo_postal' => '1000'
    ];

    echo "Procesando Checkout...\n";
    $result = $checkoutService->processCheckout($empresaId, $clienteData, "Pedido de prueba automático CLI.");

    echo "Resultado del Checkout:\n";
    print_r($result);
    
    // Verificamos DB final para el pedido
    $pedidoId = $result['pedido_web_id'];
    $pedidoRow = $db->query("SELECT * FROM pedidos_web WHERE id = $pedidoId")->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== Estado final del Pedido en DB ===\n";
    echo "ID: " . $pedidoRow['id'] . "\n";
    echo "Estado Tango: " . $pedidoRow['estado_tango'] . "\n";
    
    echo "Respuesta Tango cruda almacenada (es JSON válido?):\n";
    $jsonValid = is_array(json_decode((string)$pedidoRow['respuesta_tango'], true)) ? "SI" : "NO";
    echo "-> JSON Valido: $jsonValid\n";
    echo "-> Contenido:\n" . substr((string)$pedidoRow['respuesta_tango'], 0, 500) . "...\n";

    echo "\n=== Renglones del pedido ===\n";
    $renglones = $db->query("SELECT * FROM pedidos_web_renglones WHERE pedido_web_id = $pedidoId")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($renglones as $r) {
        echo "- {$r['cantidad']}x {$r['nombre_articulo']} a {$r['precio_unitario']}\n";
    }

    echo "\n✔ PRUEBA END-TO-END FINALIZADA EXITOSAMENTE.\n";

} catch (Exception $e) {
    echo "ERROR RUNNING TEST: " . $e->getMessage() . "\n";
}
