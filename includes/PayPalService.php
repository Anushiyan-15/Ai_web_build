<?php
// ═══════════════════════════════════════════════════════════════
//  includes/PayPalService.php — PayPal REST API v2 Client
//  Handles OAuth token generation, Order creation, and Capture
// ═══════════════════════════════════════════════════════════════

require_once dirname(__DIR__) . '/config/paypal.php';
require_once dirname(__DIR__) . '/config/app.php';

class PayPalService {
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $currency;
    private ?string $accessToken = null;

    public function __construct() {
        $this->baseUrl      = defined('PAYPAL_BASE_URL') ? PAYPAL_BASE_URL : 'https://api-m.sandbox.paypal.com';
        $this->clientId     = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
        $this->clientSecret = defined('PAYPAL_CLIENT_SECRET') ? PAYPAL_CLIENT_SECRET : '';
        $this->currency     = defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD';
    }

    /**
     * Checks if real PayPal credentials have been provided.
     */
    public function isConfigured(): bool {
        return !empty($this->clientId) 
            && !empty($this->clientSecret) 
            && strpos($this->clientId, 'YOUR_PAYPAL') === false;
    }

    /**
     * Retrieve OAuth 2.0 Bearer access token from PayPal.
     */
    public function getAccessToken(): ?string {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (!$this->isConfigured()) {
            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/v1/oauth2/token';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$this->clientId}:{$this->clientSecret}",
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false // UniServerZ local dev convenience
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode < 200 || $httpCode >= 300) {
            error_log("PayPal OAuth Error: HTTP $httpCode: $response ($err)");
            return null;
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;
        return $this->accessToken;
    }

    /**
     * Create a PayPal checkout order (POST /v2/checkout/orders)
     *
     * @param string $orderRef Internal reference (e.g. WBL-2024-XXXX)
     * @param float  $amount   Amount in selected currency
     * @param string $desc     Description for PayPal item
     * @param string $returnUrl URL to return after approval
     * @param string $cancelUrl URL to return on cancel
     * @return array ['success' => bool, 'order_id' => string, 'checkout_url' => string, 'error' => string]
     */
    public function createOrder(
        string $orderRef,
        float $amount,
        string $desc,
        string $returnUrl,
        string $cancelUrl
    ): array {
        // If developer credentials are not yet entered, use realistic test sandbox simulator
        if (!$this->isConfigured()) {
            $simOrderId = 'SANDBOX-' . strtoupper(bin2hex(random_bytes(6)));
            $delim = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            $param = (strpos($returnUrl, 'payment=') !== false) ? ('token=' . urlencode($simOrderId)) : ('payment=success&token=' . urlencode($simOrderId));
            $simCheckoutUrl = $returnUrl . $delim . $param;

            return [
                'success'      => true,
                'mode'         => 'simulator',
                'order_id'     => $simOrderId,
                'checkout_url' => $simCheckoutUrl,
                'order_ref'    => $orderRef,
                'note'         => 'Sandbox simulator mode active (Configure config/paypal.php for live PayPal).'
            ];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error'   => 'Could not authenticate with PayPal API. Please check client credentials in config/paypal.php.'
            ];
        }

        $formattedAmount = number_format($amount, 2, '.', '');
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $orderRef,
                    'description'  => substr($desc, 0, 127),
                    'amount'       => [
                        'currency_code' => $this->currency,
                        'value'         => $formattedAmount,
                    ],
                ]
            ],
            'application_context' => [
                'brand_name'          => defined('SITE_NAME') ? SITE_NAME : 'WebCraft AI',
                'landing_page'        => 'NO_PREFERENCE',
                'user_action'         => 'PAY_NOW',
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
            ],
        ];

        $url = rtrim($this->baseUrl, '/') . '/v2/checkout/orders';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => "cURL error connecting to PayPal: $err"];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($data['id'])) {
            $checkoutUrl = '';
            foreach ($data['links'] ?? [] as $link) {
                if (($link['rel'] ?? '') === 'approve') {
                    $checkoutUrl = $link['href'];
                    break;
                }
            }

            return [
                'success'      => true,
                'mode'         => 'paypal',
                'order_id'     => $data['id'],
                'checkout_url' => $checkoutUrl,
                'order_ref'    => $orderRef,
                'raw'          => $data
            ];
        }

        $errorMsg = $data['message'] ?? ($data['error_description'] ?? 'Failed to create PayPal order');
        return ['success' => false, 'error' => $errorMsg, 'http_code' => $httpCode];
    }

    /**
     * Capture payment for an approved PayPal order (POST /v2/checkout/orders/{id}/capture)
     *
     * @param string $paypalOrderId
     * @return array
     */
    public function captureOrder(string $paypalOrderId): array {
        // If simulated order ID or not configured:
        if (str_starts_with($paypalOrderId, 'SANDBOX-') || str_starts_with($paypalOrderId, 'SIMULATED-') || !$this->isConfigured()) {
            return [
                'success'        => true,
                'status'         => 'COMPLETED',
                'paypal_order_id'=> $paypalOrderId,
                'capture_id'     => 'CAP-SIM-' . strtoupper(bin2hex(random_bytes(5))),
                'payer'          => [
                    'name'  => 'Sandbox Test User',
                    'email' => 'sandbox-buyer@example.com'
                ],
                'simulated'      => true
            ];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'PayPal authentication failed during capture.'];
        }

        $url = rtrim($this->baseUrl, '/') . "/v2/checkout/orders/" . urlencode($paypalOrderId) . "/capture";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer $token",
            ],
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => "cURL capture error: $err"];
        }

        $data = json_decode($response, true);
        $status = $data['status'] ?? '';

        if ($httpCode >= 200 && $httpCode < 300 && $status === 'COMPLETED') {
            $captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? ($data['id'] ?? '');
            return [
                'success'         => true,
                'status'          => 'COMPLETED',
                'paypal_order_id' => $data['id'],
                'capture_id'      => $captureId,
                'payer'           => [
                    'name'  => trim(($data['payer']['name']['given_name'] ?? '') . ' ' . ($data['payer']['name']['surname'] ?? '')),
                    'email' => $data['payer']['email_address'] ?? ''
                ],
                'raw'             => $data
            ];
        }

        $errorMsg = $data['message'] ?? ($data['details'][0]['description'] ?? "Order capture failed with status: $status");
        return ['success' => false, 'error' => $errorMsg, 'http_code' => $httpCode];
    }
}
