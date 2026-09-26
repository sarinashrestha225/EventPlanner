<?php

require_once "../includes/auth.php";
require_once "../../database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("
    SELECT
        id,
        user_id,
        user_name,
        comment,
        service_id,
        service_name,
        review_id,
        status,
        created_at
    FROM comments
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Comment not found.");
}

$comment = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>View Comment | Event Planner</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #fffaf2;
    color: #333;
}

.main {
    margin-left: 250px;
    padding: 30px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    color: #b8860b;
}

.back-btn {
    text-decoration: none;
    background: #f4c2c2;
    color: #6b3d3d;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: bold;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    max-width: 900px;
}

.detail {
    margin-bottom: 20px;
}

.detail label {
    display: block;
    color: #b8860b;
    font-weight: bold;
    margin-bottom: 7px;
}

.detail p {
    background: #fffaf2;
    padding: 12px;
    border-radius: 8px;
}

.comment-box {
    background: #fff5f5;
    padding: 20px;
    border-radius: 10px;
    line-height: 1.6;
}

.status {
    display: inline-block;
    padding: 7px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.pending {
    background: #fff3cd;
    color: #856404;
}

.approved {
    background: #d4edda;
    color: #155724;
}

.hidden {
    background: #e2e3e5;
    color: #383d41;
}

.actions {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 7px;
    font-weight: bold;
}

.edit {
    background: #fff3cd;
    color: #856404;
}

.approve {
    background: #d4edda;
    color: #155724;
}

.hide {
    background: #e2e3e5;
    color: #383d41;
}

.delete {
    background: #f8d7da;
    color: #721c24;
}

</style>

</head>

<body>

<?php require_once "../includes/sidebar.php"; ?>

<div class="main">

    <div class="page-header">

        <h1>💬 View Comment</h1>

        <a href="index.php" class="back-btn">
            ← Back
        </a>

    </div>

    <div class="card">

        <div class="detail">

            <label>👤 User Name</label>

            <p>
                <?php echo htmlspecialchars($comment['user_name']); ?>
            </p>

        </div>

        <div class="detail">

            <label>📝 Comment</label>

            <div class="comment-box">

                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>

            </div>

        </div>

        <div class="detail">

            <label>📅 Date</label>

            <p>
                <?php echo date("d M Y, h:i A", strtotime($comment['created_at'])); ?>
            </p>

        </div>

        <div class="detail">

            <label>📌 Related Service</label>

            <p>

                <?php

                echo !empty($comment['service_name'])
                    ? htmlspecialchars($comment['service_name'])
                    : "Not related to any service";

                ?>

            </p>

        </div>

        <div class="detail">

            <label>⭐ Related Review</label>

            <p>

                <?php

                echo !empty($comment['review_id'])
                    ? "Review #" . htmlspecialchars($comment['review_id'])
                    : "No related review";

                ?>

            </p>

        </div>

        <div class="detail">

            <label>📊 Status</label>

            <p>

                <span class="status <?php echo strtolower($comment['status']); ?>">

                    <?php echo htmlspecialchars($comment['status']); ?>

                </span>

            </p>

        </div>

        <div class="actions">

            <a
                href="edit.php?id=<?php echo $comment['id']; ?>"
                class="btn edit"
            >
                ✏ Edit
            </a>

            <?php if ($comment['status'] !== 'Approved'): ?>

                <a
                    href="approve.php?id=<?php echo $comment['id']; ?>"
                    class="btn approve"
                    onclick="return confirm('Approve this comment?');"
                >
                    ✅ Approve
                </a>

            <?php endif; ?>

            <?php if ($comment['status'] !== 'Hidden'): ?>

                <a
                    href="hide.php?id=<?php echo $comment['id']; ?>"
                    class="btn hide"
                    onclick="return confirm('Hide this comment?');"
                >
                    🚫 Hide
                </a>

            <?php endif; ?>

            <a
                href="delete.php?id=<?php echo $comment['id']; ?>"
                class="btn delete"
                onclick="return confirm('Are you sure you want to delete this comment?');"
            >
                🗑 Delete
            </a>

        </div>

    </div>

</div>

</body>

</html>

<?php $conn->close(); ?>