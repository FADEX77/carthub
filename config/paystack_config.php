<?php
// Paystack Configuration
// Replace these with your actual Paystack keys

// Test Keys (for development)
define('PAYSTACK_PUBLIC_KEY_TEST', 'pk_test_4594636884df651cf5633e6edcbfa3a46245c746');
define('PAYSTACK_SECRET_KEY_TEST', 'sk_test_7955d083231af531d17aa9e154bc1bccb543b0e3');

// Live Keys (for production)
define('PAYSTACK_PUBLIC_KEY_LIVE', 'pk_live_your_public_key_here');
define('PAYSTACK_SECRET_KEY_LIVE', 'sk_live_your_secret_key_here');

// Environment (set to 'live' for production)
define('PAYSTACK_ENVIRONMENT', 'test');

// Get current keys based on environment
function getPaystackPublicKey() {
    return PAYSTACK_ENVIRONMENT === 'live' ? PAYSTACK_PUBLIC_KEY_LIVE : PAYSTACK_PUBLIC_KEY_TEST;
}

function getPaystackSecretKey() {
    return PAYSTACK_ENVIRONMENT === 'live' ? PAYSTACK_SECRET_KEY_LIVE : PAYSTACK_SECRET_KEY_TEST;
}

// Paystack API Base URL
define('PAYSTACK_BASE_URL', 'https://api.paystack.co');

// Supported currencies
define('PAYSTACK_CURRENCIES', ['NGN', 'USD', 'GHS', 'ZAR', 'KES']);

// Default currency
define('DEFAULT_CURRENCY', 'USD');
?>
