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
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get share link from URL
$shareLink = isset($_GET['link']) ? $_GET['link'] : '';

if (empty($shareLink)) {
    die("No share link provided");
}

// Fetch photo information using the share link
$stmt = $pdo->prepare("SELECT p.*, u.username FROM photos p JOIN users u ON p.user_id = u.id WHERE p.share_link = ?");
$stmt->execute([$shareLink]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    die("Invalid share link or photo not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Photo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            width: 100%;
            box-sizing: border-box;
        }

        .photo-container {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .photo-header {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .photo-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .photo-meta {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .photo-wrapper {
            position: relative;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .photo-wrapper img {
            width: 100%;
            height: auto;
            border-radius: 0.25rem;
        }

        .download-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            margin-top: 1rem;
            transition: background-color 0.2s;
        }

        .download-button:hover {
            background-color: #4338ca;
        }

        @media (max-width: 640px) {
            .container {
                margin: 1rem auto;
            }

            .photo-container {
                padding: 1rem;
            }

            .photo-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="photo-container">
            <div class="photo-header">
                <h1 class="photo-title"><?php echo htmlspecialchars($photo['original_filename']); ?></h1>
                <div class="photo-meta">
                    <p>Shared by: <?php echo htmlspecialchars($photo['username']); ?></p>
                    <p>Upload date: <?php echo date('F j, Y', strtotime($photo['upload_date'])); ?></p>
                </div>
            </div>
            <div class="photo-wrapper">
                <img src="uploads/<?php echo htmlspecialchars($photo['filename']); ?>" 
                     alt="<?php echo htmlspecialchars($photo['original_filename']); ?>">
            </div>
            <a href="uploads/<?php echo htmlspecialchars($photo['filename']); ?>" 
               download="<?php echo htmlspecialchars($photo['original_filename']); ?>"
               class="download-button">
                Download Photo
            </a>
        </div>
    </div>
</body>
</html>