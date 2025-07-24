<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::STYLESHEET => ['onStylesheet', 100],
            ThemeEvent::JAVASCRIPT => ['onJavascript', 100],
        ];
    }

    public function onStylesheet(ThemeEvent $event): void
    {
        $event->addContent('<link rel="stylesheet" href="/bundles/ai/css/ai-chat.css">');
    }

    public function onJavascript(ThemeEvent $event): void
    {
        $event->addContent('<script src="/bundles/ai/js/ai-chat.js"></script>');
    }
}