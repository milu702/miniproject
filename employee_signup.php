<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['temp_email'])) {
    header("Location: signup.php");
    exit();
}

$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

$error = '';
$success = '';

// Ensure the uploads directory exists
$uploadDir = "uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $qualification = $_POST['qualification'];
    $email = $_SESSION['temp_email'];

    // Get user ID
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("User not found.");
    }
    $user = $result->fetch_assoc();
    $user_id = $user['id'];

    // File upload handling
    $profilePath = '';
    $certificatePath = '';

    // Upload Profile Picture
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $profileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $profileExt = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $profileName = $user_id . "_profile." . $profileExt;
        $profilePath = $uploadDir . $profileName;

        $allowedProfileTypes = ['image/jpeg', 'image/png'];
        if (!in_array($_FILES['profile_picture']['type'], $allowedProfileTypes)) {
            $error = "Profile picture must be in JPG or PNG format.";
        } elseif (!move_uploaded_file($profileTmpPath, $profilePath)) {
            $error = "Profile picture upload failed.";
        }
    } else {
        $error = "Please upload a profile picture.";
    }

    // Upload Certificate
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === 0) {
        $certTmpPath = $_FILES['certificate']['tmp_name'];
        $certificateName = $user_id . "_certificate.pdf";
        $certificatePath = $uploadDir . $certificateName;

        if ($_FILES['certificate']['type'] !== "application/pdf") {
            $error = "Certificate must be in PDF format.";
        } elseif (!move_uploaded_file($certTmpPath, $certificatePath)) {
            $error = "Certificate upload failed.";
        }
    } else {
        $error = "Please upload a valid certificate.";
    }

    // Insert into database if no errors
    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO employees (user_id, qualification, profile_picture, certificate) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            die("MySQL Error: " . $conn->error);
        }

        $stmt->bind_param("isss", $user_id, $qualification, $profilePath, $certificatePath);
        if ($stmt->execute()) {
            // Registration successful, redirect to login page
            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Employee Signup</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: url('log.jpeg') no-repeat center center/cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            width: 400px;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            text-align: center;
            color: white;
        }
        h2 { font-size: 24px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { font-weight: 600; }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            outline: none;
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        .btn-primary {
            width: 100%; padding: 10px; border: none;
            background: #ff9800; color: white;
            font-size: 16px; border-radius: 5px;
            cursor: pointer; transition: 0.3s;
        }
        .btn-primary:hover { background: #e68900; }
        .error-message, .success-message {
            padding: 10px; border-radius: 5px;
            margin-bottom: 15px;
        }
        .error-message { background: rgba(255, 0, 0, 0.2); color: white; }
        .success-message { background: rgba(0, 255, 0, 0.2); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Employee Registration</h2>
        <?php if (!empty($error)): ?>
            <div class="error-message"> <?php echo $error; ?> </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="qualification">Qualification</label>
                <select id="qualification" name="qualification" required>
                    <option value="M.Sc. Agronomy">M.Sc. Agronomy</option>
                    <option value="Soil Science">Soil Science</option>
                    <option value="Biochemistry">Biochemistry</option>
                    <option value="Chemistry">Chemistry</option>
                </select>
            </div>
            <div class="form-group">
                <label for="profile_picture">Upload Profile Picture (JPG/PNG)</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg, image/png" required>
            </div>
            <div class="form-group">
                <label for="certificate">Upload Certificate (PDF)</label>
                <input type="file" id="certificate" name="certificate" accept="application/pdf" required>
            </div>
            <button type="submit" class="btn-primary">Submit</button>
        </form>
    </div>
</body>
</html>
