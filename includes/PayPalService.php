<?php
// Legacy PayPal compatibility shim.
require_once __DIR__ . '/PayHereService.php';

class PayPalService extends PayHereService {
    public function isConfigured(): bool {
        return parent::isConfigured();
    }

    public function getAccessToken(): ?string {
        return null;
    }

    public function createOrder(
        string $orderRef,
        float $amount,
        string $desc,
        string $returnUrl,
        string $cancelUrl
    ): array {
        return parent::createOrder($orderRef, $amount, $desc, $returnUrl, $cancelUrl);
    }

    public function captureOrder(string $paypalOrderId): array {
        return parent::captureOrder($paypalOrderId);
    }
}
