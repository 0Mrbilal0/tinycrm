<?php

namespace App\Controller;

use Stripe\Webhook;
use Stripe\StripeClient;
use App\Form\PaymentType;
use App\Entity\Transaction;
use UnexpectedValueException;
use App\Service\StripeService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Mime\Email;
use App\Repository\OffreRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransactionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/payment')]
class PaymentController extends AbstractController
{
    #[Route('/', name: 'payment')]
    public function index(
        Request $request,
        StripeService $stripeService,
        OffreRepository $offreRepository,
        ClientRepository $clientRepository,
        MailerInterface $mailer,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(PaymentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $offre = $offreRepository->findOneBy(['id' => $data['offre']->getId()]); // Offre à vendre (titre et montant)
            $clientEmail = $clientRepository->findOneBy(['id' => $data['client']->getId()])->getEmail();
            $link = $stripeService
                ->makePayment(
                    $this->getParameter('STRIPE_API_KEY_SECRET'),
                    $offre->getMontant(),
                    $offre->getTitre(),
                    $clientEmail
                );
            $email = (new Email())
                ->from('hello@tinycrm.app')
                ->to($clientEmail)
                ->priority(Email::PRIORITY_HIGH)
                ->subject('Merci de procéder au paiment de votre offre')
                ->html('<div style="background-color: #f4f4f4; padding: 20px; text-align: center;">
                <h1>Bonjour</h1><br><br>
                <p>Voici le lien pour effectuer le règlement de votre offre :</p><br>
                <a href="' . $link . '" target="_blank">Payer</a><br>
                <hr>
                <p>Ce lien est valable pour une durée Limitée. </p><br></div>
                ');
            $mailer->send($email);

            $transaction = new Transaction();
            $transaction->setClient($data['client'])
                ->setMontant($offre->getMontant())
                ->setStatut('En attente');

            $em->persist($transaction);
            $em->flush();
        }


        return $this->render('payment/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/success', name: 'payment_success')]
    public function success(): Response
    {
        new StripeClient($this->getParameter('STRIPE_API_KEY_SECRET'));
        $endpoint_secret = 'whsec_b57be25a4ceba7058407a0541887054d5c0b7075b2ee205fe28379ddd39f029d';

        $payload = @file_get_contents('php://input');
        // dd($_SERVER);
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        http_response_code(200);
        return $this->render('payment/success.html.twig', []);
    }

    #[Route('/cancel', name: 'payment_cancel')]
    public function cancel(): Response
    {
        return $this->render('payment/cancel.html.twig', []);
    }
}
