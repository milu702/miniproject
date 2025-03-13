<?php
session_start();
require_once 'db_connection.php';

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Get farmer and test IDs from URL
$farmer_id = $_GET['farmer_id'] ?? '';
$soil_test_id = $_GET['soil_test_id'] ?? '';

// Fetch soil test data
$soil_query = "SELECT st.*, u.username as farmer_name 
               FROM soil_tests st 
               JOIN users u ON st.farmer_id = u.id 
               WHERE st.id = ? AND st.recommendation_status = 'pending'";
$stmt = mysqli_prepare($conn, $soil_query);
mysqli_stmt_bind_param($stmt, "i", $soil_test_id);
mysqli_stmt_execute($stmt);
$soil_result = mysqli_stmt_get_result($stmt);
$soil_data = mysqli_fetch_assoc($soil_result);

// Fetch latest weather data for the farmer
$weather_query = "SELECT * FROM weather_conditions 
                 WHERE farmer_id = ? 
                 ORDER BY recorded_date DESC LIMIT 1";
$stmt = mysqli_prepare($conn, $weather_query);
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$weather_result = mysqli_stmt_get_result($stmt);
$weather_data = mysqli_fetch_assoc($weather_result);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recommendation = $_POST['recommendation'];
    $fertilizer_suggestion = $_POST['fertilizer_suggestion'];
    $irrigation_advice = $_POST['irrigation_advice'];
    $additional_notes = $_POST['additional_notes'];
    
    $insert_query = "INSERT INTO recommendations 
                    (farmer_id, soil_test_id, recommendation, fertilizer_suggestion, 
                     irrigation_advice, additional_notes, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "iissssi", 
        $farmer_id, 
        $soil_test_id, 
        $recommendation, 
        $fertilizer_suggestion, 
        $irrigation_advice, 
        $additional_notes,
        $_SESSION['user_id']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        // Update soil test status
        mysqli_query($conn, "UPDATE soil_tests SET recommendation_status = 'completed' WHERE id = $soil_test_id");
        header("Location: employe.php?success=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Recommendation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Add your existing styles here */
        .recommendation-form {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .data-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="recommendation-form">
        <h2>Create Recommendation for <?php echo htmlspecialchars($soil_data['farmer_name']); ?></h2>
        
        <div class="data-summary">
            <div class="soil-data">
                <h3>Soil Test Results</h3>
                <p>pH Level: <?php echo $soil_data['ph_level']; ?></p>
                <p>Nitrogen: <?php echo $soil_data['nitrogen_content']; ?>%</p>
                <p>Phosphorus: <?php echo $soil_data['phosphorus_content']; ?>%</p>
                <p>Potassium: <?php echo $soil_data['potassium_content']; ?>%</p>
            </div>
            
            <div class="weather-data">
                <h3>Weather Conditions</h3>
                <p>Temperature: <?php echo $weather_data['temperature']; ?>°C</p>
                <p>Humidity: <?php echo $weather_data['humidity']; ?>%</p>
                <p>Rainfall: <?php echo $weather_data['rainfall']; ?> mm</p>
                <p>Soil Moisture: <?php echo $weather_data['soil_moisture']; ?>%</p>
            </div>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="recommendation">General Recommendations</label>
                <textarea name="recommendation" id="recommendation" required
                    placeholder="Based on soil and weather conditions, provide general recommendations..."></textarea>
            </div>

            <div class="form-group">
                <label for="fertilizer_suggestion">Fertilizer Suggestions</label>
                <textarea name="fertilizer_suggestion" id="fertilizer_suggestion" required
                    placeholder="Recommend specific fertilizers and application methods..."></textarea>
            </div>

            <div class="form-group">
                <label for="irrigation_advice">Irrigation Advice</label>
                <textarea name="irrigation_advice" id="irrigation_advice" required
                    placeholder="Provide irrigation schedule and methods based on weather conditions..."></textarea>
            </div>

            <div class="form-group">
                <label for="additional_notes">Additional Notes</label>
                <textarea name="additional_notes" id="additional_notes"
                    placeholder="Any other important information or precautions..."></textarea>
            </div>

            <button type="submit" class="submit-btn">Submit Recommendation</button>
        </form>
    </div>
</body>
</html> 