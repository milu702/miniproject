<?php
session_start();

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get test_id and farmer id from URL parameters
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$farmer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$test_id || !$farmer_id) {
    die("Invalid request: Missing test ID or farmer ID");
}

// Get soil test data
$test_query = "SELECT st.*, u.username as farmer_name, u.email as farmer_email 
               FROM soil_tests st 
               JOIN users u ON st.farmer_id = u.id 
               WHERE st.id = ? AND st.farmer_id = ?";
$stmt = mysqli_prepare($conn, $test_query);
mysqli_stmt_bind_param($stmt, "ii", $test_id, $farmer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    die("Soil test not found");
}

$soil_test = mysqli_fetch_assoc($result);

// Process form submission for sending recommendations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_recommendations'])) {
    $recommendations = mysqli_real_escape_string($conn, $_POST['recommendations']);
    
    // Update the soil test with recommendations
    $update_query = "UPDATE soil_tests SET recommendations = ?, reviewed_by = ?, review_date = NOW() 
                    WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "sii", $recommendations, $_SESSION['user_id'], $test_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        // Mark related notification as read
        $mark_read_query = "UPDATE notifications SET is_read = 1 
                           WHERE reference_id = ? AND type = 'soil_test'";
        $mark_stmt = mysqli_prepare($conn, $mark_read_query);
        mysqli_stmt_bind_param($mark_stmt, "i", $test_id);
        mysqli_stmt_execute($mark_stmt);
        
        // Send email notification to farmer
        require 'PHPMailer-master/src/Exception.php';
        require 'PHPMailer-master/src/PHPMailer.php';
        require 'PHPMailer-master/src/SMTP.php';
        
        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\Exception;
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'milujiji702@gmail.com';
            $mail->Password = 'dglt rbly eujw zstx';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('milujiji702@gmail.com', 'GrowGuide');
            $mail->addAddress($soil_test['farmer_email'], $soil_test['farmer_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Soil Test Recommendations - GrowGuide';
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { text-align: center; padding: 20px; background: #2D5A27; color: white; }
                    .content { padding: 20px; border: 1px solid #ddd; }
                    .recommendations { background: #f9f9f9; padding: 15px; border-left: 4px solid #2D5A27; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Soil Test Recommendations</h1>
                    </div>
                    <div class='content'>
                        <p>Dear {$soil_test['farmer_name']},</p>
                        <p>Our agricultural experts have reviewed your soil test results and provided recommendations:</p>
                        
                        <div class='recommendations'>
                            " . nl2br($recommendations) . "
                        </div>
                        
                        <p>Test Details:</p>
                        <ul>
                            <li>pH Level: {$soil_test['ph_level']}</li>
                            <li>Nitrogen: {$soil_test['nitrogen_content']}%</li>
                            <li>Phosphorus: {$soil_test['phosphorus_content']}%</li>
                            <li>Potassium: {$soil_test['potassium_content']}%</li>
                            <li>Test Date: " . date('F j, Y', strtotime($soil_test['test_date'])) . "</li>
                        </ul>
                        
                        <p>Login to your GrowGuide account to view the full details.</p>
                        
                        <p>Thank you for using GrowGuide!</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            $_SESSION['message'] = "Recommendations sent successfully to farmer.";
        } catch (Exception $e) {
            $_SESSION['message'] = "Error sending email: " . $mail->ErrorInfo;
        }
        
        header("Location: notifications.php");
        exit();
    } else {
        $error_message = "Error updating soil test: " . mysqli_error($conn);
    }
}

// Helper functions for soil test analysis
function getPHStatus($ph) {
    if ($ph < 5.5) return "Too Acidic";
    if ($ph > 6.5) return "Too Alkaline";
    return "Optimal";
}

function getPHColor($ph) {
    if ($ph < 5.5) return "#dc3545";
    if ($ph > 6.5) return "#ffc107";
    return "#28a745";
}

function getNitrogenStatus($value) {
    if ($value < 0.5) return ["Low", "#dc3545"];
    if ($value > 1.0) return ["High", "#ffc107"];
    return ["Optimal", "#28a745"];
}

function getPhosphorusStatus($value) {
    if ($value < 0.05) return ["Low", "#dc3545"];
    if ($value > 0.2) return ["High", "#ffc107"];
    return ["Optimal", "#28a745"];
}

function getPotassiumStatus($value) {
    if ($value < 1.0) return ["Low", "#dc3545"];
    if ($value > 2.0) return ["High", "#ffc107"];
    return ["Optimal", "#28a745"];
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respond to Soil Test - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Sidebar styles */
        .sidebar {
            background-color: #1B4D1B;
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
        }
        
        .logo {
            color: white;
            font-size: 24px;
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i {
            color: #4CAF50;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 5px 0;
        }
        
        .nav-item:hover, .nav-item.active {
            background-color: #2B7A30;
        }
        
        .nav-item i {
            width: 24px;
            margin-right: 10px;
        }
        
        .logout-btn {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .logout-btn i {
            width: 24px;
            margin-right: 10px;
        }
        
        /* Content styles */
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .soil-test-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .test-header h1 {
            margin: 0;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            background: #f0f0f0;
            color: #333;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #e0e0e0;
        }
        
        .test-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .farmer-info, .soil-results {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .farmer-info h3, .soil-results h3 {
            margin-top: 0;
            color: #1B4D1B;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }
        
        .soil-parameter {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        
        .param-name {
            font-weight: bold;
        }
        
        .param-value {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            color: white;
            font-size: 0.9em;
        }
        
        .recommendations-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .recommendations-form h3 {
            margin-top: 0;
            color: #1B4D1B;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        textarea {
            width: 100%;
            min-height: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            font-family: inherit;
            margin-bottom: 15px;
        }
        
        .submit-btn {
            background: #2B7A30;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        .submit-btn:hover {
            background: #1B4D1B;
        }
        
        .document-section {
            margin-top: 20px;
        }
        
        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            background: #f0f0f0;
            color: #333;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        .document-link:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-leaf"></i>
            <span>GrowGuide</span>
        </div>
        <a href="employe.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="employe_varities.php" class="nav-item">
            <i class="fas fa-seedling"></i>
            <span>Varieties</span>
        </a>
        <a href="notifications.php" class="nav-item active">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </a>
        <a href="employee_settings.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
    
    <div class="content">
        <div class="soil-test-container">
            <div class="test-header">
                <h1><i class="fas fa-flask"></i> Soil Test Review</h1>
                <a href="notifications.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Notifications
                </a>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="test-details">
                <div class="farmer-info">
                    <h3><i class="fas fa-user"></i> Farmer Information</h3>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span><?php echo htmlspecialchars($soil_test['farmer_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span><?php echo htmlspecialchars($soil_test['farmer_email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Test Date:</span>
                        <span><?php echo date('F j, Y', strtotime($soil_test['test_date'])); ?></span>
                    </div>
                    
                    <?php if (!empty($soil_test['document_path'])): ?>
                        <div class="document-section">
                            <h4>Uploaded Document</h4>
                            <a href="<?php echo htmlspecialchars($soil_test['document_path']); ?>" 
                               target="_blank" class="document-link">
                                <i class="fas fa-file-alt"></i> View Original Document
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="soil-results">
                    <h3><i class="fas fa-chart-bar"></i> Soil Test Results</h3>
                    
                    <div class="soil-parameter">
                        <span class="param-name">pH Level</span>
                        <div class="param-value">
                            <?php echo $soil_test['ph_level']; ?>
                            <span class="status-badge" style="background-color: <?php echo getPHColor($soil_test['ph_level']); ?>">
                                <?php echo getPHStatus($soil_test['ph_level']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php $n_status = getNitrogenStatus($soil_test['nitrogen_content']); ?>
                    <div class="soil-parameter">
                        <span class="param-name">Nitrogen</span>
                        <div class="param-value">
                            <?php echo $soil_test['nitrogen_content']; ?>%
                            <span class="status-badge" style="background-color: <?php echo $n_status[1]; ?>">
                                <?php echo $n_status[0]; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php $p_status = getPhosphorusStatus($soil_test['phosphorus_content']); ?>
                    <div class="soil-parameter">
                        <span class="param-name">Phosphorus</span>
                        <div class="param-value">
                            <?php echo $soil_test['phosphorus_content']; ?>%
                            <span class="status-badge" style="background-color: <?php echo $p_status[1]; ?>">
                                <?php echo $p_status[0]; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php $k_status = getPotassiumStatus($soil_test['potassium_content']); ?>
                    <div class="soil-parameter">
                        <span class="param-name">Potassium</span>
                        <div class="param-value">
                            <?php echo $soil_test['potassium_content']; ?>%
                            <span class="status-badge" style="background-color: <?php echo $k_status[1]; ?>">
                                <?php echo $k_status[0]; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php
                    $optimal_count = 0;
                    if ($soil_test['ph_level'] >= 5.5 && $soil_test['ph_level'] <= 6.5) $optimal_count++;
                    if ($soil_test['nitrogen_content'] >= 0.5 && $soil_test['nitrogen_content'] <= 1.0) $optimal_count++;
                    if ($soil_test['phosphorus_content'] >= 0.05 && $soil_test['phosphorus_content'] <= 0.2) $optimal_count++;
                    if ($soil_test['potassium_content'] >= 1.0 && $soil_test['potassium_content'] <= 2.0) $optimal_count++;
                    
                    $status_color = $optimal_count == 4 ? '#28a745' : 
                                  ($optimal_count >= 2 ? '#ffc107' : '#dc3545');
                    $status_text = $optimal_count == 4 ? 'Excellent' : 
                                 ($optimal_count >= 2 ? 'Fair' : 'Poor');
                    ?>
                    
                    <div class="soil-parameter">
                        <span class="param-name">Overall Status</span>
                        <div class="param-value">
                            <span class="status-badge" style="background-color: <?php echo $status_color; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="recommendations-form">
                <h3><i class="fas fa-clipboard-list"></i> Provide Recommendations</h3>
                
                <form method="POST" action="">
                    <textarea name="recommendations" placeholder="Enter your recommendations for the farmer based on their soil test results..."><?php 
                        if (isset($soil_test['recommendations'])) {
                            echo htmlspecialchars($soil_test['recommendations']);
                        } else {
                            // Provide a template based on soil test results
                            $template = "Based on your soil test results, here are our recommendations:\n\n";
                            
                            // pH recommendations
                            $template .= "pH Level (" . $soil_test['ph_level'] . "):\n";
                            if ($soil_test['ph_level'] < 5.5) {
                                $template .= "- Your soil is too acidic. We recommend applying agricultural lime at 2-3 tons/ha.\n";
                                $template .= "- Mix lime thoroughly with soil before planting.\n";
                                $template .= "- Allow 2-3 weeks before planting for lime to take effect.\n";
                            } elseif ($soil_test['ph_level'] > 6.5) {
                                $template .= "- Your soil is too alkaline. Consider applying agricultural sulfur at 1-2 tons/ha.\n";
                                $template .= "- Add organic matter to help lower pH gradually.\n";
                                $template .= "- Use acidifying fertilizers like ammonium sulfate if appropriate for your crops.\n";
                            } else {
                                $template .= "- Your soil pH is optimal. Maintain current soil management practices.\n";
                            }
                            
                            $template .= "\nNutrient Levels:\n";
                            
                            // Nitrogen recommendations
                            if ($soil_test['nitrogen_content'] < 0.5) {
                                $template .= "- Nitrogen is low. Apply NPK fertilizer with higher N content.\n";
                                $template .= "- Consider adding compost or organic matter.\n";
                                $template .= "- Plant nitrogen-fixing cover crops like legumes.\n";
                            } elseif ($soil_test['nitrogen_content'] > 1.0) {
                                $template .= "- Nitrogen is high. Reduce nitrogen applications temporarily.\n";
                                $template .= "- Use cover crops that absorb excess nitrogen.\n";
                            } else {
                                $template .= "- Nitrogen levels are optimal.\n";
                            }
                            
                            // Phosphorus recommendations
                            if ($soil_test['phosphorus_content'] < 0.05) {
                                $template .= "- Phosphorus is low. Apply phosphate fertilizers or bone meal.\n";
                                $template .= "- Consider mycorrhizal inoculants to improve phosphorus uptake.\n";
                            } elseif ($soil_test['phosphorus_content'] > 0.2) {
                                $template .= "- Phosphorus is high. Avoid further phosphorus applications.\n";
                                $template .= "- Implement erosion control measures to prevent phosphorus runoff.\n";
                            } else {
                                $template .= "- Phosphorus levels are optimal.\n";
                            }
                            
                            // Potassium recommendations
                            if ($soil_test['potassium_content'] < 1.0) {
                                $template .= "- Potassium is low. Apply potassium-rich fertilizers or wood ash.\n";
                                $template .= "- Consider adding compost rich in potassium.\n";
                            } elseif ($soil_test['potassium_content'] > 2.0) {
                                $template .= "- Potassium is high. Reduce potassium applications.\n";
                                $template .= "- Monitor plants for magnesium deficiency which can occur with excess potassium.\n";
                            } else {
                                $template .= "- Potassium levels are optimal.\n";
                            }
                            
                            echo $template;
                        }
                    ?></textarea>
                    
                    <button type="submit" name="send_recommendations" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Send Recommendations to Farmer
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 