<?php
// mpesa_config.php
class MpesaAPI {
    private $consumer_key = 'YOUR_CONSUMER_KEY';     // From developer portal
    private $consumer_secret = 'YOUR_CONSUMER_SECRET'; // From developer portal
    private $business_shortcode = '174379';          // Test shortcode
    private $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; // Test passkey
    private $environment = 'sandbox'; // or 'production'
    
    private $base_url;
    
    public function __construct() {
        if ($this->environment == 'sandbox') {
            $this->base_url = 'https://sandbox.safaricom.co.ke';
        } else {
            $this->base_url = 'https://api.safaricom.co.ke';
        }
    }
    
    // Generate Access Token
    public function generateToken() {
        $url = $this->base_url . '/oauth/v1/generate?grant_type=client_credentials';
        
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response);
        
        if (isset($result->access_token)) {
            return $result->access_token;
        }
        
        return null;
    }
    
    // STK Push (Send payment request to customer's phone)
    public function stkPush($phone, $amount, $account_reference, $transaction_desc) {
        $access_token = $this->generateToken();
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to generate token'];
        }
        
        // Format phone number (remove leading 0, add 254)
        $phone = $this->formatPhoneNumber($phone);
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
        
        $url = $this->base_url . '/mpesa/stkpush/v1/processrequest';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ));
        
        $data = array(
            'BusinessShortCode' => $this->business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->business_shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => 'https://yourdomain.com/mpesa_callback.php',
            'AccountReference' => $account_reference,
            'TransactionDesc' => $transaction_desc
        );
        
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response);
        
        if (isset($result->ResponseCode) && $result->ResponseCode == '0') {
            return [
                'success' => true,
                'message' => 'Payment request sent! Check your phone to complete payment.',
                'checkout_request_id' => $result->CheckoutRequestID
            ];
        } else {
            return [
                'success' => false,
                'message' => isset($result->errorMessage) ? $result->errorMessage : 'Payment request failed'
            ];
        }
    }
    
    // Check Transaction Status
    public function checkTransactionStatus($checkout_request_id) {
        $access_token = $this->generateToken();
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to generate token'];
        }
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
        
        $url = $this->base_url . '/mpesa/stkpushquery/v1/query';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ));
        
        $data = array(
            'BusinessShortCode' => $this->business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkout_request_id
        );
        
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    // Format phone number (07xxxxxxxx -> 2547xxxxxxxx)
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) == '254') {
            // Already formatted
        } else {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}
?>