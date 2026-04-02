<?php
// mpesa_callback.php - This file receives payment confirmation from Safaricom
require_once 'db.php';

// Get callback data
$callbackData = json_decode(file_get_contents('php://input'), true);

// Log callback for debugging
file_put_contents('mpesa_callback_log.txt', date('Y-m-d H:i:s') . " - " . json_encode($callbackData) . "\n", FILE_APPEND);

// Process the callback
if (isset($callbackData['Body']['stkCallback'])) {
    $resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
    $checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'];
    $amount = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'] ?? 0;
    $mpesaReceipt = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? '';
    
    if ($resultCode == 0) {
        // Payment successful
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'completed', 
                mpesa_receipt = ?,
                payment_date = NOW()
            WHERE transaction_code = ?
        ");
        $stmt->bind_param("ss", $mpesaReceipt, $checkoutRequestID);
        $stmt->execute();
        
        // Update booking payment status
        $stmt = $conn->prepare("
            UPDATE bookings b
            JOIN payments p ON b.id = p.booking_id
            SET b.payment_status = 'paid', b.status = 'confirmed'
            WHERE p.transaction_code = ?
        ");
        $stmt->bind_param("s", $checkoutRequestID);
        $stmt->execute();
        
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    } else {
        // Payment failed
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'failed'
            WHERE transaction_code = ?
        ");
        $stmt->bind_param("s", $checkoutRequestID);
        $stmt->execute();
        
        echo json_encode(['ResultCode' => $resultCode, 'ResultDesc' => 'Payment failed']);
    }
}
?>