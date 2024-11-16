<?php
// Start session and error reporting
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_host = 'localhost';
$db_name = 'photo_storage';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name");
    $pdo->exec("USE $db_name");
    
    // Create tables with additional fields for photo management
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) UNIQUE NOT NULL,
            email VARCHAR(120) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            share_link VARCHAR(32) UNIQUE NOT NULL,
            file_size INT NOT NULL,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
                $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $email, $password]);
                    echo "<script>alert('Registration successful! Please login.');</script>";
                } catch(PDOException $e) {
                    echo "<script>alert('Registration failed. Username or email may already exist.');</script>";
                }
                break;
                
            case 'login':
                $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
                $password = $_POST['password'];
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    echo "<script>alert('Invalid credentials');</script>";
                }
                break;
                
            case 'upload':
                if (isset($_SESSION['user_id']) && isset($_FILES['photo'])) {
                    $files = $_FILES['photo'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $maxFileSize = 5 * 1024 * 1024; // 5MB
                    
                    // Handle multiple file upload
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if (!in_array($files['type'][$i], $allowedTypes)) {
                            echo "<script>alert('Invalid file type: " . htmlspecialchars($files['name'][$i]) . "');</script>";
                            continue;
                        }
                        
                        if ($files['size'][$i] > $maxFileSize) {
                            echo "<script>alert('File too large: " . htmlspecialchars($files['name'][$i]) . "');</script>";
                            continue;
                        }
                        
                        if (!file_exists('uploads')) {
                            mkdir('uploads', 0777, true);
                        }
                        
                        $filename = uniqid() . '_' . basename($files['name'][$i]);
                        $uploadPath = 'uploads/' . $filename;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                            $shareLink = bin2hex(random_bytes(16));
                            $stmt = $pdo->prepare("INSERT INTO photos (user_id, filename, original_filename, share_link, file_size) VALUES (?, ?, ?, ?, ?)");
                            if ($stmt->execute([$_SESSION['user_id'], $filename, $files['name'][$i], $shareLink, $files['size'][$i]])) {
                                echo "<script>alert('Upload successful!');</script>";
                            }
                        }
                    }
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                break;

            case 'delete':
                if (isset($_POST['photo_ids']) && isset($_SESSION['user_id'])) {
                    $photoIds = $_POST['photo_ids'];
                    foreach ($photoIds as $photoId) {
                        // Verify ownership and get filename
                        $stmt = $pdo->prepare("SELECT filename FROM photos WHERE id = ? AND user_id = ?");
                        $stmt->execute([$photoId, $_SESSION['user_id']]);
                        $photo = $stmt->fetch();
                        
                        if ($photo) {
                            $filepath = 'uploads/' . $photo['filename'];
                            if (file_exists($filepath)) {
                                unlink($filepath);
                            }
                            
                            $stmt = $pdo->prepare("DELETE FROM photos WHERE id = ? AND user_id = ?");
                            $stmt->execute([$photoId, $_SESSION['user_id']]);
                        }
                    }
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                break;
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Storage App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --secondary-color: #818cf8;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --background-color: #f8fafc;
            --card-color: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Dashboard Header */
        .dashboard-header {
            background-color: var(--card-color);
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }

        .dashboard-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Enhanced Forms */
        .auth-forms {
            max-width: 460px;
            margin: 2rem auto;
            background: var(--card-color);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        /* Upload Area */
        .upload-section {
            background: var(--card-color);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(79, 70, 229, 0.05);
        }

        /* Photo Grid */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .photo-card {
            background: var(--card-color);
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .photo-card:hover {
            transform: translateY(-2px);
        }

        .photo-checkbox {
            position: absolute;
            top: 1.5rem;
            left: 1rem;
            z-index: 10;
        }

        .photo-img {
            position: relative;
            padding-top: 100%;
        }

        .photo-img img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-info {
            padding: 1rem;
        }

        .photo-actions {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            background-color: #f8fafc;
            border-top: 1px solid var(--border-color);
        }

        /* Bulk Actions */
        .bulk-actions {
            display: none;
            padding: 1rem;
            background: var(--card-color);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .bulk-actions.visible {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .dashboard-nav {
                flex-direction: column;
                gap: 1rem;
            }

            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
        }
        /* Base Styles */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

/* Container for responsive layout */
.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Responsive grid */
.row {
    display: flex;
    flex-wrap: wrap;
}

.column {
    flex: 1;
    padding: 10px;
}

/* Responsive images */
img {
    max-width: 100%;
    height: auto;
}

/* Default styles */
.header {
    background: #333;
    color: white;
    padding: 10px 0;
    text-align: center;
}

.main-content {
    background: #f4f4f4;
    padding: 20px;
}

.footer {
    background: #333;
    color: white;
    text-align: center;
    padding: 10px 0;
}

/* Media Queries for Responsiveness */

/* For tablets and larger screens */
@media (min-width: 600px) {
    .column {
        flex: 0 0 50%;
    }
}

/* For laptops and desktops */
@media (min-width: 992px) {
    .column {
        flex: 0 0 33.33%;
    }
}

/* For extra-large screens */
@media (min-width: 1200px) {
    .column {
        flex: 0 0 25%;
    }
}

    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login Form -->
            <div class="auth-forms" id="login-form">
                <h2 class="text-2xl font-bold text-center mb-6">Login to Photo Storage</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                <div class="text-center mt-4">
                    <a href="#" onclick="toggleForms(); return false;" class="text-primary-color">Need an account? Register</a>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="auth-forms" id="register-form" style="display: none;">
               <h2 class="text-2xl font-bold text-center mb-6">Create an Account</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>
                <div class="text-center mt-4">
                    <a href="#" onclick="toggleForms(); return false;" class="text-primary-color">Already have an account? Login</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Dashboard -->
            <div class="dashboard-header">
                <div class="dashboard-nav">
                    <h1 class="dashboard-title">Photo Storage Dashboard</h1>
                    <div class="user-nav">
                        <span class="user-welcome">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                        <a href="?logout" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div id="bulk-actions" class="bulk-actions">
                <div class="selected-count">
                    <span id="selected-count">0</span> items selected
                </div>
                <button onclick="deleteSelected()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>

            <!-- Upload Section -->
            <div class="upload-section">
                <h2 class="text-xl font-bold mb-4">Upload Photos</h2>
                <form method="POST" enctype="multipart/form-data" id="upload-form">
                    <input type="hidden" name="action" value="upload">
                    <div class="upload-area" onclick="document.getElementById('photo-input').click()">
                        <i class="fas fa-cloud-upload-alt text-4xl mb-2"></i>
                        <p>Click to upload or drag photos here</p>
                        <input type="file" id="photo-input" name="photo[]" accept="image/*" multiple style="display: none" onchange="handleFileSelect(this)">
                    </div>
                    <div id="upload-preview" class="mt-4"></div>
                </form>
            </div>

            <!-- Photo Grid -->
            <form id="delete-form" method="POST">
                <input type="hidden" name="action" value="delete">
                <div class="photo-grid">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM photos WHERE user_id = ? ORDER BY upload_date DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    while ($photo = $stmt->fetch()):
                        $filesize = number_format($photo['file_size'] / 1024, 2) . ' KB';
                    ?>
                    <div class="photo-card">
                        <div class="photo-img">
                            <input type="checkbox" name="photo_ids[]" value="<?php echo $photo['id']; ?>" 
                                   class="photo-checkbox" onchange="updateBulkActions()">
                            <img src="uploads/<?php echo htmlspecialchars($photo['filename']); ?>" 
                                 alt="<?php echo htmlspecialchars($photo['original_filename']); ?>">
                        </div>
                        <div class="photo-info">
                            <p class="truncate"><?php echo htmlspecialchars($photo['original_filename']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo $filesize; ?></p>
                            <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($photo['upload_date'])); ?></p>
                        </div>
                        <div class="photo-actions">
                            <a href="uploads/<?php echo htmlspecialchars($photo['filename']); ?>" 
                               target="_blank" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="copyShareLink('<?php echo htmlspecialchars($photo['share_link']); ?>')">
                                <i class="fas fa-share"></i> Share
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function toggleForms() {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            loginForm.style.display = loginForm.style.display === 'none' ? 'block' : 'none';
            registerForm.style.display = registerForm.style.display === 'none' ? 'block' : 'none';
        }

        function copyShareLink(shareLink) {
            const shareUrl = `${window.location.origin}/share.php?link=${shareLink}`;
            navigator.clipboard.writeText(shareUrl)
                .then(() => alert('Share link copied to clipboard!'))
                .catch(() => alert('Failed to copy share link'));
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.photo-checkbox:checked');
            const bulkActions = document.getElementById('bulk-actions');
            const selectedCount = document.getElementById('selected-count');
            
            bulkActions.classList.toggle('visible', checkboxes.length > 0);
            selectedCount.textContent = checkboxes.length;
        }

        function deleteSelected() {
            if (confirm('Are you sure you want to delete the selected photos?')) {
                document.getElementById('delete-form').submit();
            }
        }

        function handleFileSelect(input) {
            const preview = document.getElementById('upload-preview');
            preview.innerHTML = '';
            
            if (input.files.length > 0) {
                const fileList = document.createElement('div');
                fileList.className = 'mt-4 space-y-2';
                
                Array.from(input.files).forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'flex items-center justify-between p-2 bg-gray-50 rounded';
                    fileItem.innerHTML = `
                        <span>${file.name}</span>
                        <span>${(file.size / 1024).toFixed(2)} KB</span>
                    `;
                    fileList.appendChild(fileItem);
                });
                
                preview.appendChild(fileList);
                const uploadButton = document.createElement('button');
                uploadButton.type = 'submit';
                uploadButton.className = 'btn btn-primary mt-4';
                uploadButton.innerHTML = '<i class="fas fa-upload"></i> Upload Selected Files';
                preview.appendChild(uploadButton);
            }
        }

        // Drag and drop handling
        const uploadArea = document.querySelector('.upload-area');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.classList.add('border-primary-color');
        }

        function unhighlight(e) {
            uploadArea.classList.remove('border-primary-color');
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('photo-input').files = files;
            handleFileSelect(document.getElementById('photo-input'));
        }
    </script>
</body>
</html>