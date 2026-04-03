<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Core\Services\MailService;
use App\Core\View;
use App\Modules\PrintForms\PrintFormRepository;
use App\Modules\PrintForms\PrintFormRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Modules\EmpresaConfig\EmpresaConfigRepository;

class DocumentMailerService
{
    private MailService $mailService;
    private EmpresaConfigRepository $configRepo;
    private PrintFormRepository $printRepo;
    private PrintFormRenderer $renderer;

    public function __construct(string $area = 'crm')
    {
        $this->mailService = MailService::forArea($area);
        $this->configRepo = EmpresaConfigRepository::forArea($area);
        $this->printRepo = new PrintFormRepository();
        $this->renderer = new PrintFormRenderer();
    }

    /**
     * Envia un correo con cuerpo y adjunto PDF generados dinámicamente con PrintForms
     *
     * @param int    $empresaId
     * @param string $to                 Destinatario
     * @param array  $contextData        Datos inyectables para PrintForms (ej. Cliente, Presupuesto, Items)
     * @param string $configPrefix       Prefijo en empresa_config ('presupuesto' o 'pds')
     * @param string $defaultDocumentKey Clave de documento por defecto si no hay template PDF seleccionado
     * @param string $defaultSubject     Asunto por defecto si no hay nada en configuracion
     * @param string $filename           Nombre deseado para el PDF adjunto (sin el .pdf)
     */
    public function sendDocument(
        int $empresaId,
        string $to,
        array $contextData,
        string $configPrefix,
        string $defaultDocumentKey,
        string $defaultSubject,
        string $filename,
        array $extraAttachments = []
    ): bool {
        $config = $this->configRepo->findByEmpresaId($empresaId);
        
        $bodyCanvasId = $config->{$configPrefix . '_email_body_canvas_id'} ?? null;
        $pdfCanvasId = $config->{$configPrefix . '_email_pdf_canvas_id'} ?? null;
        $subject = trim((string)($config->{$configPrefix . '_email_asunto'} ?? ''));
        if ($subject === '') {
            $subject = $defaultSubject;
        }
        
        // 1. Obtener HTML del cuerpo del correo
        $bodyHtml = '';
        if ($bodyCanvasId) {
            $bodyHtml = $this->renderCanvasToHtml($empresaId, (int)$bodyCanvasId, $contextData);
        } else {
            $bodyHtml = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #2c3e50;'>" . htmlspecialchars($subject) . "</h2>
                    <p>Adjunto a este correo encontrara su documento.</p>
                </div>
            ";
        }

        // 2. Obtener HTML del PDF
        $pdfHtml = '';
        if ($pdfCanvasId) {
            $pdfHtml = $this->renderCanvasToHtml($empresaId, (int)$pdfCanvasId, $contextData);
        } else {
            $pdfHtml = $this->renderCanvasByDocumentKeyToHtml($empresaId, $defaultDocumentKey, $contextData);
        }

        // 3. Generar PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultPaperSize', 'A4');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($pdfHtml);
        $dompdf->render();
        $pdfContent = $dompdf->output();

        // 4. Armar Attachments
        $attachments = [
            [
                'content' => $pdfContent,
                'name' => $filename . '.pdf',
                'type' => 'application/pdf',
                'encoding' => 'base64'
            ]
        ];

        if (!empty($extraAttachments)) {
            $attachments = array_merge($attachments, $extraAttachments);
        }

        // 5. Enviar usando MailService base
        return $this->mailService->send($to, $subject, $bodyHtml, $empresaId, $attachments);
    }

    private function renderCanvasToHtml(int $empresaId, int $canvasDefinitionId, array $contextData): string
    {
        try {
            $template = $this->printRepo->resolveTemplateByDefinitionId($empresaId, $canvasDefinitionId);
            return $this->buildHtmlString($template, $contextData);
        } catch (\Throwable $e) {
            // Fallback si falla la plantilla
            return "<p>Error renderizando plantilla de documento: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    private function renderCanvasByDocumentKeyToHtml(int $empresaId, string $documentKey, array $contextData): string
    {
        try {
            $template = $this->printRepo->resolveTemplateForDocument($empresaId, $documentKey);
            return $this->buildHtmlString($template, $contextData);
        } catch (\Throwable $e) {
            return "<p>Error renderizando documento base: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    private function buildHtmlString(array $template, array $contextData): string
    {
        $rendered = $this->renderer->buildDocument(
            $template['page_config'] ?? [],
            $template['objects'] ?? [],
            $contextData,
            (string) ($template['background_url'] ?? '')
        );

        return View::renderToString('app/modules/PrintForms/views/document_render.php', [
            'page' => $rendered['page'],
            'renderedObjects' => $rendered['objects'],
            'title' => 'Documento Generado',
            'subtitle' => 'Envio automatico de sistema',
            // Render limpio sin botones
            'autoPrint' => false,
            'printPath' => null,
            'backPath' => null,
            'hideToolbar' => true,
            'isEmailContext' => true
        ]);
    }
}
