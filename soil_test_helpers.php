<?php
// Helper functions for soil test processing

// Extract soil test data from uploaded files (PDF or images)
function extractSoilTestData($file) {
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    try {
        if ($file_extension == "pdf") {
            // Simple text extraction from PDF
            $content = strip_tags(file_get_contents($file["tmp_name"]));
            return extractValuesFromText($content);
        } 
        else if (in_array($file_extension, ["jpg", "jpeg", "png"])) {
            // For images, you'd need OCR capabilities
            // This is a simplified placeholder
            return false;
        }
    } catch (Exception $e) {
        error_log("Error extracting soil test data: " . $e->getMessage());
        return false;
    }
    
    return false;
}

// Extract values from text using pattern matching
function extractValuesFromText($text) {
    $patterns = [
        'ph' => '/pH\s*(?:level|value)?[:=\s]*(\d+\.?\d*)/i',
        'nitrogen' => '/(?:nitrogen|N)\s*(?:content|level|value)?[:=\s]*(\d+\.?\d*)/i',
        'phosphorus' => '/(?:phosphorus|P)\s*(?:content|level|value)?[:=\s]*(\d+\.?\d*)/i',
        'potassium' => '/(?:potassium|K)\s*(?:content|level|value)?[:=\s]*(\d+\.?\d*)/i'
    ];
    
    $results = [];
    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $results[$key] = floatval($matches[1]);
        }
    }
    
    // As long as we have the essential NPK and pH values, consider it valid
    if (isset($results['ph']) && isset($results['nitrogen']) && 
        isset($results['phosphorus']) && isset($results['potassium'])) {
        return $results;
    }
    
    return false;
}

// Generate fertilizer recommendations
function generateCustomFertilizerRecommendations($soil_data) {
    $recommendations = [];
    
    // pH based recommendations
    if ($soil_data['ph'] < 5.5) {
        $recommendations[] = [
            'type' => 'Amendment',
            'name' => 'Agricultural Lime (Calcium Carbonate)',
            'amount' => '2-3 tons per hectare',
            'application' => 'Incorporate thoroughly into soil 2-3 months before planting'
        ];
    } elseif ($soil_data['ph'] > 7.5) {
        $recommendations[] = [
            'type' => 'Amendment',
            'name' => 'Elemental Sulfur',
            'amount' => '300-500 kg per hectare',
            'application' => 'Apply 2-3 months before planting and incorporate into soil'
        ];
    }
    
    // Nitrogen recommendations
    if ($soil_data['nitrogen'] < 0.5) {
        $recommendations[] = [
            'type' => 'Fertilizer',
            'name' => 'Urea (46-0-0)',
            'amount' => '100-150 kg per hectare',
            'application' => 'Split application: 50% at planting, 50% during vegetative growth'
        ];
    } elseif ($soil_data['nitrogen'] > 1.0) {
        $recommendations[] = [
            'type' => 'Management',
            'name' => 'Reduce Nitrogen Input',
            'amount' => 'N/A',
            'application' => 'Plant cover crops like cereals to use excess nitrogen'
        ];
    }
    
    // Phosphorus recommendations
    if ($soil_data['phosphorus'] < 0.05) {
        $recommendations[] = [
            'type' => 'Fertilizer',
            'name' => 'Triple Superphosphate (0-46-0)',
            'amount' => '100-150 kg per hectare',
            'application' => 'Apply before planting and incorporate into soil'
        ];
    } elseif ($soil_data['phosphorus'] > 0.2) {
        $recommendations[] = [
            'type' => 'Management',
            'name' => 'Avoid Phosphorus Fertilizers',
            'amount' => 'N/A',
            'application' => 'Plant cover crops to prevent phosphorus runoff'
        ];
    }
    
    // Potassium recommendations
    if ($soil_data['potassium'] < 1.0) {
        $recommendations[] = [
            'type' => 'Fertilizer',
            'name' => 'Potassium Chloride (0-0-60)',
            'amount' => '100-150 kg per hectare',
            'application' => 'Apply before planting and incorporate into soil'
        ];
    } elseif ($soil_data['potassium'] > 2.0) {
        $recommendations[] = [
            'type' => 'Management',
            'name' => 'Avoid Potassium Fertilizers',
            'amount' => 'N/A',
            'application' => 'Monitor calcium and magnesium levels for imbalances'
        ];
    }
    
    return $recommendations;
}

// Generate pesticide recommendations
function generateCustomPesticideRecommendations($soil_data) {
    $recommendations = [];
    
    // Acidic soil recommendations
    if ($soil_data['ph'] < 5.5) {
        $recommendations[] = [
            'type' => 'Fungicide',
            'name' => 'Copper-based Fungicide',
            'target' => 'Root rot and fungal diseases common in acidic soils',
            'application' => 'Apply according to package instructions when disease symptoms appear'
        ];
    }
    
    // Alkaline soil recommendations
    if ($soil_data['ph'] > 7.0) {
        $recommendations[] = [
            'type' => 'Insecticide',
            'name' => 'Neem Oil',
            'target' => 'Aphids and whiteflies common in alkaline conditions',
            'application' => 'Apply weekly as a preventative measure during pest season'
        ];
    }
    
    // High nitrogen pest issues
    if ($soil_data['nitrogen'] > 1.0) {
        $recommendations[] = [
            'type' => 'Insecticide',
            'name' => 'Bacillus thuringiensis (Bt)',
            'target' => 'Caterpillars attracted to nitrogen-rich plants',
            'application' => 'Apply every 7-10 days when pests are present'
        ];
    }
    
    // Always add a basic IPM recommendation
    $recommendations[] = [
        'type' => 'IPM',
        'name' => 'Integrated Pest Management',
        'target' => 'All pests and diseases',
        'application' => 'Regular scouting, crop rotation, beneficial insects, and targeted interventions only when necessary'
    ];
    
    return $recommendations;
}
?>