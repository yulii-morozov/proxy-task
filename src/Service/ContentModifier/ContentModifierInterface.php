<?php

declare(strict_types=1);

namespace App\Service\ContentModifier;

use App\DTO\ContentModificationRequest;

interface ContentModifierInterface
{
    public function modify(ContentModificationRequest $request): string;
}