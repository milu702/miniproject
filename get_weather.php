<?php
header('Content-Type: application/json');

$location = $_GET['location'] ?? '';
$api_key = "cc02c9dee7518466102e748f211bca05";

if (empty($location)) {
    echo json_encode(['error' => 'Location not specified']);
    exit;
}

$url = "http://api.openweathermap.org/data/2.5/weather?q=" . urlencode($location) . 
       "&units=metric&appid=" . $api_key;

$response = file_get_contents($url);

if ($response === FALSE) {
    echo json_encode(['error' => 'Failed to fetch weather data']);
    exit;
}

$data = json_decode($response, true);

if ($data['cod'] != 200) {
    echo json_encode(['error' => 'Weather data not available']);
    exit;
}

echo json_encode([
    'temp' => round($data['main']['temp']),
    'humidity' => $data['main']['humidity'],
    'description' => ucfirst($data['weather'][0]['description'])
]); 