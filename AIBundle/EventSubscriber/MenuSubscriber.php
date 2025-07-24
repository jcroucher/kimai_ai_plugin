<?php

declare(strict_types=1);

namespace KimaiPlugin\AIBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    private AuthorizationCheckerInterface $security;

    public function __construct(AuthorizationCheckerInterface $security)
    {
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $menu = $event->getSystemMenu();

        if ($menu === null) {
            return;
        }

        $menu->addChild(
            new MenuItemModel('ai_admin', 'AI Assistant', 'ai_admin', [], 'fas fa-robot')
        );
    }
}