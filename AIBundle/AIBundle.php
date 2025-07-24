<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AIBundle extends Bundle implements PluginInterface
{
    public function getPath(): string
    {
        return \dirname(__DIR__) . '/AIBundle';
    }
}