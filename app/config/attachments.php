<?php

declare(strict_types=1);

/**
 * Config de adjuntos polimórficos (attachments).
 *
 * Usado por App\Core\Services\AttachmentService y App\Core\UploadValidator::anyFile().
 *
 * Owner types permitidos: whitelist estricta. Agregar acá cuando un módulo nuevo
 * empiece a usar attachments (la lista se usa también en AttachmentService como guard).
 */

return [
    'max_files_per_owner'       => 10,
    'max_file_size_bytes'       => 100 * 1024 * 1024,   // 100 MB por archivo
    'max_total_bytes_per_owner' => 100 * 1024 * 1024,   // 100 MB acumulado por owner

    'allowed_owner_types' => [
        'crm_nota',
        'crm_presupuesto',
        'crm_hora',
    ],

    // MIME real detectado por finfo => extensión canónica que grabamos.
    // Si un archivo llega con MIME fuera de esta lista, se rechaza.
    'allowed_mime_to_ext' => [
        // documentos
        'application/pdf'                                                                  => 'pdf',
        'application/msword'                                                               => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'          => 'docx',
        'application/vnd.ms-excel'                                                         => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'                => 'xlsx',
        'application/vnd.ms-powerpoint'                                                    => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'        => 'pptx',
        'application/vnd.oasis.opendocument.text'                                          => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet'                                   => 'ods',
        'application/rtf'                                                                  => 'rtf',
        'text/plain'                                                                       => 'txt',
        'text/csv'                                                                         => 'csv',

        // imágenes
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/bmp'  => 'bmp',

        // comprimidos
        'application/zip'              => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/vnd.rar'          => 'rar',
        'application/x-7z-compressed'  => '7z',

        // audio / video
        'video/mp4'    => 'mp4',
        'audio/mpeg'   => 'mp3',
        'audio/wav'    => 'wav',
        'audio/x-wav'  => 'wav',
        'audio/wave'   => 'wav',
    ],

    // Blacklist dura por extensión del NOMBRE ORIGINAL.
    // Es la segunda red: aunque el MIME pase la whitelist, si el nombre termina
    // en una de estas extensiones se rechaza (anti-polyglot).
    'blocked_extensions' => [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar',
        'pl', 'py', 'rb', 'jsp', 'asp', 'aspx', 'cgi',
        'sh', 'bash', 'zsh', 'bat', 'cmd', 'ps1', 'com', 'msi', 'exe', 'dll',
        'htaccess', 'htpasswd',
        'htm', 'html', 'xhtml', 'svg',
        'js', 'mjs', 'jsx', 'vbs', 'vbe', 'wsf', 'wsh',
    ],

    // Raíz absoluta donde se guardan los archivos. Se arma como:
    // {project_root}/public/uploads/empresas/{empresa_id}/attachments/Y/m/{stored_name}
    'storage_root_relative' => 'public/uploads/empresas',
];
