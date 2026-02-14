<?php

namespace App\Services;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

class PaymentService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function createPreference($plan, $user)
    {
        $client = new PreferenceClient();

        try {
            $preference = $client->create([
                "items" => [
                    [
                        "id" => (string) $plan->id,
                        "title" => "Plano " . $plan->name . " - AprovaAI",
                        "quantity" => 1,
                        "unit_price" => (float) $plan->price,
                        "currency_id" => "BRL"
                    ]
                ],
                "payer" => [
                    "name" => $user->name,
                    "email" => $user->email,
                ],
                "back_urls" => [
                    "success" => route('payments.success'),
                    "failure" => route('payments.failure'),
                    "pending" => route('payments.pending'),
                ],
                "auto_return" => "approved",
                "notification_url" => route('webhooks.mercadopago'),
                "external_reference" => (string) $user->id,
            ]);

            return $preference;

        } catch (MPApiException $e) {
            // Log error
            return null;
        }
    }
}
