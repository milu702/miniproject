<?php
$pageTitle = 'Cardamom Varieties Management';
require_once 'layout.php';
?>

<div class="section-header">
    <h2>Cardamom Varieties Management</h2>
    <button class="btn btn-primary" onclick="openVarietyModal()">
        <i class="fas fa-plus"></i> Add Variety
    </button>
</div>

<div class="data-table">
    <table id="varieties-table">
        <thead>
            <tr>
                <th>Variety Name</th>
                <th>Scientific Name</th>
                <th>Growing Period</th>
                <th>Yield Rate</th>
                <th>Disease Resistance</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Data will be loaded dynamically -->
        </tbody>
    </table>
</div>

<!-- Variety Modal -->
<div id="varietyModal" class="modal">
    <!-- ... modal content ... -->
</div>

<script src="js/varieties.js"></script>
<?php require_once 'footer.php'; ?> 