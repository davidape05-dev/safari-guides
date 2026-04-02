<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'tourist') {
    header("Location: login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Check if booking is completed and belongs to this tourist
$query = "
    SELECT b.*, u.first_name, u.last_name 
    FROM bookings b
    INNER JOIN users u ON b.guide_id = u.id
    WHERE b.id = ? AND b.tourist_id = ? AND b.status = 'completed'
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: tourist_dashboard.php");
    exit();
}

// Check if review already exists
$check = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
$check->bind_param("i", $booking_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $_SESSION['error'] = "You have already reviewed this tour";
    header("Location: my_bookings.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = $_POST['comment'];

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a valid rating";
    } else {
        $insert = $conn->prepare("
            INSERT INTO reviews (booking_id, tourist_id, guide_id, rating, comment, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $insert->bind_param("iiiis", $booking_id, $_SESSION['user_id'], $booking['guide_id'], $rating, $comment);

        if ($insert->execute()) {
            if ($insert->execute()) {
                // Update guide rating using stored procedure - FIXED NAME
                $conn->query("CALL update_guide_rating({$booking['guide_id']})");

                // Create notification for guide
                $notify = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link) 
        VALUES (?, 'review', 'New Review Received', 
                CONCAT('You received a ', ?, '-star review from ', ?), 
                'guide_dashboard.php?section=reviews')
    ");
                $message_rating = $rating;
                $message_name = $booking['tourist_name'];
                $notify->bind_param("iis", $booking['guide_id'], $message_rating, $message_name);
                $notify->execute();

                $_SESSION['success'] = "Thank you for your review!";
                header("Location: tourist_dashboard.php");
                exit();
            }
        } else {
            $error = "Failed to submit review";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Your Tour</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #8b4513 0%, #d2691e 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .review-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 2rem;
        }

        h1 {
            color: #228B22;
            margin-bottom: 0.5rem;
        }

        .guide-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin: 1.5rem 0;
        }

        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            font-size: 2rem;
            cursor: pointer;
        }

        .star {
            color: #ddd;
            transition: color 0.3s;
        }

        .star.active {
            color: #FFD700;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            min-height: 150px;
        }

        .btn-submit {
            background: #228B22;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .rating-hint {
            color: #666;
            font-size: 0.9rem;
            margin: 0.5rem 0 1.5rem;
        }
    </style>
</head>

<body>
    <div class="review-container">
        <h1>Rate Your Tour Experience</h1>
        <p>Share your feedback with <?php echo htmlspecialchars($booking['first_name']); ?></p>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="guide-info">
            <p><strong>Guide:</strong>
                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></p>
            <p><strong>Tour Dates:</strong> <?php echo date('M j, Y', strtotime($booking['start_date'])); ?> -
                <?php echo date('M j, Y', strtotime($booking['end_date'])); ?>
            </p>
        </div>

        <form method="POST" id="reviewForm">
            <div class="form-group">
                <label>Your Rating</label>
                <div class="rating-stars" id="stars">
                    <span class="star" data-rating="1">★</span>
                    <span class="star" data-rating="2">★</span>
                    <span class="star" data-rating="3">★</span>
                    <span class="star" data-rating="4">★</span>
                    <span class="star" data-rating="5">★</span>
                </div>
                <input type="hidden" name="rating" id="rating" required>
                <div class="rating-hint" id="ratingText">Click a star to rate</div>
            </div>

            <div class="form-group">
                <label>Your Review</label>
                <textarea name="comment" placeholder="Tell us about your experience with this guide..."
                    required></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Review</button>
        </form>
    </div>

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('rating');
        const ratingText = document.getElementById('ratingText');

        stars.forEach(star => {
            star.addEventListener('click', function () {
                const rating = this.dataset.rating;
                ratingInput.value = rating;

                stars.forEach(s => s.classList.remove('active'));
                for (let i = 0; i < rating; i++) {
                    stars[i].classList.add('active');
                }

                const messages = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                ratingText.textContent = messages[rating - 1];
            });
        });
    </script>
</body>

</html>