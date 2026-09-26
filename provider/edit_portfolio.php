
<?php

session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/../database.php";

if (
    !isset($_SESSION["provider_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "provider"
) {
    header("Location: login.php");
    exit();
}

$provider_id = (int) $_SESSION["provider_id"];

if ($provider_id <= 0) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["portfolio_edit_csrf_token"])) {
    $_SESSION["portfolio_edit_csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION["portfolio_edit_csrf_token"];

$portfolio_id =
    isset($_GET["id"])
        ? (int) $_GET["id"]
        : 0;

if ($portfolio_id <= 0) {
    $conn->close();
    header("Location: portfolio.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT
        id,
        provider_id,
        title,
        description,
        image,
        media_type,
        created_at
    FROM portfolio
    WHERE id = ?
    AND provider_id = ?
    LIMIT 1
");

if (!$stmt) {
    $error_message = $conn->error;
    $conn->close();

    die(
        "SQL Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$stmt->bind_param(
    "ii",
    $portfolio_id,
    $provider_id
);

$stmt->execute();

$result = $stmt->get_result();

$portfolio =
    $result
        ? $result->fetch_assoc()
        : null;

$stmt->close();

if (!$portfolio) {
    $conn->close();
    header("Location: portfolio.php");
    exit();
}

$title =
    (string) ($portfolio["title"] ?? "");

$description =
    (string) ($portfolio["description"] ?? "");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_token =
        $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $csrf_token,
            $posted_token
        )
    ) {

        $error =
            "Invalid request. Please try again.";

    } else {

        $title =
            trim($_POST["title"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        if ($title === "") {

            $error =
                "Please enter a title.";

        } elseif (mb_strlen($title) > 255) {

            $error =
                "Title must be 255 characters or less.";

        } elseif (mb_strlen($description) > 2000) {

            $error =
                "Description must be 2000 characters or less.";
        }

        $new_file_uploaded = false;

        $new_file_name =
            $portfolio["image"];

        $new_media_type =
            $portfolio["media_type"] ?? "image";

        $new_file_path = "";

        if (
            $error === "" &&
            isset($_FILES["media"]) &&
            $_FILES["media"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            $file = $_FILES["media"];

            if (
                $file["error"] !==
                UPLOAD_ERR_OK
            ) {

                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE =>
                        "The uploaded file is too large.",
                    UPLOAD_ERR_FORM_SIZE =>
                        "The uploaded file is too large.",
                    UPLOAD_ERR_PARTIAL =>
                        "The file upload was incomplete.",
                    UPLOAD_ERR_NO_TMP_DIR =>
                        "Temporary upload folder is missing.",
                    UPLOAD_ERR_CANT_WRITE =>
                        "Unable to save the uploaded file.",
                    UPLOAD_ERR_EXTENSION =>
                        "File upload was blocked by the server."
                ];

                $error =
                    $upload_errors[
                        $file["error"]
                    ]
                    ?? "File upload failed.";

            } else {

                $original_name =
                    (string) (
                        $file["name"] ?? ""
                    );

                $tmp_name =
                    (string) (
                        $file["tmp_name"] ?? ""
                    );

                $file_size =
                    (int) (
                        $file["size"] ?? 0
                    );

                $extension =
                    strtolower(
                        pathinfo(
                            $original_name,
                            PATHINFO_EXTENSION
                        )
                    );

                $image_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                $video_extensions = [
                    "mp4",
                    "webm",
                    "mov"
                ];

                $new_media_type = "";

                $max_size = 0;

                if (
                    in_array(
                        $extension,
                        $image_extensions,
                        true
                    )
                ) {

                    $new_media_type =
                        "image";

                    $max_size =
                        5 * 1024 * 1024;

                } elseif (
                    in_array(
                        $extension,
                        $video_extensions,
                        true
                    )
                ) {

                    $new_media_type =
                        "video";

                    $max_size =
                        50 * 1024 * 1024;

                } else {

                    $error =
                        "Only JPG, JPEG, PNG, WEBP, MP4, WEBM and MOV files are allowed.";
                }

                if (
                    $error === "" &&
                    $file_size <= 0
                ) {

                    $error =
                        "The uploaded file is empty.";

                } elseif (
                    $error === "" &&
                    $file_size > $max_size
                ) {

                    if (
                        $new_media_type ===
                        "image"
                    ) {

                        $error =
                            "Image size must be 5MB or less.";

                    } else {

                        $error =
                            "Video size must be 50MB or less.";
                    }
                }

                if (
                    $error === "" &&
                    !is_uploaded_file($tmp_name)
                ) {

                    $error =
                        "Invalid uploaded file.";
                }

                if ($error === "") {

                    $finfo =
                        finfo_open(
                            FILEINFO_MIME_TYPE
                        );

                    if ($finfo === false) {

                        $error =
                            "Unable to verify uploaded file.";

                    } else {

                        $mime_type =
                            finfo_file(
                                $finfo,
                                $tmp_name
                            );

                        finfo_close($finfo);

                        $allowed_image_mimes = [
                            "image/jpeg",
                            "image/png",
                            "image/webp"
                        ];

                        $allowed_video_mimes = [
                            "video/mp4",
                            "video/webm",
                            "video/quicktime"
                        ];

                        if (
                            $new_media_type ===
                            "image"
                        ) {

                            if (
                                !in_array(
                                    $mime_type,
                                    $allowed_image_mimes,
                                    true
                                )
                            ) {

                                $error =
                                    "Invalid image file.";
                            }

                        } elseif (
                            $new_media_type ===
                            "video"
                        ) {

                            if (
                                !in_array(
                                    $mime_type,
                                    $allowed_video_mimes,
                                    true
                                )
                            ) {

                                $error =
                                    "Invalid video file.";
                            }
                        }
                    }
                }

                if ($error === "") {

                    $upload_dir =
                        __DIR__ .
                        "/uploads/portfolio/";

                    if (!is_dir($upload_dir)) {

                        if (
                            !mkdir(
                                $upload_dir,
                                0755,
                                true
                            )
                        ) {

                            $error =
                                "Unable to create portfolio upload folder.";
                        }
                    }

                    if (
                        $error === "" &&
                        !is_writable($upload_dir)
                    ) {

                        $error =
                            "Portfolio upload folder is not writable.";
                    }
                }

                if ($error === "") {

                    try {

                        $random_name =
                            bin2hex(
                                random_bytes(16)
                            );

                    } catch (Exception $e) {

                        $random_name =
                            uniqid(
                                "portfolio_",
                                true
                            );
                    }

                    $new_file_name =
                        "provider_" .
                        $provider_id .
                        "_" .
                        time() .
                        "_" .
                        $random_name .
                        "." .
                        $extension;

                    $new_file_path =
                        $upload_dir .
                        $new_file_name;

                    if (
                        !move_uploaded_file(
                            $tmp_name,
                            $new_file_path
                        )
                    ) {

                        $error =
                            "Unable to save the uploaded file.";

                    } else {

                        $new_file_uploaded =
                            true;
                    }
                }
            }
        }

        if ($error === "") {

            $update =
                $conn->prepare("
                    UPDATE portfolio
                    SET
                        title = ?,
                        description = ?,
                        image = ?,
                        media_type = ?
                    WHERE id = ?
                    AND provider_id = ?
                ");

            if (!$update) {

                if (
                    $new_file_uploaded &&
                    file_exists($new_file_path) &&
                    is_file($new_file_path)
                ) {

                    unlink($new_file_path);
                }

                $error =
                    "Database error while updating portfolio item.";

            } else {

                $update->bind_param(
                    "ssssii",
                    $title,
                    $description,
                    $new_file_name,
                    $new_media_type,
                    $portfolio_id,
                    $provider_id
                );

                if ($update->execute()) {

                    if (
                        $new_file_uploaded &&
                        !empty($portfolio["image"])
                    ) {

                        $old_file_name =
                            basename(
                                $portfolio["image"]
                            );

                        $old_file_path =
                            __DIR__ .
                            "/uploads/portfolio/" .
                            $old_file_name;

                        if (
                            file_exists(
                                $old_file_path
                            ) &&
                            is_file(
                                $old_file_path
                            ) &&
                            $old_file_path !==
                            $new_file_path
                        ) {

                            unlink(
                                $old_file_path
                            );
                        }
                    }

                    $update->close();

                    $notification_title =
                        "Portfolio Updated";

                    $notification_message =
                        "Your portfolio item \"" .
                        $title .
                        "\" has been updated successfully.";

                    $notification_type =
                        "system";

                    $notification =
                        $conn->prepare("
                            INSERT INTO notifications
                            (
                                user_id,
                                user_role,
                                title,
                                message,
                                type,
                                is_read
                            )
                            VALUES
                            (
                                ?,
                                'provider',
                                ?,
                                ?,
                                ?,
                                0
                            )
                        ");

                    if ($notification) {

                        $notification->bind_param(
                            "isss",
                            $provider_id,
                            $notification_title,
                            $notification_message,
                            $notification_type
                        );

                        $notification->execute();

                        $notification->close();
                    }

                    $conn->close();

                    header(
                        "Location: portfolio.php?success=updated"
                    );

                    exit();

                } else {

                    if (
                        $new_file_uploaded &&
                        file_exists($new_file_path) &&
                        is_file($new_file_path)
                    ) {

                        unlink(
                            $new_file_path
                        );
                    }

                    $error =
                        "Unable to update portfolio item.";

                    $update->close();
                }
            }
        }
    }
}

$provider_name =
    $_SESSION["provider_name"]
    ?? "Provider";

$current_media_type =
    $portfolio["media_type"]
    ?? "image";

$current_media_file = "";

$current_media_url = "";

if (!empty($portfolio["image"])) {

    $current_media_name =
        basename(
            $portfolio["image"]
        );

    $current_media_file =
        __DIR__ .
        "/uploads/portfolio/" .
        $current_media_name;

    $current_media_url =
        "uploads/portfolio/" .
        rawurlencode(
            $current_media_name
        );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Edit Portfolio - Provider</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #fff7f0;
    color: #4b3621;
    min-height: 100vh;
}

.header {
    background: linear-gradient(
        135deg,
        #f7c6d9,
        #fff0c7
    );

    padding: 22px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 3px 15px rgba(
            120,
            80,
            90,
            0.12
        );
}

.header-left h1 {
    color: #8a5a00;
    font-size: 27px;
    margin-bottom: 5px;
}

.header-left p {
    color: #765d64;
    font-size: 14px;
}

.back-btn {
    text-decoration: none;
    background: #b8860b;
    color: white;
    padding: 11px 18px;
    border-radius: 10px;
    font-weight: bold;
}

.back-btn:hover {
    background: #9d7207;
}

.container {
    width: 92%;
    max-width: 850px;
    margin: 35px auto;
}

.card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    box-shadow:
        0 6px 20px rgba(
            100,
            70,
            80,
            0.09
        );

    border: 1px solid #f1e1e6;
}

.card h2 {
    color: #5d4148;
    font-size: 24px;
    margin-bottom: 25px;
}

.current-media {
    margin-bottom: 25px;
}

.current-media h3 {
    color: #6d4c57;
    font-size: 16px;
    margin-bottom: 12px;
}

.media-box {
    width: 100%;
    height: 350px;
    background: #f8efe7;
    border-radius: 14px;
    overflow: hidden;
}

.media-box img,
.media-box video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.no-media {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    color: #654452;
    font-weight: bold;
    margin-bottom: 8px;
}

input[type="text"],
textarea,
input[type="file"] {
    width: 100%;
    padding: 13px;
    border: 1px solid #e7c7d0;
    border-radius: 10px;
    background: #fffdfd;
    font-size: 15px;
}

input[type="text"]:focus,
textarea:focus,
input[type="file"]:focus {
    outline: none;
    border-color: #dca8bc;
    box-shadow:
        0 0 0 3px rgba(
            248,
            200,
            220,
            0.25
        );
}

textarea {
    min-height: 130px;
    resize: vertical;
}

input[type="file"] {
    padding: 10px;
}

.help-text {
    color: #8c747d;
    font-size: 13px;
    line-height: 1.6;
    margin-top: 8px;
}

.error {
    background: #ffe2e2;
    color: #a33a3a;
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.preview {
    display: none;
    margin-top: 18px;
}

.preview img,
.preview video {
    width: 100%;
    max-height: 350px;
    object-fit: contain;
    border-radius: 12px;
    background: #f8efe7;
}

.update-btn {
    width: 100%;
    border: none;
    padding: 14px;
    border-radius: 10px;
    background: linear-gradient(
        135deg,
        #e9a9c1,
        #e8c45d
    );
    color: #5d3d47;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.update-btn:hover {
    opacity: 0.9;
}

.cancel-btn {
    display: block;
    text-align: center;
    text-decoration: none;
    margin-top: 12px;
    padding: 13px;
    border-radius: 10px;
    background: #f3eeee;
    color: #765d64;
    font-weight: bold;
}

.cancel-btn:hover {
    background: #e9dddd;
}

@media (max-width: 700px) {

    .header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
    }

    .header-left h1 {
        font-size: 23px;
    }

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .card {
        padding: 20px;
    }

    .media-box {
        height: 250px;
    }

}

</style>

</head>

<body>

<header class="header">

    <div class="header-left">

        <h1>
            ✏️ Edit Portfolio
        </h1>

        <p>
            Welcome,
            <?= htmlspecialchars(
                $provider_name,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>

    </div>

    <a
        href="portfolio.php"
        class="back-btn"
    >
        ← Portfolio
    </a>

</header>

<div class="container">

    <div class="card">

        <h2>
            Edit Portfolio Media
        </h2>

        <?php if ($error !== ""): ?>

            <div class="error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <div class="current-media">

            <h3>
                Current Media
            </h3>

            <div class="media-box">

                <?php if (
                    !empty(
                        $portfolio["image"]
                    ) &&
                    file_exists(
                        $current_media_file
                    ) &&
                    is_file(
                        $current_media_file
                    )
                ): ?>

                    <?php if (
                        $current_media_type ===
                        "video"
                    ): ?>

                        <video controls>

                            <source
                                src="<?= htmlspecialchars(
                                    $current_media_url,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                            Your browser does not support video playback.

                        </video>

                    <?php else: ?>

                        <img
                            src="<?= htmlspecialchars(
                                $current_media_url,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            alt="<?= htmlspecialchars(
                                $portfolio["title"] ?? "Portfolio",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                    <?php endif; ?>

                <?php else: ?>

                    <div class="no-media">
                        📁
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <div class="form-group">

                <label for="title">
                    Title
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    value="<?= htmlspecialchars(
                        $title,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    maxlength="255"
                    required
                >

            </div>

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="2000"
                ><?= htmlspecialchars(
                    $description,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

                <div class="help-text">
                    Maximum 2000 characters.
                </div>

            </div>

            <div class="form-group">

                <label for="media">
                    Replace Photo / Video
                </label>

                <input
                    type="file"
                    id="media"
                    name="media"
                    accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov"
                >

                <div class="help-text">
                    Leave empty if you want to keep the current media.
                    <br>
                    Photos: JPG, JPEG, PNG, WEBP up to 5MB
                    <br>
                    Videos: MP4, WEBM, MOV up to 50MB
                </div>

                <div
                    class="preview"
                    id="preview"
                ></div>

            </div>

            <button
                type="submit"
                class="update-btn"
            >
                💾 Update Portfolio
            </button>

            <a
                href="portfolio.php"
                class="cancel-btn"
            >
                Cancel
            </a>

        </form>

    </div>

</div>

<script>

const mediaInput =
    document.getElementById("media");

const preview =
    document.getElementById("preview");

let previewUrl = null;

mediaInput.addEventListener(
    "change",
    function () {

        preview.innerHTML = "";

        preview.style.display =
            "none";

        if (previewUrl) {

            URL.revokeObjectURL(
                previewUrl
            );

            previewUrl = null;
        }

        const file =
            this.files[0];

        if (!file) {
            return;
        }

        previewUrl =
            URL.createObjectURL(file);

        if (
            file.type.startsWith(
                "image/"
            )
        ) {

            const image =
                document.createElement(
                    "img"
                );

            image.src =
                previewUrl;

            image.alt =
                "New portfolio preview";

            preview.appendChild(
                image
            );

            preview.style.display =
                "block";

        } else if (
            file.type.startsWith(
                "video/"
            )
        ) {

            const video =
                document.createElement(
                    "video"
                );

            video.src =
                previewUrl;

            video.controls =
                true;

            video.preload =
                "metadata";

            preview.appendChild(
                video
            );

            preview.style.display =
                "block";
        }

    }
);

</script>

</body>

</html>

