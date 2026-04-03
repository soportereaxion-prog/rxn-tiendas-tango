<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\ModuleNoteService;
use Throwable;

class ModuleNotesController extends Controller
{
    private const MAX_ATTACHMENTS_PER_NOTE = 6;
    private const MAX_ATTACHMENT_SIZE = 5242880;

    public function index(): void
    {
        AuthService::requireRxnAdmin();

        $moduleNotesFlash = $_SESSION['module_notes_flash'] ?? null;
        unset($_SESSION['module_notes_flash']);

        View::render('app/modules/Admin/views/module_notes_index.php', [
            'modules' => ModuleNoteService::modules(),
            'totalNotes' => ModuleNoteService::totalNotes(),
            'flash' => $moduleNotesFlash,
        ]);
    }

    public function store(): void
    {
        AuthService::requireRxnAdmin();

        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? null);

        try {
            $attachmentLabels = $this->parseAttachmentLabels($_POST['attachment_labels_json'] ?? null);
            $attachments = $this->handleAttachmentUploads($_FILES['attachments'] ?? null, $attachmentLabels);

            ModuleNoteService::add(
                (string) ($_POST['module_key'] ?? ''),
                (string) ($_POST['module_label'] ?? ''),
                (string) ($_POST['type'] ?? 'idea'),
                (string) ($_POST['content'] ?? ''),
                (int) ($_SESSION['user_id'] ?? 0),
                (string) ($_SESSION['user_name'] ?? 'Administrador'),
                $attachments
            );

            $_SESSION['module_notes_flash'] = [
                'type' => 'success',
                'message' => $attachments !== []
                    ? 'Anotacion guardada con capturas en la bitacora del modulo.'
                    : 'Anotacion guardada en la bitacora del modulo.',
            ];
        } catch (Throwable $exception) {
            $_SESSION['module_notes_flash'] = [
                'type' => 'danger',
                'message' => $exception->getMessage(),
            ];
        }

        header('Location: ' . $returnTo);
        exit;
    }

    private function handleAttachmentUploads(mixed $attachments, array $labels): array
    {
        $files = $this->normalizeUploadedFiles($attachments);

        if ($files === []) {
            return [];
        }

        if (count($files) > self::MAX_ATTACHMENTS_PER_NOTE) {
            throw new \RuntimeException('Cada nota admite hasta ' . self::MAX_ATTACHMENTS_PER_NOTE . ' capturas.');
        }

        $allowedTypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $relativeDirectory = '/uploads/module-notes/' . date('Y/m');
        $absoluteDirectory = BASE_PATH . '/public' . $relativeDirectory;

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de capturas.');
        }

        $saved = [];

        foreach ($files as $index => $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('No se pudo subir una de las capturas adjuntas.');
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new \RuntimeException('El archivo temporal de una captura no es valido.');
            }

            $size = (int) ($file['size'] ?? 0);
            if ($size <= 0 || $size > self::MAX_ATTACHMENT_SIZE) {
                throw new \RuntimeException('Cada captura debe pesar entre 1 byte y 5 MB.');
            }

            $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName);
            if (!isset($allowedTypes[$mimeType])) {
                throw new \RuntimeException('Las capturas deben ser PNG, JPG, WEBP o GIF.');
            }

            $fileName = 'note_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
            $absolutePath = $absoluteDirectory . '/' . $fileName;

            if (!move_uploaded_file($tmpName, $absolutePath)) {
                throw new \RuntimeException('No se pudo guardar una captura en disco.');
            }

            $saved[] = [
                'label' => $this->normalizeAttachmentLabel($labels[$index] ?? '', $index + 1),
                'path' => $relativeDirectory . '/' . $fileName,
                'name' => basename((string) ($file['name'] ?? $fileName)),
            ];
        }

        return $saved;
    }

    private function normalizeUploadedFiles(mixed $attachments): array
    {
        if (!is_array($attachments) || !isset($attachments['error'])) {
            return [];
        }

        if (!is_array($attachments['error'])) {
            return [$attachments];
        }

        $files = [];
        $count = count($attachments['error']);

        for ($index = 0; $index < $count; $index += 1) {
            $files[] = [
                'name' => $attachments['name'][$index] ?? '',
                'type' => $attachments['type'][$index] ?? '',
                'tmp_name' => $attachments['tmp_name'][$index] ?? '',
                'error' => $attachments['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $attachments['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    private function parseAttachmentLabels(mixed $rawLabels): array
    {
        if (!is_string($rawLabels) || trim($rawLabels) === '') {
            return [];
        }

        $decoded = json_decode($rawLabels, true);
        if (!is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $label) {
            if (!is_string($label) || trim($label) === '') {
                continue;
            }

            $labels[] = trim($label);
        }

        return array_values($labels);
    }

    private function normalizeAttachmentLabel(string $label, int $fallbackIndex): string
    {
        $normalized = strtolower(trim($label));

        if (preg_match('/^#?imagen(\d+)$/', $normalized, $matches) === 1) {
            return '#imagen' . max(1, (int) ($matches[1] ?? 0));
        }

        return '#imagen' . max(1, $fallbackIndex);
    }

    private function sanitizeReturnTo(mixed $returnTo): string
    {
        if (!is_string($returnTo)) {
            return '/admin/notas-modulos';
        }

        $returnTo = trim($returnTo);

        if ($returnTo === '' || !str_starts_with($returnTo, '/')) {
            return '/admin/notas-modulos';
        }

        return $returnTo;
    }

    public function syncExport(): void
    {
        $expectedKey = $_ENV['SYNC_API_KEY'] ?? getenv('SYNC_API_KEY') ?? '';

        if ($expectedKey === '') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'API Key not configured on server']);
            exit;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (trim($authHeader) === '') {
            if (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
        }

        if (!str_starts_with($authHeader, 'Bearer ') || substr($authHeader, 7) !== $expectedKey) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $storagePath = BASE_PATH . '/app/storage/module_notes.json';

        header('Content-Type: application/json');
        
        if (!is_file($storagePath)) {
            echo json_encode(['modules' => []]);
            exit;
        }

        readfile($storagePath);
        exit;
    }
}
