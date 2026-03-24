<?php
declare(strict_types=1);

namespace App\Modules\ClientesWeb\Controllers;

use App\Core\View;
use App\Modules\ClientesWeb\ClienteWebRepository;
use App\Modules\ClientesWeb\Services\ClienteTangoLookupService;
use App\Core\Database;
use App\Modules\Auth\AuthService;
use App\Core\Context;
use Exception;

class ClienteWebController
{
    private ClienteWebRepository $repository;

    public function __construct()
    {
        $this->repository = new ClienteWebRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int)Context::getEmpresaId();
        
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 20;
        $search = trim($_GET['search'] ?? '');
        $sort = $_GET['sort'] ?? 'id';
        $dir = $_GET['dir'] ?? 'DESC';

        $clientes = $this->repository->findAllPaginated($empresaId, $page, $limit, $search, $sort, $dir);
        $total = $this->repository->countAll($empresaId, $search);
        $totalPages = ceil($total / $limit) ?: 1;

        View::render('app/modules/ClientesWeb/views/index.php', [
            'clientes' => $clientes,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }

    public function edit(int $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int)Context::getEmpresaId();
        
        $cliente = $this->repository->findById($id, $empresaId);

        if (!$cliente) {
            $_SESSION['flash_error'] = "Cliente no encontrado.";
            header('Location: /rxnTiendasIA/public/mi-empresa/clientes');
            exit;
        }

        View::render('app/modules/ClientesWeb/views/edit.php', [
            'cliente' => $cliente
        ]);
    }

    public function update(int $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int)Context::getEmpresaId();
        
        $cliente = $this->repository->findById($id, $empresaId);

        if (!$cliente) {
            header('Location: /rxnTiendasIA/public/mi-empresa/clientes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'apellido' => trim($_POST['apellido'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'documento' => trim($_POST['documento'] ?? ''),
                'razon_social' => trim($_POST['razon_social'] ?? ''),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'localidad' => trim($_POST['localidad'] ?? ''),
                'provincia' => trim($_POST['provincia'] ?? ''),
                'codigo_postal' => trim($_POST['codigo_postal'] ?? ''),
                'codigo_tango' => trim($_POST['codigo_tango'] ?? ''),
                'activo' => isset($_POST['activo']) ? 1 : 0
            ];

            $this->repository->update($id, $data);
            $_SESSION['flash_success'] = "Cliente web actualizado correctamente.";
            header("Location: /rxnTiendasIA/public/mi-empresa/clientes/$id/editar");
            exit;
        }
    }

    public function validarTango(int $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int)Context::getEmpresaId();
        
        $cliente = $this->repository->findById($id, $empresaId);

        if (!$cliente) {
            $_SESSION['flash_error'] = "Cliente no encontrado.";
            header('Location: /rxnTiendasIA/public/mi-empresa/clientes');
            exit;
        }

        $codigoTango = trim($_POST['codigo_tango'] ?? $cliente['codigo_tango'] ?? '');
        
        if (empty($codigoTango)) {
            $_SESSION['flash_error'] = "Primero debes guardar un Código Tango antes de validar.";
            header("Location: /rxnTiendasIA/public/mi-empresa/clientes/$id/editar");
            exit;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = :emp_id LIMIT 1");
            $stmt->execute(['emp_id' => $empresaId]);
            $config = $stmt->fetch();

            if (!$config || empty($config['tango_connect_token']) || empty($config['tango_connect_key'])) {
                throw new Exception("Configuración de Tango incompleta para la empresa.");
            }

            $apiUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $config['tango_connect_key']), '/');
            
            $lookupService = new ClienteTangoLookupService($apiUrl, $config['tango_connect_token'], (string)$config['tango_connect_company_id']);
            $tangoData = $lookupService->findByCodigo($codigoTango);

            if (!$tangoData) {
                $_SESSION['flash_error'] = "El cliente con código '{$codigoTango}' NO fue encontrado en Tango.";
                
                // Si el operario intentó validar un nuevo código en vuelo sin guardar, lo guardamos para que no se pierda el input
                if ($cliente['codigo_tango'] !== $codigoTango) {
                    $pdoStmt = $pdo->prepare("UPDATE clientes_web SET codigo_tango = :cod WHERE id = :id");
                    $pdoStmt->execute(['cod' => $codigoTango, 'id' => $id]);
                }
            } else {
                $tangoData['codigo_tango'] = $codigoTango;
                $this->repository->updateTangoData($id, $tangoData);
                $_SESSION['flash_success'] = "Cliente resuelto correctamente en Tango. (ID_GVA14: {$tangoData['id_gva14_tango']})";
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error validando en Tango: " . $e->getMessage();
        }

        header("Location: /rxnTiendasIA/public/mi-empresa/clientes/$id/editar");
        exit;
    }
}
