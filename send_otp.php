<?php
session_start(); 
$error_message = '';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

function generateVerificationCode($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($recipientEmail, $verificationCode) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'growguide593@gmail.com';
        $mail->Password   = 'dubv llyx bjvf zyyd';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('growguide593@gmail.com', 'GrowGuide');
        $mail->addAddress($recipientEmail);
        $mail->Subject = 'Your Verification Code';
        $mail->Body    = "Your verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email'])) {
        $email = $_POST['email'];
        $verificationCode = generateVerificationCode();
        $_SESSION['verification_code'] = $verificationCode;
        $_SESSION['email'] = $email;

        if (sendVerificationEmail($email, $verificationCode)) {
            echo"";
        } else {
            $error_message= "Failed to send verification code.";
        }
    } elseif (isset($_POST['verify'])) {
        $enteredOTP = $_POST['otp1'] . $_POST['otp2'] . $_POST['otp3'] . $_POST['otp4'] . $_POST['otp5'] . $_POST['otp6'];
        if ($enteredOTP == $_SESSION['verification_code']) {
            header('Location: resetpassword.php');
            unset($_SESSION['verification_code']);  
        } else {
            $error_message="Incorrect OTP.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskMate - OTP Verification</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s ease-out;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .task { color:rgb(48, 179, 15); }
        .mate { color:rgb(22, 202, 37); }

        h2 {
            text-align: center;
            color:rgb(5, 53, 4);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color:rgb(25, 218, 35);
            font-size: 0.9rem;
        }

        .otp-inputs {
            display: flex;
            justify-content: space-between;
        }

        .otp-inputs input {
            width: 3rem;
            height: 3rem;
            text-align: center;
            font-size: 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .otp-inputs input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .resend-text {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }

        .resend-text a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .resend-text a:hover {
            color: #1d4ed8;
        }
        .error-message {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <span class="task">Grow</span><span class="mate">Guide</span>
        </div>
        <h2>OTP Verification</h2>
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <p style="text-align: center; color: #64748b; margin-bottom: 1.5rem;">Enter the 6-digit code sent to your email.</p>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group otp-inputs">
                <input type="text" maxlength="1" required oninput="moveToNext(this, 'otp2')" id="otp1" name="otp1">
                <input type="text" maxlength="1" required oninput="moveToNext(this, 'otp3')" id="otp2" name="otp2">
                <input type="text" maxlength="1" required oninput="moveToNext(this, 'otp4')" id="otp3" name="otp3">
                <input type="text" maxlength="1" required oninput="moveToNext(this, 'otp5')" id="otp4" name="otp4">
                <input type="text" maxlength="1" required oninput="moveToNext(this, 'otp6')" id="otp5" name="otp5">
                <input type="text" maxlength="1" required id="otp6" name="otp6">
            </div>
            <button type="submit" class="login-btn" name="verify">Verify OTP</button>
            <p class="resend-text">Didn't receive the code? <a href="#">Resend OTP</a></p>
        </form>
    </div>

    <script>
        function moveToNext(current, nextFieldID) {
            if (current.value.length === 1) {
                document.getElementById(nextFieldID)?.focus();
            }
        }
    </script>
</body>
</html>