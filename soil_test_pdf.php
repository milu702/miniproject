<?php
session_start();
require_once 'config.php';

// Include the TCPDF library
require_once('C:/Users/HP/Downloads/tcpdf_6_3_2/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('GrowGuide');
$pdf->SetAuthor('GrowGuide System');
$pdf->SetTitle('Soil Test Report');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get test ID from URL
$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;

// Fetch soil test data
$query = "SELECT st.*, u.username, u.farm_location 
          FROM soil_tests st 
          JOIN users u ON st.farmer_id = u.id 
          WHERE st.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $test_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$test = mysqli_fetch_assoc($result);

if ($test) {
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'Soil Test Report', 0, 1, 'C');
    $pdf->Ln(10);

    // Farmer Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Farmer Information:', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 7, 'Name: ' . $test['username'], 0, 1);
    $pdf->Cell(0, 7, 'Location: ' . $test['farm_location'], 0, 1);
    $pdf->Cell(0, 7, 'Test Date: ' . date('F j, Y', strtotime($test['test_date'])), 0, 1);
    $pdf->Ln(10);

    // Test Results
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Test Results:', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    // Create table header
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(60, 7, 'Parameter', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Value', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Status', 1, 1, 'C', true);

    // pH Level
    $pdf->Cell(60, 7, 'pH Level', 1);
    $pdf->Cell(60, 7, $test['ph_level'], 1, 0, 'C');
    $pdf->Cell(60, 7, getPHStatus($test['ph_level']), 1, 1, 'C');

    // Nitrogen
    $n_status = getNitrogenStatus($test['nitrogen_content']);
    $pdf->Cell(60, 7, 'Nitrogen (N)', 1);
    $pdf->Cell(60, 7, $test['nitrogen_content'] . '%', 1, 0, 'C');
    $pdf->Cell(60, 7, $n_status[0], 1, 1, 'C');

    // Phosphorus
    $p_status = getPhosphorusStatus($test['phosphorus_content']);
    $pdf->Cell(60, 7, 'Phosphorus (P)', 1);
    $pdf->Cell(60, 7, $test['phosphorus_content'] . '%', 1, 0, 'C');
    $pdf->Cell(60, 7, $p_status[0], 1, 1, 'C');

    // Potassium
    $k_status = getPotassiumStatus($test['potassium_content']);
    $pdf->Cell(60, 7, 'Potassium (K)', 1);
    $pdf->Cell(60, 7, $test['potassium_content'] . '%', 1, 0, 'C');
    $pdf->Cell(60, 7, $k_status[0], 1, 1, 'C');

    $pdf->Ln(10);

    // Recommendations
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Recommendations:', 0, 1);
    $pdf->SetFont('helvetica', '', 12);

    // Add recommendations based on test results
    if ($test['ph_level'] < 5.5) {
        $pdf->Cell(0, 7, '• Add lime to increase pH level', 0, 1);
    } elseif ($test['ph_level'] > 6.5) {
        $pdf->Cell(0, 7, '• Add sulfur to decrease pH level', 0, 1);
    }

    if ($n_status[0] === 'Low') {
        $pdf->Cell(0, 7, '• Add nitrogen-rich organic matter', 0, 1);
    } elseif ($n_status[0] === 'High') {
        $pdf->Cell(0, 7, '• Reduce nitrogen application', 0, 1);
    }

    if ($p_status[0] === 'Low') {
        $pdf->Cell(0, 7, '• Apply bone meal or rock phosphate', 0, 1);
    } elseif ($p_status[0] === 'High') {
        $pdf->Cell(0, 7, '• Reduce phosphorus application', 0, 1);
    }

    if ($k_status[0] === 'Low') {
        $pdf->Cell(0, 7, '• Add wood ash or potassium-rich fertilizers', 0, 1);
    } elseif ($k_status[0] === 'High') {
        $pdf->Cell(0, 7, '• Reduce potassium application', 0, 1);
    }

    // Output the PDF
    $pdf->Output('soil_test_report_' . $test_id . '.pdf', 'D');
} else {
    die('Soil test not found');
}

// Helper functions
function getPHStatus($ph) {
    if ($ph < 6.0) return 'Acidic';
    if ($ph > 7.5) return 'Alkaline';
    return 'Optimal';
}

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
?> 