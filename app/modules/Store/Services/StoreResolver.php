<?php
declare(strict_types=1);

namespace App\Modules\Store\Services;

use App\Core\Database;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\EmpresaConfig\EmpresaConfigRepository;

class StoreResolver
{
    /**
     * Valida y resuelve el ecosistema de la Tienda desde un request
     * (actualmente por slug, extensible).
     * 
     * @return bool false si la tienda no existe o está inactiva.
     */
    public static function resolveEmpresaPublica(string $slug): bool
    {
        $db = Database::getConnection();
        
        // Buscamos empresa por slug normalizado
        $stmt = $db->prepare("SELECT * FROM empresas WHERE slug = :slug AND activa = 1 AND modulo_tiendas = 1");
        $stmt->execute([':slug' => $slug]);
        $empresa = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$empresa) {
            return false;
        }

        // Recuperar configuraciones extra si aplican
        $configRepo = new EmpresaConfigRepository();
        $config = $configRepo->findByEmpresaId((int)$empresa['id']);
        
        // Transformar objeto $config a un simple array
        $configArr = [];
        if ($config) {
            $configArr = [
                'deposito_codigo' => $config->deposito_codigo,
                'lista_precio_1' => $config->lista_precio_1,
                'lista_precio_2' => $config->lista_precio_2
            ];
        }

        // Inyectar estado estático de lectura general global para el ciclo de vida del router
        PublicStoreContext::init((int)$empresa['id'], $empresa, $configArr);
        
        return true;
    }
}
