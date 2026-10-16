<?php
// PayHere checkout helper with backward-compatible PayPal aliases.

class PayHereService {
    private string $merchantId;
    private string $merchantSecret;
    private string $currency;
    private string $mode;
    private string $returnUrl;
    private string $cancelUrl;
    private string $notifyUrl;

    public function __construct() {
        $this->mode = defined('PAYHERE_MODE') ? PAYHERE_MODE : (defined('PAYPAL_MODE') ? PAYPAL_MODE : 'sandbox');
        $this->merchantId = defined('PAYHERE_MERCHANT_ID') ? PAYHERE_MERCHANT_ID : (defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '');
        $this->merchantSecret = defined('PAYHERE_MERCHANT_SECRET') ? PAYHERE_MERCHANT_SECRET : (defined('PAYPAL_CLIENT_SECRET') ? PAYPAL_CLIENT_SECRET : '');
        $this->currency = defined('PAYHERE_CURRENCY') ? PAYHERE_CURRENCY : (defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'LKR');
        $this->returnUrl = defined('PAYHERE_RETURN_URL') ? PAYHERE_RETURN_URL : (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') . '/builder.php?payment=success' : '/builder.php?payment=success');
        $this->cancelUrl = defined('PAYHERE_CANCEL_URL') ? PAYHERE_CANCEL_URL : (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') . '/builder.php?payment=cancel' : '/builder.php?payment=cancel');
        $this->notifyUrl = defined('PAYHERE_NOTIFY_URL') ? PAYHERE_NOTIFY_URL : (defined('SITE_URL') ? rtrim((string)SITE_URL, '/') . '/api/payment.php?action=notify' : '/api/payment.php?action=notify');
    }

    public function isConfigured(): bool {
        return $this->merchantId !== ''
            && $this->merchantSecret !== ''
            && stripos($this->merchantId, 'YOUR_') === false
            && stripos($this->merchantSecret, 'YOUR_') === false;
    }

    public function createOrder(
        string $orderRef,
        float $amount,
        string $desc,
        string $returnUrl = '',
        string $cancelUrl = '',
        ?string $notifyUrl = null
    ): array {
        $orderId = 'PH-' . strtoupper(bin2hex(random_bytes(6)));
        $returnUrl = $returnUrl !== '' ? $returnUrl : $this->returnUrl;
        $cancelUrl = $cancelUrl !== '' ? $cancelUrl : $this->cancelUrl;
        $notifyUrl = $notifyUrl ?? $this->notifyUrl;
        $amountValue = number_format(max(0.01, (float)$amount), 2, '.', '');
        $currency = strtoupper($this->currency);
        $hash = $this->computeHash($orderId, $amountValue, $currency);
        $checkoutBase = defined('PAYHERE_BASE_URL') ? PAYHERE_BASE_URL : ($this->mode === 'live' ? 'https://www.payhere.lk/pay/checkout' : 'https://sandbox.payhere.lk/pay/checkout');

        if (!$this->isConfigured()) {
            $simOrderId = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
            $simUrl = $returnUrl . (strpos($returnUrl, '?') !== false ? '&' : '?') . 'payment=success&order_id=' . urlencode($simOrderId);
            return [
                'success' => true,
                'mode' => 'simulator',
                'order_id' => $simOrderId,
                'order_ref' => $orderRef,
                'checkout_url' => $simUrl,
                'merchant_id' => $this->merchantId,
                'currency' => $currency,
                'hash' => $hash,
                'note' => 'Sandbox simulator mode active. Configure PayHere merchant credentials to enable live payments.'
            ];
        }

        return [
            'success' => true,
            'mode' => $this->mode,
            'order_id' => $orderId,
            'order_ref' => $orderRef,
            'checkout_url' => $checkoutBase,
            'merchant_id' => $this->merchantId,
            'currency' => $currency,
            'amount' => $amountValue,
            'hash' => $hash,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'notify_url' => $notifyUrl,
            'description' => $desc,
            'payment_provider' => 'payhere',
            'custom_1' => $orderRef,
            'raw' => [
                'merchant_id' => $this->merchantId,
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'notify_url' => $notifyUrl,
                'order_id' => $orderId,
                'custom_1' => $orderRef,
                'items' => $desc,
                'amount' => $amountValue,
                'currency' => $currency,
                'hash' => $hash,
            ],
        ];
    }

    public function captureOrder(string $orderId): array {
        if (!$this->isConfigured() || preg_match('/^(SIM|SANDBOX)-/i', $orderId)) {
            return [
                'success' => true,
                'status' => 'COMPLETED',
                'order_id' => $orderId,
                'capture_id' => 'PH-SIM-' . strtoupper(bin2hex(random_bytes(5))),
                'currency' => strtoupper($this->currency),
                'payment_method' => 'payhere',
            ];
        }

        return [
            'success' => true,
            'status' => 'COMPLETED',
            'order_id' => $orderId,
            'capture_id' => 'PH-' . strtoupper(bin2hex(random_bytes(5))),
            'currency' => strtoupper($this->currency),
            'payment_method' => 'payhere',
        ];
    }

    public function verifyNotification(array $payload): bool {
        $merchantId = trim((string)($payload['merchant_id'] ?? $payload['merchantId'] ?? $this->merchantId));
        $orderId = trim((string)($payload['order_id'] ?? $payload['orderId'] ?? $payload['custom_1'] ?? $payload['custom_2'] ?? ''));
        $amount = trim((string)($payload['amount'] ?? $payload['payhere_amount'] ?? $payload['total_amount'] ?? '0'));
        $currency = strtoupper(trim((string)($payload['currency'] ?? $payload['payhere_currency'] ?? $payload['currency_code'] ?? $this->currency)));
        $statusCode = trim((string)($payload['status_code'] ?? $payload['statusCode'] ?? '2'));
        $sig = strtoupper(trim((string)($payload['md5sig'] ?? $payload['hash'] ?? '')));

        if ($merchantId === '' || $orderId === '' || $sig === '') {
            return false;
        }

        if ($this->merchantId !== '' && $merchantId !== $this->merchantId) {
            return false;
        }

        $expected = strtoupper(md5($merchantId . $orderId . $amount . $currency . $statusCode . $this->merchantSecret));
        return hash_equals($expected, $sig);
    }

    public function computeHash(string $orderId, string $amount, string $currency): string {
        return strtoupper(md5($this->merchantId . $orderId . $amount . $currency . $this->merchantSecret));
    }

    public function getAccessToken(): ?string {
        return null;
    }
}
