<?php
// File: includes/weather_utils.php

function getWeatherAlerts($location) {
    // This is a simplified implementation
    // In a real application, you would integrate with a weather API
    
    // For demonstration, return some sample alerts based on current month
    $month = date('n');
    $alerts = [];
    
    // Sample seasonal alerts
    switch($month) {
        case 12:
        case 1:
        case 2:
            $alerts[] = "Winter season: Watch for frost conditions";
            break;
        case 3:
        case 4:
        case 5:
            $alerts[] = "Spring season: Moderate rainfall expected";
            break;
        case 6:
        case 7:
        case 8:
            $alerts[] = "Summer season: High temperature alert";
            break;
        case 9:
        case 10:
        case 11:
            $alerts[] = "Fall season: Variable weather conditions";
            break;
    }
    
    // Add a general alert based on the location
    if (!empty($location)) {
        $alerts[] = "Weather forecast for $location: Normal conditions";
    }
    
    return $alerts;
}

function getForecast($location) {
    // Placeholder for future weather forecast implementation
    return [
        'temperature' => rand(20, 35),
        'humidity' => rand(40, 80),
        'rainfall_chance' => rand(0, 100)
    ];
}
?>