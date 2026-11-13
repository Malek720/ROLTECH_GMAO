<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_unread_count', [$this, 'getUnreadNotificationCount']),
            new TwigFunction('notification_count', [$this, 'getNotificationCount']),
        ];
    }

    public function getUnreadNotificationCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notificationRepository->countUnreadForUser($user) : 0;
    }

    public function getNotificationCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notificationRepository->countForUser($user) : 0;
    }
}
