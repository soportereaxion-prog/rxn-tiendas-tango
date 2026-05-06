<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\UserModuleAccessService;
use App\Modules\Empresas\EmpresaAccessService;

/**
 * Landing del módulo CrmMailMasivos con las 3 subsecciones: Envíos,
 * Reportes y Plantillas. Fase 3 (Plantillas) activa; Envíos queda
 * como "Fase 4" con badge hasta que se implemente el workflow de n8n.
 */
class MailMasivosDashboardController
{
    public function index(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');

        View::render('app/modules/CrmMailMasivos/views/dashboard.php', [
            'cards' => [
                [
                    'title' => 'Envíos',
                    'desc'  => 'Disparar envíos masivos, monitorear progreso, ver informes y tracking.',
                    'icon'  => 'bi-send-fill',
                    'link'  => '/mi-empresa/crm/mail-masivos/envios',
                    'badge' => null,
                ],
                [
                    'title' => 'Reportes',
                    'desc'  => 'Diseñar fuentes de destinatarios con el editor visual estilo Links, sin escribir SQL.',
                    'icon'  => 'bi-diagram-3',
                    'link'  => '/mi-empresa/crm/mail-masivos/reportes',
                    'badge' => null,
                ],
                [
                    'title' => 'Plantillas',
                    'desc'  => 'Armar plantillas HTML con variables, previsualización en vivo y botonera de variables del reporte asociado.',
                    'icon'  => 'bi-file-earmark-text-fill',
                    'link'  => '/mi-empresa/crm/mail-masivos/plantillas',
                    'badge' => null,
                ],
            ],
        ]);
    }
}
