<?php

namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user', name: 'user_')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    )
    {
    }

    #[Route('/dashboard', name: 'public_user_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('public/user/pages/dashboard.html.twig', [
            'title' => 'Dashboard',
        ]);
    }

    #[Route('/profile', name: 'public_user_profile')]
    public function profile(): Response
    {
        return $this->render('public/user/pages/profile.html.twig', [
            'title' => 'Profile',
        ]);
    }

    #[Route('/requests', name: 'public_user_requests')]
    public function requests(): Response
    {
        return $this->render('public/user/pages/requests.html.twig', [
            'title' => 'Requests',
        ]);
    }

    #[Route('/orders', name: 'public_user_orders')]
    public function orders(): Response
    {
        return $this->render('public/user/pages/orders.html.twig', [
            'title' => 'Orders',
        ]);
    }

    #[Route('/orders/{id}', name: 'public_user_order', methods: ['GET'])]
    public function order(string $id): Response
    {
        $order = $this->em->getRepository(Order::class)->find($id);
        // TODO: Implement order retrieval and validation
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        return $this->render('public/user/pages/order.html.twig', [
            'title' => 'Order',
            'order' => $order,
        ]);
    }
}
