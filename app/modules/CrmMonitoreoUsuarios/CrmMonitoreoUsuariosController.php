<?php

declare(strict_types=1);

namespace App\Modules\CrmMonitoreoUsuarios;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Usuarios\UsuarioService;

class CrmMonitoreoUsuariosController extends Controller
{
    /**
     * Muestra la pantalla principal de Monitoreo de Usuarios en el CRM
     */
    public function index(): void
    {
        AuthService::requireLogin();

        $usuarioService = new UsuarioService();
        
        // Obtener todos los usuarios del contexto de la empresa actual
        // No usaremos paginación todavía para el monitor ya que queremos ver el equipo completo
        $resultado = $usuarioService->findAllForContext([
            'status' => 'activos',
            'limit' => 1000 // Forzamos un limite alto internamente si es posible, aunque findAllForContext usa paginacion (PER_PAGE=10).
            // Para resolver esto simple para el CRM Monitor, vamos a llamar a un getAll o ajustar
        ]);
        
        // En UsuarioService, Mantenemos PER_PAGE o usamos algo que traiga todos. 
        // Como el findAllForContext tiene un param de page, por ahora le pasamos limit = no hay, pero devuelve la primer pagina (10).
        // Vamos a instanciar el repositorio directo para este caso específico del monitor así obtenemos todos de una, ya que la pantalla de monitoreo busca mostrar al equipo.
        $repo = new \App\Modules\Auth\UsuarioRepository();
        
        if (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1) {
            $usuarios = $repo->findAll();
        } else {
            $empresaId = \App\Core\Context::getEmpresaId();
            $usuarios = $repo->findAllByEmpresaId($empresaId);
        }

        // Decoramos los datos para la vista
        $currentUserId = $_SESSION['user_id'] ?? null;
        
        $usuariosDecorados = array_map(function($u) use ($currentUserId) {
            // Generar Iniciales para el Avatar
            $nombres = explode(' ', trim($u->nombre));
            $iniciales = '';
            if (count($nombres) >= 2) {
                $iniciales = mb_strtoupper(mb_substr($nombres[0], 0, 1) . mb_substr($nombres[1], 0, 1));
            } else {
                $iniciales = mb_strtoupper(mb_substr($nombres[0], 0, 2));
            }

            // Generar color base del avatar según ID para que sea consistente
            $colors = ['bg-primary', 'bg-success', 'bg-danger', 'bg-warning text-dark', 'bg-info text-dark', 'bg-secondary', 'bg-dark'];
            $colorClass = $colors[$u->id % count($colors)];

            // Determinar rol
            $rol = 'Operador / Vendedor';
            if ($u->es_rxn_admin == 1) {
                $rol = 'Soporte RXN (Global Administrador)';
            } elseif ($u->es_admin == 1) {
                $rol = 'Administrador de Empresa';
            }

            return [
                'id' => $u->id,
                'nombre' => $u->nombre,
                'email' => $u->email,
                'rol' => $rol,
                'iniciales' => $iniciales,
                'avatar_color' => $colorClass,
                'activo' => (bool)$u->activo,
                'is_current_user' => ($currentUserId && (int)$currentUserId === (int)$u->id),
                'anura_interno' => $u->anura_interno ?: 'Sin Asignar',
                'tango_perfil' => $u->tango_perfil_pedido_codigo ? "({$u->tango_perfil_pedido_codigo}) {$u->tango_perfil_pedido_nombre}" : 'Sin Vincular',
            ];
        }, $usuarios);

        View::render('app/modules/CrmMonitoreoUsuarios/views/index.php', [
            'usuarios' => $usuariosDecorados,
            'basePath' => '/mi-empresa/crm'
        ]);
    }
}
