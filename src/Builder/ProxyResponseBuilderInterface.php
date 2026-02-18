<?php

declare(strict_types=1);

namespace App\Builder;

use App\DTO\ProxyResponse;
use Symfony\Component\HttpFoundation\Response;

interface ProxyResponseBuilderInterface
{
    public function build(ProxyResponse $proxyResponse): Response;
}