<?php

declare(strict_types=1);

namespace App\Service\CorsService;

use Symfony\Component\HttpFoundation\Response;

interface CorsServiceInterface
{
    public function addHeaders(Response $response): void;

    public function createOptionsResponse(): Response;
}