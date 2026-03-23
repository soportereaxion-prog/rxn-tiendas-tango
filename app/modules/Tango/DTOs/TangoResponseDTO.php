<?php

declare(strict_types=1);

namespace App\Modules\Tango\DTOs;

class TangoResponseDTO
{
    public bool $isSuccess = false;
    public array $payload = [];
    public ?string $errorMessage = null;
}
