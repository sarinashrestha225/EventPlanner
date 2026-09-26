<?php

require_once "../includes/auth.php";
require_once "../../database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int) $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_name = trim($_POST['user_name']);
    $comment = trim($_POST['comment']);
    $service_name = trim($_POST['service_name']);
    $status = $_POST['status'];

    $allowed_status = [
        'Pending',
        'Approved',
        'Hidden'
    ];

    if (!in_array($status, $allowed_status)) {
        $status = 'Pending';
    }

    $stmt = $conn->prepare("
        UPDATE comments
        SET
            user_name = ?,
            comment = ?,
            service_name = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssi",
        $user_name,
        $comment,
        $service_name,
        $status,
        $id
    );

    if ($stmt->execute()) {

        $stmt->close();

        header("Location: view.php?id=" . $id);
        exit;

    } else {

        $error = "Failed to update comment.";

    }

    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT *
    FROM comments
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Comment not found.");
}

$commentData = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Edit Comment | Event Planner</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fffaf2;
}

.main {
    margin-left: 250px;
    padding: 30px;
}

.card {
    max-width: 800px;
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

h1 {
    color: #b8860b;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input,
textarea,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

textarea {
    min-height: 150px;
    resize: vertical;
}

.btn {
    border: none;
    padding: 11px 20px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.save {
    background: #d4af37;
    color: white;
}

.cancel {
    background: #f4c2c2;
    color: #6b3d3d;
    text-decoration: none;
    padding: 11px 20px;
    border-radius: 8px;
    display: inline-block;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 8px;
}

</style>

</head>

<body>

<?php require_once "../includes/sidebar.php"; ?>

<div class="main">

<h1>✏ Edit Comment</h1>

<br>

<div class="card">

<?php if (isset($error)): ?>

    <div class="error">
        <?php echo htmlspecialchars($error); ?>
    </div>

<?php endif; ?>

<form method="POST">

<div class="form-group">

<label>👤 User Name</label>

<input
    type="text"
    name="user_name"
    value="<?php echo htmlspecialchars($commentData['user_name']); ?>"
    required
>

</div>

<div class="form-group">

<label>📝 Comment</label>

<textarea
    name="comment"
    required
><?php echo htmlspecialchars($commentData['comment']); ?></textarea>

</div>

<div class="form-group">

<label>📌 Service Name</label>

<input
    type="text"
    name="service_name"
    value="<?php echo htmlspecialchars($commentData['service_name'] ?? ''); ?>"
>

</div>

<div class="form-group">

<label>📊 Status</label>

<select name="status">

<option
    value="Pending"
    <?php echo $commentData['status'] === 'Pending' ? 'selected' : ''; ?>
>
Pending
</option>

<option
    value="Approved"
    <?php echo $commentData['status'] === 'Approved' ? 'selected' : ''; ?>
>
Approved
</option>

<option
    value="Hidden"
    <?php echo $commentData['status'] === 'Hidden' ? 'selected' : ''; ?>
>
Hidden
</option>

</select>

</div>

<button
    type="submit"
    class="btn save"
>
💾 Save Changes
</button>

<a
    href="view.php?id=<?php echo $id; ?>"
    class="cancel"
>
Cancel
</a>

</form>

</div>

</div>

</body>

</html>

<?php $conn->close(); ?>