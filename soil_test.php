<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize message variable at the top of the file, after session_start()
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying
}

// Add these PHPMailer requirements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Remove the composer autoload and use direct file requires
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

require_once 'config.php';

// Define all helper functions first
function extractSoilTestData($file) {
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    try {
        if ($file_extension == "pdf") {
            $content = strip_tags(file_get_contents($file["tmp_name"]));
            return extractValuesFromText($content);
        } 
        else if (in_array($file_extension, ["jpg", "jpeg", "png"])) {
            return false;
        }
    } catch (Exception $e) {
        error_log("Error extracting soil test data: " . $e->getMessage());
        return false;
    }
    
    return false;
}

function extractValuesFromText($text) {
    $patterns = [
        'ph' => '/pH\s*(?:level)?[:=\s]*(\d+\.?\d*)/i',
        'nitrogen' => '/nitrogen\s*(?:content)?[:=\s]*(\d+\.?\d*)/i',
        'phosphorus' => '/phosphorus\s*(?:content)?[:=\s]*(\d+\.?\d*)/i',
        'potassium' => '/potassium\s*(?:content)?[:=\s]*(\d+\.?\d*)/i'
    ];
    
    $results = [];
    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $results[$key] = floatval($matches[1]);
        }
    }
    
    if (count($results) === 4) {
        return $results;
    }
    
    return false;
}

// Keep this single declaration of validateSoilTestValues() near the top with other helper functions
function validateSoilTestValues($values) {
    if (!is_array($values)) return false;
    
    $valid = true;
    $valid &= isset($values['ph']) && $values['ph'] >= 0 && $values['ph'] <= 14;
    $valid &= isset($values['nitrogen']) && $values['nitrogen'] >= 0 && $values['nitrogen'] <= 5;
    $valid &= isset($values['phosphorus']) && $values['phosphorus'] >= 0 && $values['phosphorus'] <= 1;
    $valid &= isset($values['potassium']) && $values['potassium'] >= 0 && $values['potassium'] <= 5;
    
    return $valid;
}

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

// Get soil tests data from database
$soil_tests = [];
$query = "SELECT st.*, u.username as farmer_name 
    FROM soil_tests st 
          JOIN users u ON st.farmer_id = u.id 
          WHERE st.farmer_id = ? 
    ORDER BY st.test_date DESC";

    $stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $soil_tests[] = $row;
    }
    mysqli_free_result($result);
}

// Get farmers for dropdown
$farmers = [];
$query = "SELECT id, username FROM users WHERE id = ? AND role = 'farmer' AND status = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $farmers[] = $row;
    }
    mysqli_free_result($result);
}

// Add this function before the form processing section
function getFarmerName($conn, $farmer_id) {
    $query = "SELECT username FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $farmer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return htmlspecialchars($row['username']);
    }
    return 'Unknown Farmer';
}

// Add this function to handle file uploads
function handleFileUpload($file) {
    $target_dir = "uploads/soil_tests/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check file type
    if ($file_extension != "pdf" && $file_extension != "jpg" && $file_extension != "jpeg" && $file_extension != "png") {
        return ["success" => false, "message" => "Only PDF, JPG, JPEG & PNG files are allowed."];
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large. Maximum size is 5MB."];
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "filename" => $new_filename];
    } else {
        return ["success" => false, "message" => "Error uploading file."];
    }
}

// Add this function to handle both manual entry and file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_soil_test'])) {
        $document_path = null;
        $soil_data = null;
        
        // Check if file is uploaded
        if (isset($_FILES["soil_test_document"]) && $_FILES["soil_test_document"]["error"] == 0) {
            $upload_result = handleFileUpload($_FILES["soil_test_document"]);
            if ($upload_result["success"]) {
                $document_path = $upload_result["filename"];
                // Try to extract data from file
                $soil_data = extractSoilTestData($_FILES["soil_test_document"]);
                if ($soil_data) {
                    // Use extracted values
                    $ph_level = $soil_data['ph'];
                    $nitrogen_content = $soil_data['nitrogen'];
                    $phosphorus_content = $soil_data['phosphorus'];
                    $potassium_content = $soil_data['potassium'];
                }
            } else {
                $message = $upload_result["message"];
                error_log("File upload failed: " . $upload_result["message"]);
            }
        }
        
        // If no file data, use manual input
        if (!$soil_data) {
            if (empty($_POST['ph_level']) || 
                empty($_POST['nitrogen_content']) || 
                empty($_POST['phosphorus_content']) || 
                empty($_POST['potassium_content'])) {
                $message = 'Please either upload a soil test document or fill in all the fields manually';
                error_log("Validation failed: Missing required fields");
            } else {
                $ph_level = floatval($_POST['ph_level']);
                $nitrogen_content = floatval($_POST['nitrogen_content']);
                $phosphorus_content = floatval($_POST['phosphorus_content']);
                $potassium_content = floatval($_POST['potassium_content']);
            }
        }

        // Continue with database insertion if we have valid data
        if (isset($ph_level)) {
            $farmer_id = $_SESSION['user_id'];
            $test_date = date('Y-m-d');
            
            // Modify the insert query to include document_path
            $insert_query = "INSERT INTO soil_tests (farmer_id, ph_level, nitrogen_content, phosphorus_content, potassium_content, test_date, document_path) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iddddss", $farmer_id, $ph_level, $nitrogen_content, $phosphorus_content, $potassium_content, $test_date, $document_path);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Get farmer's name
                    $farmer_name = getFarmerName($conn, $farmer_id);
                    
                    // Create notification message
                    $notification_message = "<div class='notification-content'>
                        <strong>New Soil Test Submitted</strong><br>
                        Farmer: {$farmer_name}<br>
                        pH Level: {$ph_level}<br>
                        N-P-K: {$nitrogen_content}% - {$phosphorus_content}% - {$potassium_content}%<br>
                        Date: " . date('Y-m-d H:i:s') . "
                    </div>";
                    
                    // Send notification
                    require_once 'send_notification.php';
                    sendNotification($conn, 'soil_test', $notification_message, $farmer_id);
                    
                    // Get farmer's email and name
                    $email_query = "SELECT email, username FROM users WHERE id = ?";
                    $email_stmt = mysqli_prepare($conn, $email_query);
                    mysqli_stmt_bind_param($email_stmt, "i", $farmer_id);
                    mysqli_stmt_execute($email_stmt);
                    $email_result = mysqli_stmt_get_result($email_stmt);
                    $farmer_data = mysqli_fetch_assoc($email_result);
                    
                    if ($farmer_data) {
                        try {
                            $mail = new PHPMailer(true);
                            
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'milujiji702@gmail.com';
                            $mail->Password   = 'dglt rbly eujw zstx';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            // Recipients
                            $mail->setFrom('milujiji702@gmail.com', 'GrowGuide');
                            $mail->addAddress($farmer_data['email'], $farmer_data['username']);
                            
                            // Generate certificate number
                            $certificate_number = 'ST-' . date('Ymd') . '-' . sprintf('%04d', $_SESSION['user_id']);
                            
                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Soil Test Certificate - GrowGuide';
                            
                            // Email body HTML
                        $email_body = "
                        <html>
                        <head>
                            <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                    .header { text-align: center; padding: 20px; background: #2D5A27; color: white; }
                                    .certificate { 
                                        border: 2px solid #2D5A27; 
            padding: 20px;
                                        margin: 20px 0; 
                                        background: #f9f9f9;
                                        position: relative;
                                    }
                                    .result-item { 
                                        margin: 10px 0;
            padding: 10px;
            background: white;
                                    border-radius: 5px;
                                    }
                                    .status {
                                    display: inline-block;
                                        padding: 3px 8px;
                                        border-radius: 3px;
            color: white;
            font-size: 0.9em;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                    <div class='header'>
                                        <h1>Soil Test Certificate</h1>
        </div>

                                    <p>Dear " . htmlspecialchars($farmer_data['username']) . ",</p>
                                    
                                    <p>Your soil test results are ready. Here is your official soil test certificate:</p>
                                    
                                    <div class='certificate'>
                                        <h2 style='text-align: center;'>SOIL TEST CERTIFICATE</h2>
                                        <p style='text-align: center;'>Certificate Number: $certificate_number</p>
                                        <p style='text-align: center;'>Date: " . date('d-m-Y') . "</p>
                                        
                                        <h3>Test Results:</h3>
                                        
                                        <div class='result-item'>
                                            <strong>pH Level:</strong> $ph_level 
                                            <span class='status' style='background-color: " . getPHColor($ph_level) . ";'>" 
                                                . getPHStatus($ph_level) . "</span>
                                                    </div>

                                        <div class='result-item'>
                                            <strong>Nitrogen Content:</strong> $nitrogen_content% ";
                                            $n_status = getNitrogenStatus($nitrogen_content);
                                            $email_body .= "<span class='status' style='background-color: {$n_status[1]};'>{$n_status[0]}</span>
                </div>

                                        <div class='result-item'>
                                            <strong>Phosphorus Content:</strong> $phosphorus_content% ";
                                            $p_status = getPhosphorusStatus($phosphorus_content);
                                            $email_body .= "<span class='status' style='background-color: {$p_status[1]};'>{$p_status[0]}</span>
                                        </div>

                                        <div class='result-item'>
                                            <strong>Potassium Content:</strong> $potassium_content% ";
                                            $k_status = getPotassiumStatus($potassium_content);
                                            $email_body .= "<span class='status' style='background-color: {$k_status[1]};'>{$k_status[0]}</span>
                                        </div>
                                        
                                        <h3>Recommendations:</h3>
                                        <ul>
                                            " . generateRecommendations($ph_level, $nitrogen_content, $phosphorus_content, $potassium_content) . "
                                        </ul>
                                        
                                        <p style='text-align: center; margin-top: 30px;'>
                                            <img src='https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode("https://yourdomain.com/verify-certificate.php?cert=" . $certificate_number) . "' alt='Certificate QR Code'>
                                        </p>
                                        
                                        <p style='text-align: center; font-style: italic; margin-top: 20px;'>
                                            This is an official soil test certificate from GrowGuide
                                        </p>
                </div>

                                    <p><strong>Next Steps:</strong></p>
                                    <ul>
                                        <li>Review the detailed recommendations provided above</li>
                                        <li>Log in to your GrowGuide dashboard for more detailed analysis</li>
                                        <li>Schedule a follow-up consultation if needed</li>
                                    </ul>
                                    
                                    <p style='text-align: center; color: #666; font-size: 0.9em; margin-top: 30px;'>
                                        For any questions or support, please contact our team.<br>
                                        Email: support@growguide.com | Phone: +1234567890
                                    </p>
                            </div>
                        </body>
                        </html>";
                        
                            $mail->Body = $email_body;
                        
                        // Send email
                            if($mail->send()) {
                                // Get the last inserted soil test ID
                                $soil_test_id = mysqli_insert_id($conn);
                                
                                // Store certificate in database
                                $cert_query = "INSERT INTO soil_test_certificates (certificate_number, farmer_id, test_date, soil_test_id) 
                                               VALUES (?, ?, NOW(), ?)";
                                $cert_stmt = mysqli_prepare($conn, $cert_query);
                                
                                if ($cert_stmt) {
                                    mysqli_stmt_bind_param($cert_stmt, "sii", $certificate_number, $farmer_id, $soil_test_id);
                                    
                                    if (mysqli_stmt_execute($cert_stmt)) {
                                        $message = 'Soil test added successfully! Certificate has been sent to your email.';
                                        $_SESSION['success'] = true;
                                    } else {
                                        $message = 'Soil test added but certificate storage failed: ' . mysqli_stmt_error($cert_stmt);
                                        error_log("Certificate storage failed: " . mysqli_stmt_error($cert_stmt));
                                        $_SESSION['success'] = false;
                                    }
                                    mysqli_stmt_close($cert_stmt);
                                } else {
                                    $message = 'Soil test added but certificate preparation failed: ' . mysqli_error($conn);
                                    error_log("Certificate preparation failed: " . mysqli_error($conn));
                                    $_SESSION['success'] = false;
                                }
                            } else {
                                $message = "Soil test added but email could not be sent. Error: {$mail->ErrorInfo}";
                                error_log("Email sending failed: " . $mail->ErrorInfo);
                                $_SESSION['success'] = false;
                            }
                            
                        } catch (Exception $e) {
                            $message = "Soil test added but email could not be sent. Error: {$mail->ErrorInfo}";
                            error_log("Email sending failed: " . $mail->ErrorInfo);
                            $_SESSION['success'] = false;
                        }
                        
                        $_SESSION['message'] = 'Soil test added successfully!';
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    
                    $message = 'Soil test added successfully!';
                    $_SESSION['message'] = 'Soil test added successfully!';
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $message = 'Error adding soil test: ' . mysqli_stmt_error($stmt);
                    error_log("Insert failed: " . mysqli_stmt_error($stmt));
                    $_SESSION['message'] = 'Error adding soil test: ' . mysqli_stmt_error($stmt);
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Add helper functions at the top of the file
function getPHColor($ph) {
    if ($ph < 6.0) return '#ff6b6b';
    if ($ph > 7.5) return '#4d96ff';
    return '#69db7c';
}

function getPHStatus($ph) {
    if ($ph < 6.0) return 'Acidic';
    if ($ph > 7.5) return 'Alkaline';
    return 'Optimal';
}

// Add these helper functions at the top with the other functions
function getNitrogenStatus($value) {
    if ($value < 0.5) return ['Low', '#ff6b6b'];
    if ($value > 1.0) return ['High', '#4d96ff'];
    return ['Optimal', '#69db7c'];
}

function getPhosphorusStatus($value) {
    if ($value < 0.05) return ['Low', '#ff6b6b'];
    if ($value > 0.2) return ['High', '#4d96ff'];
    return ['Optimal', '#69db7c'];
}

function getPotassiumStatus($value) {
    if ($value < 1.0) return ['Low', '#ff6b6b'];
    if ($value > 2.0) return ['High', '#4d96ff'];
    return ['Optimal', '#69db7c'];
}

// Helper function to generate recommendations
function generateRecommendations($ph, $n, $p, $k) {
    $recommendations = [];
    
    // pH recommendations
    if ($ph < 5.5) {
        $recommendations[] = "Apply agricultural lime (2-3 tons/ha) to increase soil pH";
        $recommendations[] = "Mix lime thoroughly with soil before planting";
        $recommendations[] = "Consider split applications for better results";
    } elseif ($ph > 6.5) {
        $recommendations[] = "Add organic matter to help lower soil pH";
        $recommendations[] = "Use sulfur-based amendments";
        $recommendations[] = "Choose acid-loving plants for better yields";
    }
    
    // Nitrogen recommendations
    if ($n < 0.5) {
        $recommendations[] = "Apply nitrogen-rich fertilizer (NPK ratio 6:6:20)";
        $recommendations[] = "Add composted manure to improve soil fertility";
        $recommendations[] = "Consider planting nitrogen-fixing cover crops";
    } elseif ($n > 1.0) {
        $recommendations[] = "Reduce nitrogen application for next season";
        $recommendations[] = "Plant nitrogen-consuming crops";
        $recommendations[] = "Monitor plant growth for excess nitrogen symptoms";
    }
    
    // Phosphorus recommendations
    if ($p < 0.05) {
        $recommendations[] = "Apply phosphate fertilizers or bone meal";
        $recommendations[] = "Incorporate organic phosphorus sources";
        $recommendations[] = "Maintain soil pH between 6.0-7.0 for better phosphorus availability";
    } elseif ($p > 0.2) {
        $recommendations[] = "Avoid phosphorus fertilizers for the next growing season";
        $recommendations[] = "Use cover crops to prevent phosphorus runoff";
        $recommendations[] = "Monitor water quality in nearby water bodies";
    }
    
    // Potassium recommendations
    if ($k < 1.0) {
        $recommendations[] = "Apply potassium-rich fertilizers";
        $recommendations[] = "Add wood ash to increase potassium levels";
        $recommendations[] = "Consider foliar application of potassium";
    } elseif ($k > 2.0) {
        $recommendations[] = "Reduce potassium fertilizer application";
        $recommendations[] = "Improve soil drainage";
        $recommendations[] = "Monitor for nutrient imbalances";
    }
    
    return "<li>" . implode("</li><li>", $recommendations) . "</li>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Soil Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1A3A19;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
            --sidebar-width: 250px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .farmer-profile {
            padding: 20px;
            text-align: center;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 250px);
            justify-content: space-between;
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 8px;
            margin: 0 10px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .content-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .soil-test-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .input-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .results-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 80vh;
            overflow-y: auto;
        }

        .test-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .test-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .test-date {
            color: #666;
            font-size: 0.9em;
        }

        .test-date i {
            margin-right: 5px;
            color: var(--primary-color);
        }

        .parameter-item {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .parameter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .parameter-label {
            font-weight: 500;
            color: var(--text-color);
        }

        .parameter-value {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
        }

        .recommendation {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            font-size: 0.9em;
        }

        .recommendation i {
            margin-right: 5px;
        }

        .recommendation ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .recommendation li {
            margin: 5px 0;
            color: #666;
        }

        .fa-check-circle {
            color: #28a745;
        }

        .fa-info-circle {
            color: #17a2b8;
        }

        .fa-exclamation-circle {
            color: #dc3545;
        }

        .optimal {
            color: #28a745;
        }

        .low, .acidic {
            color: #dc3545;
        }

        .high, .alkaline {
            color: #17a2b8;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        /* Add responsive design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            .main-content {
                margin-left: 60px;
            }
            .nav-item span {
                display: none;
            }
            .sidebar-header h2, 
            .farmer-profile h3, 
            .farmer-profile p {
                display: none;
            }
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            padding-right: 35px;
            transition: border-color 0.3s ease;
        }

        .input-icon {
            position: absolute;
            right: 10px;
            display: none;
        }

        .success-icon {
            color: #28a745;
            display: none;
        }

        .error-icon {
            color: #dc3545;
            display: none;
        }

        .input-group.success .success-icon {
            display: block;
        }

        .input-group.error .error-icon {
            display: block;
        }

        .input-group.success input {
            border-color: #28a745;
        }

        .input-group.error input {
            border-color: #dc3545;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.8em;
            margin-top: 5px;
            display: none;
            animation: slideDown 0.3s ease-out;
        }

        .input-group.error .error-message {
            display: block;
        }

        .input-info {
            color: #666;
            font-size: 0.8em;
            margin-top: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateX(-20px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }

        .submit-btn {
            transition: transform 0.3s ease, background-color 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .input-group {
            transition: all 0.3s ease;
        }

        .input-group:hover {
            transform: translateY(-2px);
        }

        .input-wrapper input:focus {
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.2);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .print-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .results-table th,
        .results-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        .results-table tr:hover {
            background-color: #f8f9fa;
        }

        .value-with-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
            white-space: nowrap;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #printableArea, #printableArea * {
                visibility: visible;
            }
            #printableArea {
                position: absolute;
                left: 0;
                top: 0;
            }
            .sidebar, .soil-test-form {
                display: none;
            }
            .status-badge {
                border: 1px solid #000;
            }
            .results-table th {
                background-color: #f0f0f0 !important;
                color: black !important;
            }
        }

        .test-result-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .test-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--primary-color);
            color: white;
        }

        .test-info h4 {
            margin: 0;
            font-size: 1.1em;
        }

        .farmer-name {
            margin: 5px 0 0;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .print-single-btn {
            background: white;
            color: var(--primary-color);
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .print-single-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        @media print {
            .test-result-card {
                page-break-inside: avoid;
            }
            
            .test-card-header {
                background-color: #f0f0f0 !important;
                color: black !important;
            }
            
            .print-single-btn {
                display: none;
            }
        }

        .results-table th {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }

        .results-table th i.fa-sort {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.3;
        }

        .results-table th:hover i.fa-sort {
            opacity: 1;
        }

        .print-btn-small {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .print-btn-small:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .action-btn {
            margin: 0 2px;
        }

        .value-with-status {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        @media print {
            .action-btn {
                display: none;
            }
            
            .results-table th i.fa-sort {
                display: none;
            }
        }

        .logout-btn {
            color: #ff6b6b !important;
        }

        .logout-btn:hover {
            background-color: #ff6b6b !important;
            color: white !important;
        }

        .test-date-cell {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .test-date-cell:hover {
            background-color: #f5f5f5;
        }

        .date-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-wrapper i {
            transition: transform 0.3s ease;
        }

        .date-wrapper.active i {
            transform: rotate(180deg);
        }

        .recommendation-content {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 10px;
        }

        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .recommendation-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .recommendation-item h5 {
            color: var(--primary-color);
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .recommendation-item ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .recommendation-item li {
            margin: 5px 0;
            color: #666;
        }

        .recommendation-item i {
            width: 20px;
        }

        .fa-arrow-up { color: #dc3545; }
        .fa-arrow-down { color: #17a2b8; }
        .fa-check-circle { color: #28a745; }
        .fa-exclamation-triangle { color: #ffc107; }
        .fa-exclamation-circle { color: #dc3545; }

        .hidden-row {
            display: none;
        }

        .show-more-container {
            text-align: center;
            margin-top: 20px;
            padding: 10px;
        }

        .show-more-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .show-more-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .show-more-btn i {
            transition: transform 0.3s ease;
        }

        .show-more-btn.active i {
            transform: rotate(180deg);
        }

        .document-upload {
            grid-column: 1 / -1;
        }
        
        .document-upload input[type="file"] {
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
            width: 100%;
            cursor: pointer;
        }
        
        .document-upload input[type="file"]:hover {
            border-color: var(--primary-color);
        }
        
        .file-preview {
            margin-top: 10px;
            max-width: 200px;
        }
        
        .file-preview img {
            max-width: 100%;
            border-radius: 4px;
        }
        
        .file-preview .pdf-preview {
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-method-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .toggle-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .toggle-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .toggle-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> GrowGuide</h2>
        </div>
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                    </div>
                <h3><?php echo htmlspecialchars($farmers[0]['username']); ?></h3>
                <p>Cardamom Farmer</p>
            </div>
            <nav class="nav-menu">
                <div class="nav-menu-items">
                    <a href="farmer.php" class="nav-item">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="soil_test.php" class="nav-item active">
                        <i class="fas fa-flask"></i> Soil Test
                    </a>
                    <a href="fertilizerrrr.php" class="nav-item">
                        <i class="fas fa-leaf"></i> Fertilizer Guide
                    </a>
                    <a href="farm_analysis.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i> Farm Analysis
                    </a>
                    <a href="schedule.php" class="nav-item">
                        <i class="fas fa-calendar"></i> Schedule
                    </a>
                    <a href="weather.php" class="nav-item">
                        <i class="fas fa-cloud-sun"></i> Weather
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
                <div class="nav-menu-bottom">
                    <a href="logout.php" class="nav-item logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-grid">
                <!-- Results Section -->
                <div class="results-section">
                    <div class="results-header">
                        <h3><i class="fas fa-history"></i> Soil Test History</h3>
                        <button onclick="printResults()" class="print-btn">
                            <i class="fas fa-print"></i> Print Results
                        </button>
                    </div>

                    <div class="table-responsive" id="printableArea">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th onclick="sortTable(0)">Date <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(1)">Farmer Name <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(2)">pH Level <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(3)">Nitrogen (%) <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(4)">Phosphorus (%) <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(5)">Potassium (%) <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(6)">Overall Status <i class="fas fa-sort"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_tests = count($soil_tests);
                                foreach ($soil_tests as $index => $test): 
                                    $is_hidden = $index >= 5;
                                ?>
                                    <tr class="test-row <?php echo $is_hidden ? 'hidden-row' : ''; ?>" 
                                        data-timestamp="<?php echo strtotime($test['test_date']); ?>">
                                        <td class="test-date-cell" onclick="toggleRecommendation(<?php echo strtotime($test['test_date']); ?>)">
                                            <div class="date-wrapper">
                                                <span><?php echo date('Y-m-d', strtotime($test['test_date'])); ?></span>
                                                <i class="fas fa-chevron-down"></i>
                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($test['farmer_name']); ?></td>
                                        <td>
                                            <div class="value-with-status">
                                                <?php echo $test['ph_level']; ?>
                                                <span class="status-badge" style="background-color: <?php echo getPHColor($test['ph_level']); ?>">
                                            <?php echo getPHStatus($test['ph_level']); ?>
                                        </span>
                                    </div>
                                        </td>
                                        <td>
                                            <?php $n_status = getNitrogenStatus($test['nitrogen_content']); ?>
                                            <div class="value-with-status">
                                                <?php echo $test['nitrogen_content']; ?>%
                                                <span class="status-badge" style="background-color: <?php echo $n_status[1]; ?>">
                                            <?php echo $n_status[0]; ?>
                                        </span>
                                    </div>
                                        </td>
                                        <td>
                                            <?php $p_status = getPhosphorusStatus($test['phosphorus_content']); ?>
                                            <div class="value-with-status">
                                                <?php echo $test['phosphorus_content']; ?>%
                                                <span class="status-badge" style="background-color: <?php echo $p_status[1]; ?>">
                                            <?php echo $p_status[0]; ?>
                                        </span>
                                    </div>
                                        </td>
                                        <td>
                                            <?php $k_status = getPotassiumStatus($test['potassium_content']); ?>
                                            <div class="value-with-status">
                                                <?php echo $test['potassium_content']; ?>%
                                                <span class="status-badge" style="background-color: <?php echo $k_status[1]; ?>">
                                            <?php echo $k_status[0]; ?>
                                        </span>
                                    </div>
                                        </td>
                                        <td>
                                                    <?php
                                                        $optimal_count = 0;
                                            if ($test['ph_level'] >= 5.5 && $test['ph_level'] <= 6.5) $optimal_count++;
                                            if ($test['nitrogen_content'] >= 0.5 && $test['nitrogen_content'] <= 1.0) $optimal_count++;
                                            if ($test['phosphorus_content'] >= 0.05 && $test['phosphorus_content'] <= 0.2) $optimal_count++;
                                            if ($test['potassium_content'] >= 1.0 && $test['potassium_content'] <= 2.0) $optimal_count++;
                                            
                                            $status_color = $optimal_count == 4 ? '#28a745' : 
                                                          ($optimal_count >= 2 ? '#ffc107' : '#dc3545');
                                            $status_text = $optimal_count == 4 ? 'Excellent' : 
                                                         ($optimal_count >= 2 ? 'Fair' : 'Poor');
                                            ?>
                                            <span class="status-badge" style="background-color: <?php echo $status_color; ?>">
                                                <?php echo $status_text; ?>
                                                    </span>
                                        </td>
                                        <td>
                                            <button onclick="printSingleResult(<?php echo strtotime($test['test_date']); ?>)" 
                                                    class="action-btn print-btn-small" 
                                                    title="Print this result">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="recommendation-row <?php echo $is_hidden ? 'hidden-row' : ''; ?>" 
                                        id="recommendation-<?php echo strtotime($test['test_date']); ?>" 
                                        style="display: none;">
                                        <td colspan="8">
                                            <div class="recommendation-content">
                                                <h4><i class="fas fa-lightbulb"></i> Recommendations</h4>
                                                <div class="recommendation-grid">
                                                    <!-- pH Recommendations -->
                                                    <div class="recommendation-item">
                                                        <h5>pH Level (<?php echo $test['ph_level']; ?>)</h5>
                                        <?php if ($test['ph_level'] < 5.5): ?>
                                                            <p><i class="fas fa-arrow-up"></i> Increase pH by:</p>
                                                            <ul>
                                                                <li>Apply agricultural lime (2-3 tons/ha)</li>
                                                                <li>Mix lime thoroughly with soil before planting</li>
                                                                <li>Allow 2-3 weeks before planting</li>
                                                            </ul>
                                        <?php elseif ($test['ph_level'] > 6.5): ?>
                                                            <p><i class="fas fa-arrow-down"></i> Decrease pH by:</p>
                                                            <ul>
                                                                <li>Apply agricultural sulfur (1-2 tons/ha)</li>
                                                                <li>Add organic matter</li>
                                                                <li>Use acidifying fertilizers</li>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p><i class="fas fa-check-circle"></i> pH level is optimal</p>
                                                            <ul>
                                                                <li>Maintain current soil management practices</li>
                                                            </ul>
                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Nitrogen Recommendations -->
                                                    <div class="recommendation-item">
                                                        <h5>Nitrogen (<?php echo $test['nitrogen_content']; ?>%)</h5>
                                                        <?php if ($test['nitrogen_content'] < 0.5): ?>
                                                            <p><i class="fas fa-exclamation-triangle"></i> Low Nitrogen:</p>
                                                            <ul>
                                                                <li>Apply NPK fertilizer (ratio 6:6:20)</li>
                                                                <li>Add organic compost</li>
                                                                <li>Consider cover crops</li>
                                                            </ul>
                                                        <?php elseif ($test['nitrogen_content'] > 1.0): ?>
                                                            <p><i class="fas fa-exclamation-circle"></i> High Nitrogen:</p>
                                                            <ul>
                                                                <li>Reduce nitrogen fertilizer application</li>
                                                                <li>Plant nitrogen-consuming crops</li>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p><i class="fas fa-check-circle"></i> Nitrogen level is optimal</p>
                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Phosphorus Recommendations -->
                                                    <div class="recommendation-item">
                                                        <h5>Phosphorus (<?php echo $test['phosphorus_content']; ?>%)</h5>
                                                        <?php if ($test['phosphorus_content'] < 0.05): ?>
                                                            <p><i class="fas fa-exclamation-triangle"></i> Low Phosphorus:</p>
                                                            <ul>
                                                                <li>Apply rock phosphate</li>
                                                                <li>Add bone meal</li>
                                                                <li>Use phosphorus-rich organic matter</li>
                                                            </ul>
                                                        <?php elseif ($test['phosphorus_content'] > 0.2): ?>
                                                            <p><i class="fas fa-exclamation-circle"></i> High Phosphorus:</p>
                                                            <ul>
                                                                <li>Avoid phosphorus fertilizers</li>
                                                                <li>Plant phosphorus-consuming crops</li>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p><i class="fas fa-check-circle"></i> Phosphorus level is optimal</p>
                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Potassium Recommendations -->
                                                    <div class="recommendation-item">
                                                        <h5>Potassium (<?php echo $test['potassium_content']; ?>%)</h5>
                                                        <?php if ($test['potassium_content'] < 1.0): ?>
                                                            <p><i class="fas fa-exclamation-triangle"></i> Low Potassium:</p>
                                                            <ul>
                                                                <li>Apply potash fertilizer</li>
                                                                <li>Add wood ash</li>
                                                                <li>Use compost rich in banana peels</li>
                                    </ul>
                                                        <?php elseif ($test['potassium_content'] > 2.0): ?>
                                                            <p><i class="fas fa-exclamation-circle"></i> High Potassium:</p>
                                                            <ul>
                                                                <li>Reduce potassium fertilizer use</li>
                                                                <li>Improve soil drainage</li>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p><i class="fas fa-check-circle"></i> Potassium level is optimal</p>
                                                        <?php endif; ?>
                                        </div>
                                </div>
                            </div>
                                        </td>
                                    </tr>
                    <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>

                    <?php if (count($soil_tests) > 5): ?>
                        <div class="show-more-container">
                            <button id="showMoreBtn" class="show-more-btn">
                                <i class="fas fa-chevron-down"></i> Show More Tests
                            </button>
                    </div>
                    <?php endif; ?>
                                        </div>

                <!-- Soil Test Form Section -->
                <div class="soil-test-form">
                    <h2><i class="fas fa-flask"></i> Soil Test Analysis</h2>
                    <?php if ($message): ?>
                        <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-error'; ?>">
                            <i class="fas <?php echo strpos($message, 'success') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="soilTestForm" enctype="multipart/form-data" onsubmit="return validateForm()">
                        <div class="input-method-toggle">
                            <button type="button" class="toggle-btn active" onclick="toggleInputMethod('manual')">
                                <i class="fas fa-keyboard"></i> Manual Entry
                            </button>
                            <button type="button" class="toggle-btn" onclick="toggleInputMethod('file')">
                                <i class="fas fa-file-upload"></i> Upload Test Report
                            </button>
                        </div>

                        <div class="form-grid" id="manualEntry">
                            <div class="input-group">
                                <label for="ph_level">
                                    <i class="fas fa-vial"></i> pH Level
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="ph_level" 
                                           name="ph_level" 
                                           step="0.1" 
                                           placeholder="Enter pH level"
                                           data-min="0"
                                           data-max="14">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 5.5 - 6.5</small>
                                <div class="error-message"></div>
                            </div>

                            <div class="input-group">
                                <label for="nitrogen_content">
                                    <i class="fas fa-leaf"></i> Nitrogen (%)
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="nitrogen_content" 
                                           name="nitrogen_content" 
                                           step="0.01"
                                           placeholder="Enter nitrogen content"
                                           data-min="0"
                                           data-max="5">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 0.5% - 1.0%</small>
                                <div class="error-message"></div>
                            </div>

                            <div class="input-group">
                                <label for="phosphorus_content">
                                    <i class="fas fa-seedling"></i> Phosphorus (%)
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="phosphorus_content" 
                                           name="phosphorus_content" 
                                           step="0.01"
                                           placeholder="Enter phosphorus content"
                                           data-min="0"
                                           data-max="1">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 0.05% - 0.2%</small>
                                <div class="error-message"></div>
                            </div>

                            <div class="input-group">
                                <label for="potassium_content">
                                    <i class="fas fa-flask"></i> Potassium (%)
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="potassium_content" 
                                           name="potassium_content" 
                                           step="0.01"
                                           placeholder="Enter potassium content"
                                           data-min="0"
                                           data-max="5">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 1.0% - 2.0%</small>
                                <div class="error-message"></div>
                            </div>
                        </div>

                        <div class="form-grid" id="fileUpload" style="display: none;">
                            <div class="input-group document-upload">
                                <label for="soil_test_document">
                                    <i class="fas fa-file-upload"></i> Upload Soil Test Document
                                </label>
                                <div class="input-wrapper">
                                    <input type="file" 
                                           id="soil_test_document" 
                                           name="soil_test_document" 
                                           accept=".pdf,.jpg,.jpeg,.png"
                                           onchange="previewFile(this)">
                                    <div class="file-preview" id="filePreview"></div>
                                </div>
                                <small class="input-info">Upload your soil test report (PDF or image, Max 5MB)</small>
                                <div class="error-message"></div>
                            </div>
                        </div>

                        <button type="submit" name="add_soil_test" class="submit-btn">
                            <i class="fas fa-save"></i> Submit Soil Test
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const validateSoilTestForm = () => {
            let isValid = true;
            const inputs = {
                ph_level: {
                    min: 0,
                    max: 14,
                    name: 'pH level',
                    optimal: {min: 5.5, max: 6.5}
                },
                nitrogen_content: {
                    min: 0,
                    max: 5,
                    name: 'Nitrogen content',
                    optimal: {min: 0.5, max: 1.0}
                },
                phosphorus_content: {
                    min: 0,
                    max: 1,
                    name: 'Phosphorus content',
                    optimal: {min: 0.05, max: 0.2}
                },
                potassium_content: {
                    min: 0,
                    max: 5,
                    name: 'Potassium content',
                    optimal: {min: 1.0, max: 2.0}
                }
            };

            // Reset all fields
            Object.keys(inputs).forEach(inputId => {
                const inputGroup = document.getElementById(inputId).closest('.input-group');
                inputGroup.classList.remove('success', 'error');
            });

            // Validate each field
            Object.entries(inputs).forEach(([inputId, config]) => {
                const input = document.getElementById(inputId);
                const value = parseFloat(input.value);
                const inputGroup = input.closest('.input-group');
                const errorDiv = inputGroup.querySelector('.error-message');

                if (!input.value) {
                    isValid = false;
                    inputGroup.classList.add('error');
                    errorDiv.textContent = `${config.name} is required`;
                    shakeElement(inputGroup);
                } else if (isNaN(value) || value < config.min || value > config.max) {
                        isValid = false;
                    inputGroup.classList.add('error');
                    errorDiv.textContent = `${config.name} must be between ${config.min} and ${config.max}`;
                    shakeElement(inputGroup);
                } else {
                    inputGroup.classList.add('success');
                    // Add warning if outside optimal range
                    if (value < config.optimal.min || value > config.optimal.max) {
                        errorDiv.textContent = `Warning: ${config.name} is outside optimal range (${config.optimal.min} - ${config.optimal.max})`;
                        errorDiv.style.color = '#856404';
                        errorDiv.style.display = 'block';
                    }
                }
            });

            // Validate file size
            const fileInput = document.getElementById('soil_test_document');
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                if (file.size > 5000000) {
                    const inputGroup = fileInput.closest('.input-group');
                    const errorDiv = inputGroup.querySelector('.error-message');
                    inputGroup.classList.add('error');
                    errorDiv.textContent = 'File size must be less than 5MB';
                    shakeElement(inputGroup);
                    return false;
                }
            }
            
            return isValid;
        };

        const shakeElement = (element) => {
            element.style.animation = 'none';
            element.offsetHeight; // Trigger reflow
            element.style.animation = 'shake 0.5s ease-in-out';
        };

        // Add real-time validation
        document.querySelectorAll('#soilTestForm input').forEach(input => {
            input.addEventListener('input', () => {
                const inputGroup = input.closest('.input-group');
                const errorDiv = inputGroup.querySelector('.error-message');
                
                if (!input.value) {
                    inputGroup.classList.remove('success');
                    inputGroup.classList.add('error');
                    errorDiv.textContent = 'This field is required';
                } else {
                    inputGroup.classList.remove('error');
                    inputGroup.classList.add('success');
                    errorDiv.style.display = 'none';
                }
            });
        });

        // Add this CSS animation
        document.head.insertAdjacentHTML('beforeend', `
            <style>
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    20%, 60% { transform: translateX(-5px); }
                    40%, 80% { transform: translateX(5px); }
                }
            </style>
        `);

        function sortTable(column) {
            const table = document.querySelector('.results-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const sortedRows = rows.sort((a, b) => {
                let aCol = a.cells[column].textContent.trim();
                let bCol = b.cells[column].textContent.trim();
                
                // Remove % symbol and status text for numeric columns
                if (column >= 2 && column <= 5) {
                    aCol = parseFloat(aCol.replace('%', ''));
                    bCol = parseFloat(bCol.replace('%', ''));
                } else if (column === 0) {
                    // Sort dates
                    return new Date(aCol) - new Date(bCol);
                } else {
                    // Sort strings
                    return aCol.localeCompare(bCol);
                }
                
                return aCol - bCol;
            });
            
            // Clear the table
            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }
            
            // Add sorted rows
            sortedRows.forEach(row => tbody.appendChild(row));
        }

        function printSingleResult(timestamp) {
            const row = document.querySelector(`tr[data-timestamp="${timestamp}"]`);
            const table = document.querySelector('.results-table').cloneNode(true);
            const tbody = table.querySelector('tbody');
            
            // Clear table body and add only the selected row
            tbody.innerHTML = '';
            tbody.appendChild(row.cloneNode(true));
            
            const printContent = `
                <div style="padding: 20px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2>Soil Test Report</h2>
                        <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    </div>
                    ${table.outerHTML}
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        function printResults() {
            window.print();
        }

        function toggleRecommendation(timestamp) {
            const recommendationRow = document.getElementById(`recommendation-${timestamp}`);
            const dateWrapper = event.currentTarget.querySelector('.date-wrapper');
            const allRecommendations = document.querySelectorAll('.recommendation-row');
            const allDateWrappers = document.querySelectorAll('.date-wrapper');
            
            // Hide all other recommendations
            allRecommendations.forEach(row => {
                if (row.id !== `recommendation-${timestamp}`) {
                    row.style.display = 'none';
                }
            });
            
            // Remove active class from all date wrappers
            allDateWrappers.forEach(wrapper => {
                wrapper.classList.remove('active');
            });
            
            // Toggle current recommendation
            if (recommendationRow.style.display === 'none') {
                recommendationRow.style.display = 'table-row';
                dateWrapper.classList.add('active');
            } else {
                recommendationRow.style.display = 'none';
                dateWrapper.classList.remove('active');
            }
        }

        function toggleShowMore() {
            const hiddenRows = document.querySelectorAll('.hidden-row');
            const showMoreBtn = document.getElementById('showMoreBtn');
            
            hiddenRows.forEach(row => {
                if (row.style.display === 'none' || !row.style.display) {
                    row.style.display = row.classList.contains('recommendation-row') ? 'none' : 'table-row';
                    showMoreBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less Tests';
                    showMoreBtn.classList.add('active');
                } else {
                    row.style.display = 'none';
                    showMoreBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show More Tests';
                    showMoreBtn.classList.remove('active');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const showMoreBtn = document.getElementById('showMoreBtn');
            if (showMoreBtn) {
                showMoreBtn.addEventListener('click', toggleShowMore);
            }
        });

        function previewFile(input) {
            const preview = document.getElementById('filePreview');
            const file = input.files[0];
            
            if (file) {
                if (file.type === 'application/pdf') {
                    preview.innerHTML = `
                        <div class="pdf-preview">
                            <i class="fas fa-file-pdf" style="color: #dc3545;"></i>
                            <span>${file.name}</span>
                        </div>
                    `;
                } else if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    }
                    reader.readAsDataURL(file);
                }
            } else {
                preview.innerHTML = '';
            }
        }

        function toggleInputMethod(method) {
            const manualEntry = document.getElementById('manualEntry');
            const fileUpload = document.getElementById('fileUpload');
            const toggleBtns = document.querySelectorAll('.toggle-btn');
            
            if (method === 'manual') {
                manualEntry.style.display = 'grid';
                fileUpload.style.display = 'none';
                toggleBtns[0].classList.add('active');
                toggleBtns[1].classList.remove('active');
                resetFileUpload();
            } else {
                manualEntry.style.display = 'none';
                fileUpload.style.display = 'grid';
                toggleBtns[0].classList.remove('active');
                toggleBtns[1].classList.add('active');
                resetManualInputs();
            }
        }

        function resetFileUpload() {
            const fileInput = document.getElementById('soil_test_document');
            const filePreview = document.getElementById('filePreview');
            fileInput.value = '';
            filePreview.innerHTML = '';
        }

        function resetManualInputs() {
            const inputs = document.querySelectorAll('#manualEntry input');
            inputs.forEach(input => {
                input.value = '';
                const inputGroup = input.closest('.input-group');
                inputGroup.classList.remove('success', 'error');
            });
        }

        function validateForm() {
            const manualEntry = document.getElementById('manualEntry');
            const fileUpload = document.getElementById('fileUpload');
            
            if (manualEntry.style.display !== 'none') {
                return validateSoilTestForm();
            } else {
                return validateFileUpload();
            }
        }
    </script>
</body>
</html>