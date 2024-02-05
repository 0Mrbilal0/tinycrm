<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\StripeService;

class PaymentController extends AbstractController
{
    private ?string $apiKey = $this->getParameter('STRIPE_API_KEY_SECRET');

    #[Route('/payment', name: 'app_payment')]
    public function index(): Response
    {
        // makePayment($this->apiKey, 1000, 'test product');
        return $this->render('payment/index.html.twig', [
            'controller_name' => 'PaymentController',
        ]);
    }
}
