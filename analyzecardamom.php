<?php
session_start();
require_once 'config.php';

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

class CardamomAnalyzer {
    private $idealConditions = [
        'soil' => [
            'ph' => [
                'min' => 6.0,
                'max' => 7.0,
                'optimal' => 6.5
            ],
            'nitrogen' => [
                'min' => 0.8,
                'max' => 1.5,
                'optimal' => 1.2
            ],
            'phosphorus' => [
                'min' => 0.15,
                'max' => 0.35,
                'optimal' => 0.25
            ],
            'potassium' => [
                'min' => 1.0,
                'max' => 2.5,
                'optimal' => 1.8
            ],
            'moisture' => [
                'min' => 60,
                'max' => 80,
                'optimal' => 70
            ]
        ],
        'weather' => [
            'temperature' => [
                'min' => 10,
                'max' => 35,
                'optimal' => 22
            ],
            'humidity' => [
                'min' => 60,
                'max' => 90,
                'optimal' => 75
            ],
            'rainfall_annual' => [
                'min' => 1500,
                'max' => 4000,
                'optimal' => 2500
            ]
        ]
    ];

    public function analyzeSoilConditions($soilData) {
        $analysis = [
            'suitable' => true,
            'score' => 0,
            'recommendations' => [],
            'conditions' => []
        ];

        // Check pH
        $analysis['conditions']['ph'] = [
            'value' => $soilData['ph_level'],
            'status' => $this->getConditionStatus($soilData['ph_level'], $this->idealConditions['soil']['ph'])
        ];
        if ($soilData['ph_level'] < $this->idealConditions['soil']['ph']['min']) {
            $analysis['recommendations'][] = "Soil pH is too acidic. Consider adding agricultural lime to raise pH.";
            $analysis['score'] -= 20;
        } elseif ($soilData['ph_level'] > $this->idealConditions['soil']['ph']['max']) {
            $analysis['recommendations'][] = "Soil pH is too alkaline. Consider adding organic matter or sulfur to lower pH.";
            $analysis['score'] -= 20;
        }

        // Check Nitrogen
        $analysis['conditions']['nitrogen'] = [
            'value' => $soilData['nitrogen_content'],
            'status' => $this->getConditionStatus($soilData['nitrogen_content'], $this->idealConditions['soil']['nitrogen'])
        ];
        if ($soilData['nitrogen_content'] < $this->idealConditions['soil']['nitrogen']['min']) {
            $analysis['recommendations'][] = "Low nitrogen levels. Add nitrogen-rich fertilizers or organic matter.";
            $analysis['score'] -= 15;
        }

        // Check Phosphorus
        $analysis['conditions']['phosphorus'] = [
            'value' => $soilData['phosphorus_content'],
            'status' => $this->getConditionStatus($soilData['phosphorus_content'], $this->idealConditions['soil']['phosphorus'])
        ];
        if ($soilData['phosphorus_content'] < $this->idealConditions['soil']['phosphorus']['min']) {
            $analysis['recommendations'][] = "Low phosphorus levels. Add phosphate fertilizers or bone meal.";
            $analysis['score'] -= 15;
        }

        // Check Potassium
        $analysis['conditions']['potassium'] = [
            'value' => $soilData['potassium_content'],
            'status' => $this->getConditionStatus($soilData['potassium_content'], $this->idealConditions['soil']['potassium'])
        ];
        if ($soilData['potassium_content'] < $this->idealConditions['soil']['potassium']['min']) {
            $analysis['recommendations'][] = "Low potassium levels. Add potash fertilizers or wood ash.";
            $analysis['score'] -= 15;
        }

        // Check Moisture
        $analysis['conditions']['moisture'] = [
            'value' => $soilData['moisture_content'],
            'status' => $this->getConditionStatus($soilData['moisture_content'], $this->idealConditions['soil']['moisture'])
        ];
        if ($soilData['moisture_content'] < $this->idealConditions['soil']['moisture']['min']) {
            $analysis['recommendations'][] = "Soil moisture is low. Improve irrigation and add organic matter for better water retention.";
            $analysis['score'] -= 15;
        }

        $analysis['score'] = max(0, 100 + $analysis['score']);
        $analysis['suitable'] = $analysis['score'] >= 60;

        return $analysis;
    }

    public function analyzeWeatherConditions($weatherData) {
        $analysis = [
            'suitable' => true,
            'score' => 0,
            'recommendations' => [],
            'conditions' => []
        ];

        // Check Temperature
        $analysis['conditions']['temperature'] = [
            'value' => $weatherData['temperature'],
            'status' => $this->getConditionStatus($weatherData['temperature'], $this->idealConditions['weather']['temperature'])
        ];
        if ($weatherData['temperature'] < $this->idealConditions['weather']['temperature']['min']) {
            $analysis['recommendations'][] = "Temperature is too low. Consider using shade nets or windbreaks.";
            $analysis['score'] -= 20;
        } elseif ($weatherData['temperature'] > $this->idealConditions['weather']['temperature']['max']) {
            $analysis['recommendations'][] = "Temperature is too high. Increase shade and irrigation.";
            $analysis['score'] -= 20;
        }

        // Check Humidity
        $analysis['conditions']['humidity'] = [
            'value' => $weatherData['humidity'],
            'status' => $this->getConditionStatus($weatherData['humidity'], $this->idealConditions['weather']['humidity'])
        ];
        if ($weatherData['humidity'] < $this->idealConditions['weather']['humidity']['min']) {
            $analysis['recommendations'][] = "Humidity is too low. Consider using misting systems or increasing irrigation.";
            $analysis['score'] -= 15;
        }

        // Check Rainfall
        $analysis['conditions']['rainfall'] = [
            'value' => $weatherData['rainfall_annual'],
            'status' => $this->getConditionStatus($weatherData['rainfall_annual'], $this->idealConditions['weather']['rainfall_annual'])
        ];
        if ($weatherData['rainfall_annual'] < $this->idealConditions['weather']['rainfall_annual']['min']) {
            $analysis['recommendations'][] = "Annual rainfall is insufficient. Implement irrigation system.";
            $analysis['score'] -= 15;
        }

        $analysis['score'] = max(0, 100 + $analysis['score']);
        $analysis['suitable'] = $analysis['score'] >= 60;

        return $analysis;
    }

    private function getConditionStatus($value, $idealRange) {
        if ($value < $idealRange['min']) return 'low';
        if ($value > $idealRange['max']) return 'high';
        if (abs($value - $idealRange['optimal']) <= ($idealRange['max'] - $idealRange['min']) / 4) return 'optimal';
        return 'acceptable';
    }

    public function getOverallSuitability($soilAnalysis, $weatherAnalysis) {
        $overallScore = ($soilAnalysis['score'] + $weatherAnalysis['score']) / 2;
        
        $suitabilityLevel = '';
        if ($overallScore >= 90) $suitabilityLevel = 'Excellent';
        elseif ($overallScore >= 75) $suitabilityLevel = 'Good';
        elseif ($overallScore >= 60) $suitabilityLevel = 'Moderate';
        else $suitabilityLevel = 'Poor';

        return [
            'suitable' => $overallScore >= 60,
            'suitability_level' => $suitabilityLevel,
            'overall_score' => round($overallScore, 1),
            'soil_score' => round($soilAnalysis['score'], 1),
            'weather_score' => round($weatherAnalysis['score'], 1),
            'soil_conditions' => $soilAnalysis['conditions'],
            'weather_conditions' => $weatherAnalysis['conditions'],
            'recommendations' => array_merge(
                $soilAnalysis['recommendations'],
                $weatherAnalysis['recommendations']
            )
        ];
    }
}

// Handle analysis request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $analyzer = new CardamomAnalyzer();
    $response = ['success' => false, 'message' => '', 'data' => null];

    try {
        // Get the latest soil test data
        $soil_query = "SELECT * FROM soil_tests WHERE user_id = ? ORDER BY test_date DESC LIMIT 1";
        $stmt = $conn->prepare($soil_query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $soil_result = $stmt->get_result()->fetch_assoc();

        if (!$soil_result) {
            throw new Exception("No soil test data found. Please complete a soil test first.");
        }

        // Prepare soil data
        $soilData = [
            'ph_level' => $soil_result['ph_level'],
            'nitrogen_content' => $soil_result['nitrogen_content'],
            'phosphorus_content' => $soil_result['phosphorus_content'],
            'potassium_content' => $soil_result['potassium_content'],
            'moisture_content' => $soil_result['moisture_content']
        ];

        // Get weather data (you'll need to modify this based on your weather data source)
        $weatherData = [
            'temperature' => $_POST['temperature'] ?? 22,
            'humidity' => $_POST['humidity'] ?? 75,
            'rainfall_annual' => $_POST['rainfall_annual'] ?? 2500
        ];

        // Perform analysis
        $soilAnalysis = $analyzer->analyzeSoilConditions($soilData);
        $weatherAnalysis = $analyzer->analyzeWeatherConditions($weatherData);
        $overallAnalysis = $analyzer->getOverallSuitability($soilAnalysis, $weatherAnalysis);

        $response['success'] = true;
        $response['data'] = $overallAnalysis;

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardamom Growing Conditions Analysis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .analysis-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .score-card {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .score-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c5282;
        }

        .condition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .condition-item {
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .status-optimal { color: #38a169; }
        .status-acceptable { color: #3182ce; }
        .status-low { color: #e53e3e; }
        .status-high { color: #dd6b20; }

        .recommendations {
            margin: 20px 0;
            padding: 15px;
            background: #ebf8ff;
            border-radius: 8px;
        }

        .recommendations li {
            margin: 10px 0;
            color: #2c5282;
        }
    </style>
</head>
<body>
    <div class="analysis-container">
        <h2>Cardamom Growing Conditions Analysis</h2>
        <button id="analyzeBtn" class="btn btn-primary">
            Analyze Conditions
        </button>
        <div id="analysisResults"></div>
    </div>

    <script>
    document.getElementById('analyzeBtn').addEventListener('click', async () => {
        try {
            const response = await fetch('analyze_cardamom.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    temperature: 22,  // You can modify these values or get them from form inputs
                    humidity: 75,
                    rainfall_annual: 2500
                })
            });

            const data = await response.json();
            if (data.success) {
                displayResults(data.data);
            } else {
                alert(data.message || 'Error performing analysis');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error performing analysis');
        }
    });

    function displayResults(analysis) {
        const resultsDiv = document.getElementById('analysisResults');
        
        const getStatusClass = (status) => {
            switch(status) {
                case 'optimal': return 'status-optimal';
                case 'acceptable': return 'status-acceptable';
                case 'low': return 'status-low';
                case 'high': return 'status-high';
                default: return '';
            }
        };

        resultsDiv.innerHTML = `
            <div class="score-card">
                <h3>Overall Suitability: ${analysis.suitability_level}</h3>
                <div class="score-value">${analysis.overall_score}%</div>
                <p>Soil Score: ${analysis.soil_score}% | Weather Score: ${analysis.weather_score}%</p>
            </div>

            <h3>Soil Conditions</h3>
            <div class="condition-grid">
                ${Object.entries(analysis.soil_conditions).map(([key, value]) => `
                    <div class="condition-item">
                        <h4>${key.charAt(0).toUpperCase() + key.slice(1)}</h4>
                        <p class="${getStatusClass(value.status)}">${value.value} (${value.status})</p>
                    </div>
                `).join('')}
            </div>

            <h3>Weather Conditions</h3>
            <div class="condition-grid">
                ${Object.entries(analysis.weather_conditions).map(([key, value]) => `
                    <div class="condition-item