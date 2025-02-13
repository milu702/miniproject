<?php
$pageTitle = 'Soil Tests Management';
require_once 'layout.php';
?>

<div class="section-header">
    <h2>Soil Tests Management</h2>
    <button class="btn btn-primary" onclick="openSoilTestModal()">
        <i class="fas fa-plus"></i> Add Soil Test
    </button>
</div>

<div class="data-table">
    <table id="soil-tests-table">
        <thead>
            <tr>
                <th>Farmer</th>
                <th>Test Date</th>
                <th>pH Level</th>
                <th>Nitrogen (N)</th>
                <th>Phosphorus (P)</th>
                <th>Potassium (K)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Data will be loaded dynamically -->
        </tbody>
    </table>
</div>

<!-- Soil Test Modal -->
<div id="soilTestModal" class="modal">
    <!-- ... modal content ... -->
</div>

<script src="js/soil-tests.js"></script>
<?php require_once 'footer.php'; ?> 