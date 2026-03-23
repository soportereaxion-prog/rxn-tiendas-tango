<?php
declare(strict_types=1);

namespace App\Modules\Store\Services;

use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Articulos\ArticuloRepository;

class CartService
{
    private ArticuloRepository $articuloRepo;

    public function __construct()
    {
        $this->articuloRepo = new ArticuloRepository();
        
        // Inicializar bolsa de sesión si no existe
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    /**
     * Retorna los items del carrito para la empresa actual.
     */
    public function getItems(): array
    {
        $empresaId = PublicStoreContext::getEmpresaId();
        return $_SESSION['cart'][$empresaId] ?? [];
    }

    /**
     * Retorna el total del carrito para la empresa actual.
     */
    public function getTotal(): float
    {
        $total = 0.0;
        foreach ($this->getItems() as $item) {
            $total += ($item['precio_unitario'] * $item['cantidad']);
        }
        return $total;
    }

    /**
     * Agrega un ítem asegurando que pertenezca a la empresa y tomando un snapshot de precio.
     */
    public function addItem(int $articuloId, int $cantidad): bool
    {
        $empresaId = PublicStoreContext::getEmpresaId();
        
        // Validación estricta backend
        $articulo = $this->articuloRepo->findById($articuloId, $empresaId);
        
        if (!$articulo || !$articulo->activo) {
            return false;
        }

        // Definir precio a usar (Acá podríamos elegir L1 o L2 según config, pero usaremos el bruto o el que haya por defecto)
        // Como convención simple de esta iteración 1: el de primera lista o el genérico.
        $precioSnapshot = $articulo->precio_lista_1 ?? $articulo->precio ?? 0.0;

        if (!isset($_SESSION['cart'][$empresaId])) {
            $_SESSION['cart'][$empresaId] = [];
        }

        if (isset($_SESSION['cart'][$empresaId][$articuloId])) {
            $_SESSION['cart'][$empresaId][$articuloId]['cantidad'] += $cantidad;
            // Update snapshot porsiaca cambió en bd en este ínterin
            $_SESSION['cart'][$empresaId][$articuloId]['precio_unitario'] = $precioSnapshot;
            $_SESSION['cart'][$empresaId][$articuloId]['nombre'] = $articulo->nombre;
        } else {
            $_SESSION['cart'][$empresaId][$articuloId] = [
                'articulo_id' => $articuloId,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioSnapshot,
                'nombre' => $articulo->nombre
            ];
        }

        return true;
    }

    public function updateItem(int $articuloId, int $cantidad): void
    {
        $empresaId = PublicStoreContext::getEmpresaId();
        
        if ($cantidad <= 0) {
            $this->removeItem($articuloId);
            return;
        }

        if (isset($_SESSION['cart'][$empresaId][$articuloId])) {
            $_SESSION['cart'][$empresaId][$articuloId]['cantidad'] = $cantidad;
        }
    }

    public function removeItem(int $articuloId): void
    {
        $empresaId = PublicStoreContext::getEmpresaId();
        if (isset($_SESSION['cart'][$empresaId][$articuloId])) {
            unset($_SESSION['cart'][$empresaId][$articuloId]);
        }
    }

    public function clearCart(): void
    {
        $empresaId = PublicStoreContext::getEmpresaId();
        if (isset($_SESSION['cart'][$empresaId])) {
            unset($_SESSION['cart'][$empresaId]);
        }
    }

    /**
     * Hook vacío para futura iteración Checkout / Pasarela.
     */
    public function prepareCheckout(): mixed
    {
        $items = $this->getItems();
        if (empty($items)) {
            throw new \Exception("El carrito está vacío.");
        }
        
        // Aca se estructuraria o serializaria a DB (Pedidos)
        // ...
        
        return null; 
    }
}
