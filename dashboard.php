<?php
// Add any dashboard-specific PHP logic here
$total_farmers = 3;
$total_land = 0.00;
$total_varieties = 0;
$total_employees = 1;
$total_soil_tests = 0;

// Start output buffering
ob_start();
?>

<!-- Dashboard-specific styles -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }

    .stat-card h3 {
        font-size: 1.8rem;
        margin-bottom: 5px;
        color: #2e7d32;
    }

    .stat-card p {
        color: #666;
        font-size: 0.9rem;
    }

    .stat-card i {
        position: absolute;
        right: 20px;
        bottom: 20px;
        font-size: 2rem;
        color: rgba(46, 125, 50, 0.1);
    }

    .recent-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .recent-section h2 {
        color: #2e7d32;
        margin-bottom: 15px;
        font-size: 1.2rem;
    }
</style>

<!-- Dashboard Content -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><?php echo $total_farmers; ?></h3>
        <p>Total Farmers</p>
        <i class="fas fa-users"></i>
    </div>
    <div class="stat-card">
        <h3><?php echo $total_land; ?></h3>
        <p>Total Land Area (hectares)</p>
        <i class="fas fa-chart-area"></i>
    </div>
    <div class="stat-card">
        <h3><?php echo $total_varieties; ?></h3>
        <p>Cardamom Varieties</p>
        <i class="fas fa-seedling"></i>
    </div>
    <div class="stat-card">
        <h3><?php echo $total_employees; ?></h3>
        <p>Total Employees</p>
        <i class="fas fa-user-tie"></i>
    </div>
    <div class="stat-card">
        <h3><?php echo $total_soil_tests; ?></h3>
        <p>Total Soil Tests</p>
        <i class="fas fa-flask"></i>
    </div>
</div>

<div class="recent-section">
    <h2>Recent Soil Tests</h2>
    <p>No recent soil tests found.</p>
</div>

<div class="recent-section">
    <h2>Recent Fertilizer Recommendations</h2>
    <p>No recent fertilizer recommendations found.</p>
</div>

<div class="recent-section">
    <h2>Recent Farmers</h2>
    <p>No recent farmers found.</p>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
include 'layout.php';
?>