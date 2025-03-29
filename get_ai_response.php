<?php
session_start();
require_once 'config.php';

// Get the query from POST request
$query = $_POST['query'] ?? '';
$username = $_SESSION['username'] ?? 'Farmer';

function getAIResponse($query, $username) {
    $query = strtolower(trim($query));
    $hour = (int)date('H');
    
    // Handle greetings
    if (preg_match('/^(hi|hello|hey|hai|greetings)/i', $query)) {
        $greeting = '';
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good morning';
        } elseif ($hour >= 12 && $hour < 17) {
            $greeting = 'Good afternoon';
        } elseif ($hour >= 17 && $hour < 22) {
            $greeting = 'Good evening';
        } else {
            $greeting = 'Hello';
        }
        
        return "$greeting, $username! 👋\n\n" .
               "I'm your AI Farming Assistant, and I'm here to help you succeed in cardamom cultivation! 🌱\n\n" .
               "You can ask me about:\n" .
               "• 🌿 Planting techniques and timing\n" .
               "• 💧 Irrigation and water management\n" .
               "• 🔍 Disease identification and control\n" .
               "• 🐛 Pest management\n" .
               "• 🌱 Fertilizer recommendations\n" .
               "• 🌾 Harvesting guidelines\n" .
               "• ☁️ Weather impacts\n" .
               "• 📊 Market prices and trends\n\n" .
               "To get the best answers, try to:\n" .
               "1. Be specific in your questions\n" .
               "2. Mention the growth stage of your plants\n" .
               "3. Include any relevant symptoms or observations\n\n" .
               "Example questions:\n" .
               "✓ \"What's the ideal spacing for planting cardamom?\"\n" .
               "✓ \"My cardamom leaves are turning yellow, what could be wrong?\"\n" .
               "✓ \"When is the best time to harvest cardamom pods?\"\n" .
               "✓ \"How much water do cardamom plants need during flowering?\"\n\n" .
               "Feel free to ask any questions! I'm here to help you grow healthy and productive cardamom plants! 🌿";
    }
    
    // Handle different types of queries
    if (strpos($query, 'plant') !== false) {
        return "For planting cardamom:\n\n" .
               "• Best time: Pre-monsoon (May-June)\n" .
               "• Spacing: 2m x 2m\n" .
               "• Soil: Well-drained, rich in organic matter\n" .
               "• pH: 6.0-6.5\n" .
               "• Shade: 60-70% recommended";
    }
    
    if (strpos($query, 'disease') !== false) {
        return "Common cardamom diseases:\n\n" .
               "1. Capsule rot (Phytophthora)\n" .
               "2. Rhizome rot\n" .
               "3. Katte virus\n" .
               "4. Mosaic virus\n\n" .
               "Prevention:\n" .
               "• Good drainage\n" .
               "• Regular monitoring\n" .
               "• Proper spacing\n" .
               "• Fungicide application when needed";
    }
    
    if (strpos($query, 'water') !== false || strpos($query, 'irrigation') !== false) {
        return "Cardamom irrigation guidelines:\n\n" .
               "• Water requirement: 1500-4000mm/year\n" .
               "• Frequency: Every 7-10 days in dry season\n" .
               "• Method: Sprinkler irrigation preferred\n" .
               "• Important: Avoid waterlogging";
    }
    
    // Default response
    return "I can help you with cardamom farming questions about:\n" .
           "• Planting techniques\n" .
           "• Disease management\n" .
           "• Irrigation\n" .
           "• Pest control\n" .
           "• Harvesting\n\n" .
           "Please ask a specific question about any of these topics!";
}

// Get and send the response
$response = getAIResponse($query, $username);
echo $response;
?> 