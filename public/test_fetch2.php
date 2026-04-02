<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/core/Context.php';
\App\Core\Context::initialize();

\ = 1;

\ = \App\Modules\EmpresaConfig\EmpresaConfigRepository::forCrm();
\ = \->findByEmpresaId(\);

\ = 7; // As per the screenshot

try {
    \ = new \App\Modules\Tango\Services\TangoProfileSnapshotService();
    \ = \->fetch(\, \);
    
    echo json_encode(['success' => true, 'data' => \], JSON_PRETTY_PRINT);
} catch (\Throwable \) {
    echo json_encode(['success' => false, 'message' => \->getMessage()]);
}

