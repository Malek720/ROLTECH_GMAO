<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    #[Route('', name: 'notifications', methods: ['GET'])]
    public function index(NotificationRepository $repository): Response
    {
        return $this->render('notification/index.html.twig', [
            'notifications' => $repository->findForUser($this->getUserOrFail()),
        ]);
    }

    #[Route('/{id}/read', name: 'notification_read', methods: ['POST'])]
    public function read(Request $request, Notification $notification, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUserOrFail();
        if ($notification->getRecipient() !== $user) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('notification_read_'.$notification->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $notification->setReadAt(new \DateTime());
        $entityManager->flush();

        return $this->redirectToRoute('notifications');
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(Request $request, NotificationRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUserOrFail();
        if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        foreach ($repository->findForUser($user) as $notification) {
            if ($notification->getReadAt() === null) {
                $notification->setReadAt(new \DateTime());
            }
        }
        $entityManager->flush();
        $this->addFlash('success', 'Toutes les notifications sont marquées comme lues.');

        return $this->redirectToRoute('notifications');
    }

    private function getUserOrFail(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
