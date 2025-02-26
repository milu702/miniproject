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
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer';
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
        /* Base styles from settings.php */
        :root {
            --primary-color: #2c5282;
            --secondary-color: #4299e1;
            --accent-color: #90cdf4;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: #f7fafc;
        }

        /* Include all sidebar and layout styles from settings.php */
        .sidebar {
            background: linear-gradient(180deg, #2c5282, #4299e1);
            width: 80px;  /* Reduced width */
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-y: auto;
            transition: width 0.3s ease;
        }

        .sidebar:hover {
            width: 200px;
        }

        .sidebar-header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .sidebar-header h2 {
            font-size: 1.2rem;
            margin: 0;
            display: none;
        }

        .sidebar:hover .sidebar-header h2 {
            display: block;
        }

        .nav-menu {
            width: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            width: 100%;
            box-sizing: border-box;
        }

        .nav-item i {
            font-size: 1.5rem;
            min-width: 40px;
            text-align: center;
        }

        .nav-item span {
            display: none;
            margin-left: 10px;
            white-space: nowrap;
        }

        .sidebar:hover .nav-item span {
            display: inline;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Update main content margin */
        .main-content {
            flex: 1;
            margin-left: 80px;  /* Match sidebar width */
            padding: 20px;
        }

        /* Remove farmer profile styles as it's no longer needed */
        .farmer-profile {
            display: none;
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

        /* Include all sidebar and nav styles from settings.php */

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
                <h2><i class="fas fa-seedling"></i> <span>GrowGuide</span></h2>
            </div>
            <nav class="nav-menu">
                <a href="farmer.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
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