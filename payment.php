<?php
session_start();

// Simple auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'mentorbridge');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$session_id = intval($_GET['session_id'] ?? 0);

// Default session
$session = [
    'id' => 0,
    'mentor_name' => 'Sample Mentor',
    'scheduled_at' => date('Y-m-d H:i:s', strtotime('+7 days 10:00')),
    'amount' => 50.00
];

// Try to load real session
if ($session_id) {
    $stmt = $mysqli->prepare("SELECT s.*, mp.full_name as mentor_name FROM sessions s JOIN mentor_profiles mp ON s.mentor_id = mp.id WHERE s.id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $session = $row;
    }
    $stmt->close();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $session['id'] > 0) {
    $payment_method = $_POST['payment_method'] ?? '';
    
    if (empty($payment_method)) {
        $error = 'Please select a payment method';
    } else {
        $stmt = $mysqli->prepare("UPDATE sessions SET status = 'confirmed', payment_status = 'paid' WHERE id = ?");
        $stmt->bind_param("i", $session['id']);
        $stmt->execute();
        $stmt->close();
        unset($_SESSION['pending_booking']);
        header('Location: mentee-dashboard.php?success=payment_complete');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - MentorBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #638ECB;
            --color-primary-dark: #395886;
            --color-bg-light: #F0F3FA;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--color-bg-light) 0%, #D5DEEF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(99, 142, 203, 0.2);
        }
        
        h1 {
            color: var(--color-primary-dark);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2rem;
        }
        
        .session-time {
            font-size: 0.95rem;
            color: #555;
        }
        
        .booking-details {
            background: var(--color-bg-light);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .session-time {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #666;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-row strong {
            color: #333;
        }
        
        .total {
            font-size: 1.5rem;
            color: var(--color-primary);
            font-weight: bold;
            padding-top: 1rem;
            border-top: 2px solid #e0e0e0;
            margin-top: 1rem;
        }
        
        .payment-note {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            color: #856404;
            text-align: center;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 142, 203, 0.3);
        }
        
        .btn-secondary {
            background: var(--color-bg-light);
            color: var(--color-primary-dark);
            margin-top: 1rem;
        }
        
        .btn-secondary:hover {
            background: #D5DEEF;
        }
        
        .payment-methods {
            margin-bottom: 2rem;
        }
        
        .payment-methods h3 {
            color: var(--color-primary-dark);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .payment-option {
            border: 2px solid var(--color-bg-light);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .payment-option:hover {
            border-color: var(--color-primary-light);
            background: var(--color-bg-light);
        }
        
        .payment-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .payment-option label {
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .payment-option.selected {
            border-color: var(--color-primary);
            background: var(--color-bg-light);
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>💳 Payment</h1>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="booking-details">
            <div class="detail-row">
                <span>Mentor:</span>
                <strong><?php echo htmlspecialchars($session['mentor_name']); ?></strong>
            </div>
            <div class="detail-row">
                <span>Date & Time:</span>
                <strong>
                    <?php 
                    $start = strtotime($session['scheduled_at']);
                    $end = $start + 3600; // +1 hour
                    echo date('M d, Y', $start) . '<br><span class="session-time">' . 
                         date('g:i A', $start) . ' - ' . date('g:i A', $end) . '</span>';
                    ?>
                </strong>
            </div>
            <div class="detail-row">
                <span>Duration:</span>
                <strong>1 hour</strong>
            </div>
            <div class="detail-row total">
                <span>Total Amount:</span>
                <span>$<?php echo number_format($session['amount'], 2); ?></span>
            </div>
        </div>

        <div class="payment-note">
            ⚠️ This is a demo. No actual payment will be processed.
        </div>

        <form method="POST">
            <div class="payment-methods">
                <h3>Choose Payment Method</h3>
                
                <div class="payment-option" onclick="selectPayment(this, 'visa')">
                    <input type="radio" name="payment_method" value="visa" id="visa" required>
                    <label for="visa">
                        <svg width="40" height="24" viewBox="0 0 40 24" fill="none">
                            <rect width="40" height="24" rx="4" fill="#1434CB"/>
                            <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold" font-size="12">VISA</text>
                        </svg>
                        Visa Card
                    </label>
                </div>
                
                <div class="payment-option" onclick="selectPayment(this, 'mastercard')">
                    <input type="radio" name="payment_method" value="mastercard" id="mastercard" required>
                    <label for="mastercard">
                        <svg width="40" height="24" viewBox="0 0 40 24" fill="none">
                            <rect width="40" height="24" rx="4" fill="#EB001B"/>
                            <circle cx="15" cy="12" r="6" fill="#FF5F00"/>
                            <circle cx="25" cy="12" r="6" fill="#F79E1B"/>
                        </svg>
                        Mastercard
                    </label>
                </div>
                
                <div class="payment-option" onclick="selectPayment(this, 'paypal')">
                    <input type="radio" name="payment_method" value="paypal" id="paypal" required>
                    <label for="paypal">
                        <svg width="40" height="24" viewBox="0 0 40 24" fill="none">
                            <rect width="40" height="24" rx="4" fill="#003087"/>
                            <path d="M15 8h-2l-1.5 10h2l1.5-10zm8 0h-2c-.5 0-.9.4-1 .8l-3 9.2h2l.5-1.5h3l.5 1.5h2l-2-10zm-1.5 6.5l1-3 .5 3h-1.5z" fill="#009CDE"/>
                        </svg>
                        PayPal
                    </label>
                </div>
            </div>

            <button type="submit" class="btn">Complete Payment</button>
        </form>
        
        <a href="mentee-dashboard.php">
            <button type="button" class="btn btn-secondary">Cancel</button>
        </a>
    </div>
    
    <script>
        function selectPayment(element, method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Check the radio button
            document.getElementById(method).checked = true;
        }
    </script>
</body>
</html>
<?php $mysqli->close(); ?>
