<?php
session_start();
require_once 'config.php';
require_once 'C:/Users/HP/Downloads/tcpdf_6_3_2/tcpdf.php'; // Update this path to your TCPDF location

// Include helper functions
require_once 'soil_test_helpers.php'; // Create this new file

if (!isset($_GET['test_id']) || !isset($_SESSION['user_id'])) {
    die('Invalid request');
}

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get soil test data
$test_id = intval($_GET['test_id']);
$query = "SELECT st.*, u.username as farmer_name, u.farm_location 
          FROM soil_tests st 
          JOIN users u ON st.farmer_id = u.id 
          WHERE st.id = ? AND st.farmer_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $test_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$test = mysqli_fetch_assoc($result);

if (!$test) {
    die('Soil test not found');
}

// Create new file soil_test_helpers.php with these functions
class SoilTestHelpers {
    public static function getPHStatus($ph) {
        if ($ph < 6.0) return 'Acidic';
        if ($ph > 7.5) return 'Alkaline';
        return 'Optimal';
    }

    public static function getNitrogenStatus($value) {
        if ($value < 0.5) return ['Low', '#ff6b6b'];
        if ($value > 1.0) return ['High', '#4d96ff'];
        return ['Optimal', '#69db7c'];
    }

    public static function getPhosphorusStatus($value) {
        if ($value < 0.05) return ['Low', '#ff6b6b'];
        if ($value > 0.2) return ['High', '#4d96ff'];
        return ['Optimal', '#69db7c'];
    }

    public static function getPotassiumStatus($value) {
        if ($value < 1.0) return ['Low', '#ff6b6b'];
        if ($value > 2.0) return ['High', '#4d96ff'];
        return ['Optimal', '#69db7c'];
    }
}

// Extend TCPDF with custom header and footer
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'Soil Test Report', 0, true, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('GrowGuide');
$pdf->SetAuthor('GrowGuide System');
$pdf->SetTitle('Soil Test Report');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// Set margins
$pdf->SetMargins(15, 30, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Farmer Information
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Farmer Information', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, 'Name: ' . $test['farmer_name'], 0, 1, 'L');
$pdf->Cell(0, 8, 'Location: ' . $test['farm_location'], 0, 1, 'L');
$pdf->Cell(0, 8, 'Test Date: ' . date('F j, Y', strtotime($test['test_date'])), 0, 1, 'L');
$pdf->Ln(10);

// Soil Test Results
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Soil Test Results', 0, 1, 'L');

// Create results table
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(60, 10, 'Parameter', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Value', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Status', 1, 1, 'C', true);

// pH Level
$ph_status = SoilTestHelpers::getPHStatus($test['ph_level']);
$pdf->Cell(60, 10, 'pH Level', 1, 0, 'L');
$pdf->Cell(60, 10, $test['ph_level'], 1, 0, 'C');
$pdf->Cell(60, 10, $ph_status, 1, 1, 'C');

// Nitrogen
$n_status = SoilTestHelpers::getNitrogenStatus($test['nitrogen_content']);
$pdf->Cell(60, 10, 'Nitrogen (N)', 1, 0, 'L');
$pdf->Cell(60, 10, $test['nitrogen_content'] . '%', 1, 0, 'C');
$pdf->Cell(60, 10, $n_status[0], 1, 1, 'C');

// Phosphorus
$p_status = SoilTestHelpers::getPhosphorusStatus($test['phosphorus_content']);
$pdf->Cell(60, 10, 'Phosphorus (P)', 1, 0, 'L');
$pdf->Cell(60, 10, $test['phosphorus_content'] . '%', 1, 0, 'C');
$pdf->Cell(60, 10, $p_status[0], 1, 1, 'C');

// Potassium
$k_status = SoilTestHelpers::getPotassiumStatus($test['potassium_content']);
$pdf->Cell(60, 10, 'Potassium (K)', 1, 0, 'L');
$pdf->Cell(60, 10, $test['potassium_content'] . '%', 1, 0, 'C');
$pdf->Cell(60, 10, $k_status[0], 1, 1, 'C');

$pdf->Ln(10);

// Recommendations
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Recommendations', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);

// Add recommendations based on test results
$recommendations = array();

if ($test['ph_level'] < 5.5) {
    $recommendations[] = "Add lime to increase pH level";
} elseif ($test['ph_level'] > 6.5) {
    $recommendations[] = "Add sulfur to decrease pH level";
}

if ($n_status[0] === 'Low') {
    $recommendations[] = "Add nitrogen-rich organic matter or compost";
} elseif ($n_status[0] === 'High') {
    $recommendations[] = "Reduce nitrogen application";
}

if ($p_status[0] === 'Low') {
    $recommendations[] = "Apply bone meal or rock phosphate";
} elseif ($p_status[0] === 'High') {
    $recommendations[] = "Reduce phosphorus application";
}

if ($k_status[0] === 'Low') {
    $recommendations[] = "Add wood ash or potassium-rich fertilizers";
} elseif ($k_status[0] === 'High') {
    $recommendations[] = "Reduce potassium application";
}

foreach ($recommendations as $recommendation) {
    $pdf->Cell(0, 10, '• ' . $recommendation, 0, 1, 'L');
}

// Output PDF
$pdf->Output('soil_test_report_' . $test_id . '.pdf', 'D');
?> 