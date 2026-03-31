<?php
declare(strict_types=1);

namespace App\Modules\PrintForms;

use App\Core\Context;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;
use finfo;

class PrintFormController
{
    private const MAX_BACKGROUND_SIZE = 8388608;
    private const ALLOWED_BACKGROUND_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private PrintFormRepository $repository;

    public function __construct()
    {
        $this->repository = new PrintFormRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $documents = [];
        foreach (PrintFormRegistry::documentsForArea(OperationalAreaService::AREA_CRM) as $document) {
            $definition = $this->repository->findDefinitionByDocumentKey($empresaId, (string) $document['document_key']);
            $activeVersion = $this->repository->findActiveVersionForDocument($empresaId, (string) $document['document_key']);

            $documents[] = array_merge($document, [
                'definition' => $definition,
                'active_version' => $activeVersion,
            ]);
        }

        View::render('app/modules/PrintForms/views/index.php', array_merge($this->buildUiContext(), [
            'documents' => $documents,
        ]));
    }

    public function edit(string $documentKey): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $document = PrintFormRegistry::document($documentKey);

        if ($document === null || ($document['area'] ?? '') !== OperationalAreaService::AREA_CRM) {
            Flash::set('danger', 'El formulario de impresion solicitado no existe para este entorno.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/formularios-impresion');
            exit;
        }

        $template = $this->repository->resolveTemplateForDocument($empresaId, $documentKey);
        $activeVersion = $template['active_version'] ?? null;
        $definition = $template['definition'] ?? null;
        $pageConfig = $template['page_config'] ?? ($document['default_page_config'] ?? []);
        $objects = $template['objects'] ?? ($document['default_objects'] ?? []);
        $fonts = $template['fonts'] ?? ['used' => []];
        $versions = $definition !== null ? $this->repository->findVersionsByDefinitionId((int) $definition['id']) : [];
        $backgroundUrl = (string) ($template['background_url'] ?? '');

        View::render('app/modules/PrintForms/views/editor.php', array_merge($this->buildUiContext(), [
            'document' => $document,
            'definition' => $definition,
            'activeVersion' => $activeVersion,
            'versions' => $versions,
            'pageConfig' => $pageConfig,
            'objects' => $objects,
            'fonts' => $fonts,
            'availableFonts' => PrintFormRegistry::availableFonts(),
            'variables' => $document['variables'] ?? [],
            'repeaters' => $document['repeaters'] ?? [],
            'sampleContext' => $document['sample_context'] ?? [],
            'backgroundUrl' => $backgroundUrl,
        ]));
    }

    public function update(string $documentKey): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $document = PrintFormRegistry::document($documentKey);

        if ($document === null || ($document['area'] ?? '') !== OperationalAreaService::AREA_CRM) {
            Flash::set('danger', 'El formulario de impresion solicitado no existe para este entorno.');
            header('Location: /rxnTiendasIA/public/mi-empresa/crm/formularios-impresion');
            exit;
        }

        $existingVersion = $this->repository->findActiveVersionForDocument($empresaId, $documentKey);
        $backgroundAssetId = isset($existingVersion['background_asset_id']) ? (int) $existingVersion['background_asset_id'] : null;

        if (!empty($_POST['clear_background'])) {
            $backgroundAssetId = null;
        }

        try {
            $pageConfig = $this->decodePostedJsonArray((string) ($_POST['page_config_json'] ?? ''), 'La configuracion de pagina no es valida.');
            $objects = $this->decodePostedJsonArray((string) ($_POST['objects_json'] ?? ''), 'Los objetos del canvas no tienen un formato valido.');
            $fonts = $this->decodePostedJsonArray((string) ($_POST['fonts_json'] ?? ''), 'La informacion de fuentes no es valida.');

            if (isset($_FILES['background_image']) && (int) ($_FILES['background_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $backgroundAssetId = $this->storeBackgroundAsset($empresaId, $_FILES['background_image']);
            }

            if (isset($pageConfig['background']) && is_array($pageConfig['background'])) {
                $pageConfig['background']['asset_id'] = $backgroundAssetId;
            }

            $result = $this->repository->saveVersion(
                $empresaId,
                $documentKey,
                (string) ($document['label'] ?? $documentKey),
                (string) ($document['description'] ?? ''),
                json_encode($pageConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($objects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($fonts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $backgroundAssetId,
                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                trim((string) ($_POST['version_notes'] ?? '')) !== '' ? trim((string) $_POST['version_notes']) : null
            );

            Flash::set('success', 'Canvas de impresion guardado correctamente como version #' . (int) ($result['version_number'] ?? 0) . '.');
        } catch (\RuntimeException $e) {
            Flash::set('danger', $e->getMessage());
        } catch (\Throwable $e) {
            Flash::set('danger', 'No se pudo guardar la definicion del formulario de impresion.');
        }

        header('Location: /rxnTiendasIA/public/mi-empresa/crm/formularios-impresion/' . rawurlencode($documentKey));
        exit;
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/rxnTiendasIA/public/mi-empresa/crm/formularios-impresion',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_CRM),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
            'moduleNotesKey' => 'crm_formularios_impresion',
            'moduleNotesLabel' => 'Formularios de Impresion CRM',
        ];
    }

    private function decodeJsonArray(?string $json, array $fallback): array
    {
        if (!is_string($json) || trim($json) === '') {
            return $fallback;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    private function decodePostedJsonArray(string $json, string $errorMessage): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException($errorMessage);
        }

        return $decoded;
    }

    private function storeBackgroundAsset(int $empresaId, array $file): int
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('No se pudo subir la imagen de fondo del canvas.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            throw new \RuntimeException('El archivo temporal del fondo no es valido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BACKGROUND_SIZE) {
            throw new \RuntimeException('La imagen de fondo debe pesar entre 1 byte y 8 MB.');
        }

        $mimeType = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        if (!isset(self::ALLOWED_BACKGROUND_MIME[$mimeType])) {
            throw new \RuntimeException('El fondo del canvas debe ser PNG, JPG o WEBP.');
        }

        $relativeDirectory = '/uploads/print-forms/' . $empresaId . '/backgrounds';
        $absoluteDirectory = BASE_PATH . '/public' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de fondos de impresion.');
        }

        $fileName = 'bg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . self::ALLOWED_BACKGROUND_MIME[$mimeType];
        $absolutePath = $absoluteDirectory . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new \RuntimeException('No se pudo guardar en disco la imagen de fondo.');
        }

        return $this->repository->createAsset(
            $empresaId,
            'background',
            basename((string) ($file['name'] ?? $fileName)),
            $relativeDirectory . '/' . $fileName,
            $mimeType,
            $size,
            null
        );
    }
}
