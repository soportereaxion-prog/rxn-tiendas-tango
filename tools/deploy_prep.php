<?php

/**
 * Script de preparación de build y paquetización de base de datos para despliegues.
 * Genera dos estructuras limpias:
 * 1. /build - Con el sistema listo para producción (Document Root = public/).
 * 2. /deploy_db - Con los scripts SQL y migraciones necesarios.
 *
 * ESTRATEGIA DE DEPLOY: Estrategia B
 * - El Document Root del subdominio en Plesk/Apache apunta DIRECTAMENTE a /build/public/
 * - Las URLs son absolutas desde root (/login, /mi-empresa, etc.)
 * - NO se usa RewriteBase con subpath
 * - NO hay rutas hardcodeadas a /rxn_suite/public
 */

$baseDir = dirname(__DIR__);
$buildDir = $baseDir . '/build';
$dbDir = $baseDir . '/deploy_db';

// Eliminar directorios antiguos si existen
function removeDirectory($dir) {
    if (!file_exists($dir)) return;
    $iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}

removeDirectory($buildDir);
removeDirectory($dbDir);

mkdir($buildDir, 0777, true);
mkdir($dbDir, 0777, true);

echo ">> Directorios /build y /deploy_db creados.\n";

// --- 1. PREPARACIÓN DE BASE DE DATOS (/deploy_db) ---
$dbFiles = [
    'database/schema.sql',
    'database/seeds.sql',
    'db_add_cantidad.php',
    'update_db_users.php'
];

// Identificar archivos de migración
foreach (glob($baseDir . '/database_migrations_*.php') as $migArchivo) {
    $dbFiles[] = basename($migArchivo);
}

$dbReadme = "=================================================\n";
$dbReadme .= " PAQUETE DE BASE DE DATOS PARA DESPLIEGUE\n";
$dbReadme .= "=================================================\n\n";
$dbReadme .= "Archivos provistos:\n";
$dbReadme .= "- schema.sql: Estructura inicial y tablas del sistema.\n";
$dbReadme .= "- seeds.sql: Datos iniciales o de semilla (si existieran).\n";
$dbReadme .= "- database_migrations_*.php: Scripts de actualización de estructura para uso en consola.\n";
$dbReadme .= "- db_add_cantidad.php / update_db_users.php: Scripts utilitarios específicos de BD.\n";
file_put_contents($dbDir . '/README.txt', $dbReadme);

foreach ($dbFiles as $dbFile) {
    $src = $baseDir . '/' . $dbFile;
    if (file_exists($src)) {
        copy($src, $dbDir . '/' . basename($dbFile));
        echo "Copiado a deploy_db: " . basename($dbFile) . "\n";
    }
}

// --- 2. PREPARACIÓN DE BUILD (/build) ---
$whitelistRegex = '/^(' . implode('|', [
    'app',
    'public',
    'storage',
    'vendor',
    '\.env\.example',
    'composer\.json',
    'composer\.lock',
    '\.htaccess'
]) . ')/i';

$publicExclusionsRegex = '/^(test_.*|db-debug.*|cli_.*|dump\.php|get_db\.php|db-update\.php|migrate_pedidos\.php|tmp_.*|tango_.*\.log|tango_.*\.txt|.*\.bak|.*\.old)$/i';

$iterator = new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

$count = 0;
foreach ($files as $file) {
    if (str_starts_with($file->getRealPath(), $buildDir) || str_starts_with($file->getRealPath(), $dbDir)) {
        continue;
    }
    
    $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getRealPath());
    $relativePathNormalized = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

    // Filtrar mediante whitelist raices
    if (!preg_match($whitelistRegex, $relativePathNormalized)) {
        continue;
    }

    // Reglas de exclusión dentro de public (archivos experimentales, tests)
    if (str_starts_with($relativePathNormalized, 'public/')) {
        if ($file->isFile() && preg_match($publicExclusionsRegex, $file->getFilename())) {
            continue;
        }
    }

    // Reglas de exclusión para storage (no copiar json ni logs del entorno dev)
    if (str_starts_with($relativePathNormalized, 'storage/')) {
        if ($file->isFile() && preg_match('/.*(json|log|txt)$/i', $file->getFilename())) {
            continue; // Excluye dumps y caches JSON y archivos log/texto de storage
        }
    }

    // CORRECCIÓN PSR-4 PARA LINUX: Forzar casing estricto en rutas base.
    // NTFS (Windows) a veces reporta la carpeta con mayúsculas/minúsculas diferentes a git.
    $pathParts = explode('/', $relativePathNormalized);
    if (isset($pathParts[0])) {
        $base = strtolower($pathParts[0]);
        if (in_array($base, ['app', 'public', 'storage', 'vendor'])) {
            $pathParts[0] = $base;
        }
    }

    if (isset($pathParts[0]) && $pathParts[0] === 'app') {
        if (isset($pathParts[1])) {
            $sub = strtolower($pathParts[1]);
            // Forzar en minúsculas las carpetas base de app/
            if (in_array($sub, ['core', 'config', 'modules', 'shared', 'storage'])) {
                $pathParts[1] = $sub;
                
                // Forzar TitleCase para las subcarpetas de modules (convención PSR-4 del proyecto)
                if ($sub === 'modules' && isset($pathParts[2])) {
                    $pathParts[2] = ucfirst($pathParts[2]);
                }
                
                // Forzar TitleCase para las subcarpetas de shared que lo requieran
                if ($sub === 'shared' && isset($pathParts[2])) {
                    $lowerP2 = strtolower($pathParts[2]);
                    if (in_array($lowerP2, ['views', 'middleware'])) {
                        $pathParts[2] = $lowerP2;
                    } else {
                        $pathParts[2] = ucfirst($pathParts[2]);
                    }
                }
            } elseif ($sub === 'infrastructure') {
                $pathParts[1] = 'Infrastructure'; // Capital I for PSR-4
            }
        }
    }
    // Reconstruir la ruta relativa corregida
    $relativePath = implode(DIRECTORY_SEPARATOR, $pathParts);

    $dest = $buildDir . DIRECTORY_SEPARATOR . $relativePath;

    if ($file->isDir()) {
        if (!file_exists($dest)) {
            mkdir($dest, 0777, true);
        }
    } else {
        $destDir = dirname($dest);
        if (!file_exists($destDir)) {
            mkdir($destDir, 0777, true);
        }
        copy($file->getRealPath(), $dest);
        $count++;
    }
}

echo ">> $count archivos copiados al /build.\n";

// --- 3. POST-PROCESO: LIMPIEZA DE REFERENCIAS LEGACY EN /build ---
// Garantiza que NO queden referencias a /rxn_suite/public en ningún archivo del build.
// El código fuente ya debería estar limpio, pero esto es una red de seguridad.
echo "\n>> Iniciando post-proceso de limpieza de referencias legacy...\n";

$legacyPatterns = [
    '/rxn_suite/public'  => '',   // /rxn_suite/public/login -> /login
    '/rxnTiendasIA/public' => '',  // patron antiguo por si quedó algo
];

$extensionsToClean = ['php', 'js', 'css', 'html', 'htaccess'];
$cleanCount = 0;
$cleanFiles = [];

$buildIterator = new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS);
$buildFiles = new RecursiveIteratorIterator($buildIterator);

foreach ($buildFiles as $buildFile) {
    if (!$buildFile->isFile()) continue;
    
    $ext = strtolower($buildFile->getExtension());
    // .htaccess no tiene extension en getExtension(), se detecta por nombre
    $isHtaccess = $buildFile->getFilename() === '.htaccess';
    
    if (!in_array($ext, $extensionsToClean) && !$isHtaccess) continue;
    
    $content = file_get_contents($buildFile->getRealPath());
    $originalContent = $content;
    
    foreach ($legacyPatterns as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($buildFile->getRealPath(), $content);
        $cleanCount++;
        $cleanFiles[] = str_replace($buildDir . DIRECTORY_SEPARATOR, '', $buildFile->getRealPath());
    }
}

if ($cleanCount > 0) {
    echo ">> [ADVERTENCIA] Se encontraron y limpiaron $cleanCount archivo(s) con referencias legacy:\n";
    foreach ($cleanFiles as $f) {
        echo "   - $f\n";
    }
    echo ">> ACCION REQUERIDA: Revisar el codigo fuente en app/ y buscar las referencias\n";
    echo "   que originaron estas entradas. El codigo fuente debe estar limpio.\n";
} else {
    echo ">> [OK] Sin referencias legacy detectadas en el build. El codigo fuente esta limpio.\n";
}

// --- 4. VERIFICAR Y CORREGIR .htaccess del build ---
// Garantizar que ambos .htaccess sean correctos para estrategia B
$rootHtaccess = $buildDir . '/.htaccess';
$publicHtaccess = $buildDir . '/public/.htaccess';

// public/.htaccess: debe tener RewriteBase /, NO /rxn_suite/public/
if (file_exists($publicHtaccess)) {
    $htContent = file_get_contents($publicHtaccess);
    if (str_contains($htContent, 'RewriteBase /rxn_suite') || str_contains($htContent, 'RewriteBase /rxnTiendasIA')) {
        // Corregir
        $htContent = preg_replace('/RewriteBase\s+\/rxn[^\s\n]*/i', 'RewriteBase /', $htContent);
        file_put_contents($publicHtaccess, $htContent);
        echo ">> [CORREGIDO] public/.htaccess: RewriteBase corregido a /\n";
    } else {
        echo ">> [OK] public/.htaccess: RewriteBase correcto.\n";
    }
}

echo "\n>> BUILD LISTO PARA DEPLOY\n";
echo "===========================================\n";
echo "INSTRUCCIONES DE DEPLOY EN PLESK:\n";
echo "1. Subir el CONTENIDO de /build al servidor (no la carpeta build/ en si misma)\n";
echo "2. Configurar Document Root del subdominio apuntando a: {raiz_proyecto}/public\n";
echo "3. Copiar .env.example como .env y configurar las variables de produccion:\n";
echo "   APP_ENV=production\n";
echo "   APP_DEBUG=false\n";
echo "   APP_SESSION_SECURE=true\n";
echo "   DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT\n";
echo "4. En el servidor remoto, ejecutar: composer dump-autoload -o\n";
echo "5. Verificar permisos de uploads/: chmod 755 public/uploads\n";
echo "===========================================\n";
