<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Add PDO connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=growguide", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user data
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT username, farm_location FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $username = htmlspecialchars($user['username'] ?? 'Farmer');
    $farm_location = htmlspecialchars($user['farm_location'] ?? 'Idukki');
} catch(PDOException $e) {
    $username = 'Farmer';
    $farm_location = 'Idukki';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.css">
    <style>
        /* Updated Sidebar Styles */
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1A3A19;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
            --sidebar-width: 250px;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: #f7fafc;
        }

        /* Include all sidebar and layout styles from settings.php */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            position: fixed;
            height: 100vh;
            padding: 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: width 0.3s ease;
        }

        .sidebar:hover {
            width: 200px;
        }

        /* Logo Header */
        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Farmer Profile Section */
        .farmer-profile {
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin: 0 15px 20px 15px;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .farmer-avatar i {
            font-size: 32px;
            color: rgba(255, 255, 255, 0.8);
        }

        .farmer-profile h3 {
            color: white;
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 500;
        }

        .farmer-profile p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            font-size: 14px;
        }

        .farmer-location {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 10px;
            font-size: 14px;
        }

        .farmer-location i {
            color: #4CAF50;
            font-size: 12px;
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 10px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 4px 15px 4px 0;
            border-radius: 0 25px 25px 0;
        }

        .nav-item i {
            width: 24px;
            font-size: 18px;
            margin-right: 12px;
            transition: all 0.3s ease;
        }

        .nav-item span {
            font-size: 15px;
        }

        /* Active State */
        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Hover Effects */
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(8px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-item:hover i {
            transform: rotate(10deg) scale(1.2);
            color: var(--accent-color);
        }

        /* Custom Icons Colors */
        .nav-item[href="farmer.php"] i { color: #4CAF50; }
        .nav-item[href="soil_test.php"] i { color: #2196F3; }
        .nav-item[href="fertilizerrrr.php"] i { color: #8BC34A; }
        .nav-item[href="farm_analysis.php"] i { color: #FF9800; }
        .nav-item[href="schedule.php"] i { color: #9C27B0; }
        .nav-item[href="weather.php"] i { color: #03A9F4; }
        .nav-item[href="settings.php"] i { color: #607D8B; }

        /* Active item overrides icon color */
        .nav-item.active i {
            color: white !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .farmer-profile {
                padding: 10px;
                margin: 0 5px 10px 5px;
            }

            .farmer-avatar {
                width: 40px;
                height: 40px;
                margin-bottom: 10px;
            }

            .farmer-avatar i {
                font-size: 20px;
            }

            .farmer-profile h3,
            .farmer-profile p,
            .farmer-location {
                display: none;
            }

            .nav-item {
                padding: 15px;
                justify-content: center;
            }

            .nav-item i {
                margin: 0;
                font-size: 20px;
            }

            .nav-item:hover {
                padding-left: 15px;
            }

            .main-content {
                margin-left: 60px;
            }
        }

        /* Animation for icon bounce */
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .nav-item:hover i {
            animation: iconBounce 0.5s ease infinite;
        }

        /* Update main content margin */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        /* Include all other styles from settings.php */
        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        /* Calendar specific styles */
        .calendar-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .fc {
            max-width: 100%;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .fc-toolbar-title {
            color: var(--primary-color);
            font-size: 1.5rem !important;
        }

        .fc-button-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .fc-button-primary:hover {
            background-color: var(--secondary-color) !important;
            border-color: var(--secondary-color) !important;
        }

        /* Task list styles */
        .task-list {
            margin: 20px 0;
        }

        .task-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .task-item:hover {
            transform: translateX(5px);
        }

        .task-checkbox {
            margin-right: 15px;
        }

        .task-content {
            flex: 1;
        }

        .task-title {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .task-date {
            font-size: 0.9rem;
            color: #666;
        }

        .task-actions {
            display: flex;
            gap: 10px;
        }

        .task-button {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }

        .task-button:hover {
            color: var(--primary-color);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            color: var(--primary-color);
            margin: 0;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        /* Add these priority-based styles */
        @keyframes importantPulse {
            0% { transform: translateX(0); }
            25% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }

        .priority-high {
            border-left: 4px solid #dc3545 !important;
            background-color: rgba(220, 53, 69, 0.1) !important;
            animation: importantPulse 2s infinite;
        }

        .priority-medium {
            border-left: 4px solid #28a745 !important;
            background-color: rgba(40, 167, 69, 0.1) !important;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-seedling"></i>
                <h2>GrowGuide</h2>
            </div>
            
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($username); ?></h3>
                <p>Cardamom Farmer</p>
                <div class="farmer-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($farm_location); ?></span>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="farmer.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="soil_test.php" class="nav-item">
                    <i class="fas fa-flask"></i>
                    <span>Soil Test</span>
                </a>
                <a href="fertilizerrrr.php" class="nav-item">
                    <i class="fas fa-leaf"></i>
                    <span>Fertilizer Guide</span>
                </a>
                <a href="farm_analysis.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Farm Analysis</span>
                </a>
                <a href="schedule.php" class="nav-item active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                </a>
                <a href="weather.php" class="nav-item">
                    <i class="fas fa-cloud-sun"></i>
                    <span>Weather</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-calendar"></i> Farming Calendar</h2>
                </div>
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>

            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-tasks"></i> Upcoming Tasks</h2>
                </div>
                <div class="task-list" id="taskList">
                    <?php
                    // Add this PHP code to fetch and display tasks
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY task_date ASC");
                        $stmt->execute([$user_id]);
                        $tasks = $stmt->fetchAll();

                        foreach ($tasks as $task) {
                            // Add priority-based classes
                            $priorityClass = '';
                            switch($task['priority']) {
                                case 'high':
                                    $priorityClass = 'priority-high';
                                    break;
                                case 'medium':
                                    $priorityClass = 'priority-medium';
                                    break;
                            }
                            
                            echo '<div class="task-item ' . $priorityClass . '" data-task-id="' . $task['id'] . '">
                                <input type="checkbox" class="task-checkbox">
                                <div class="task-content">
                                    <div class="task-title">' . htmlspecialchars($task['title']) . '</div>
                                    <div class="task-date">' . date('M d, Y H:i', strtotime($task['task_date'])) . '</div>
                                    <div class="task-description">' . htmlspecialchars($task['description']) . '</div>
                                </div>
                                <div class="task-actions">
                                    <button class="task-button" onclick="deleteTask(' . $task['id'] . ')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>';
                        }
                    } catch (PDOException $e) {
                       // echo '<p>Error loading tasks: ' . $e->getMessage() . '</p>';
                    }
                    ?>
                </div>
                <button class="submit-btn" onclick="showAddTaskModal()">
                    <i class="fas fa-plus"></i> Add New Task
                </button>
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div id="addTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Task</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <form id="addTaskForm" onsubmit="handleAddTask(event)">
                <div class="form-group">
                    <label for="taskTitle">Task Title</label>
                    <input type="text" id="taskTitle" name="taskTitle" required>
                </div>
                <div class="form-group">
                    <label for="taskDate">Date</label>
                    <input type="datetime-local" id="taskDate" name="taskDate" required>
                </div>
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="taskDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="taskPriority">Priority</label>
                    <select id="taskPriority" name="taskPriority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Add Task
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.js"></script>
    <script>
        let calendar; // Declare calendar variable in global scope

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize FullCalendar
            var calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {  // Remove 'var' to use global calendar
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: 'get_tasks.php',
                eventClick: function(info) {
                    alert('Task: ' + info.event.title + '\nDescription: ' + info.event.extendedProps.description);
                },
                dateClick: function(info) {
                    // Add date validation when clicking on calendar
                    const selectedDate = new Date(info.dateStr);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (selectedDate < today) {
                        alert('Cannot create tasks for past dates. Please select today or a future date.');
                        return;
                    }
                    
                    showAddTaskModal();
                    // Pre-fill the date input with the selected date
                    document.getElementById('taskDate').value = info.dateStr + 'T00:00';
                }
            });
            calendar.render();

            // Add min attribute to taskDate input to prevent selecting past dates
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            document.getElementById('taskDate').setAttribute('min', todayStr + 'T00:00');
        });

        // Modal functions
        function showAddTaskModal() {
            document.getElementById('addTaskModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('addTaskModal').style.display = 'none';
        }

        function handleAddTask(event) {
            event.preventDefault();
            
            // Add date validation before submission
            const selectedDate = new Date(document.getElementById('taskDate').value);
            const now = new Date();

            if (selectedDate < now) {
                alert('Cannot create tasks for past dates. Please select a current or future date and time.');
                return;
            }

            const formData = new FormData();
            formData.append('title', document.getElementById('taskTitle').value);
            formData.append('date', document.getElementById('taskDate').value);
            formData.append('description', document.getElementById('taskDescription').value);
            formData.append('priority', document.getElementById('taskPriority').value);

            fetch('add_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add event to calendar
                    calendar.addEvent({
                        title: document.getElementById('taskTitle').value,
                        start: document.getElementById('taskDate').value,
                        description: document.getElementById('taskDescription').value
                    });

                    // Add priority-based class
                    const priority = document.getElementById('taskPriority').value;
                    const priorityClass = priority === 'high' ? 'priority-high' : 
                                        priority === 'medium' ? 'priority-medium' : '';

                    // Add task to task list
                    const taskList = document.getElementById('taskList');
                    const taskItem = document.createElement('div');
                    taskItem.className = `task-item ${priorityClass}`;
                    taskItem.dataset.taskId = data.task_id;
                    taskItem.innerHTML = `
                        <input type="checkbox" class="task-checkbox">
                        <div class="task-content">
                            <div class="task-title">${document.getElementById('taskTitle').value}</div>
                            <div class="task-date">${new Date(document.getElementById('taskDate').value).toLocaleString()}</div>
                            <div class="task-description">${document.getElementById('taskDescription').value}</div>
                        </div>
                        <div class="task-actions">
                            <button class="task-button" onclick="deleteTask(${data.task_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    taskList.prepend(taskItem);

                    // Reset form and close modal
                    document.getElementById('addTaskForm').reset();
                    closeModal();
                }
                // Commented out error message
                // else {
                //     alert('Error adding task: ' + data.message);
                // }
            })
            .catch(error => {
                console.error('Error:', error);
                // Commented out error alert
                // alert('Error adding task');
            });
        }

        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                fetch('delete_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'task_id=' + taskId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove task from list
                        const taskElement = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
                        if (taskElement) {
                            taskElement.remove();
                        }
                        
                        // Refresh the calendar to show updated events
                        calendar.refetchEvents();
                    }
                    // Commented out error message
                    // else {
                    //     alert('Error deleting task: ' + data.message);
                    // }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Commented out error alert
                    // alert('Error deleting task');
                });
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('addTaskModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>