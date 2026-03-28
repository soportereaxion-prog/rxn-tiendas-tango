<?php
declare(strict_types=1);

namespace App\Modules\Help;

use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;

class HelpController
{
    public function operational(): void
    {
        AuthService::requireLogin();

        $area = OperationalAreaService::resolveFromRequest();

        View::render('app/modules/Help/views/operational_help.php', [
            'userName' => $_SESSION['user_name'] ?? 'Usuario',
            'area' => $area,
            'dashboardPath' => OperationalAreaService::dashboardPath($area),
            'environmentLabel' => OperationalAreaService::environmentLabel($area),
        ]);
    }
}
