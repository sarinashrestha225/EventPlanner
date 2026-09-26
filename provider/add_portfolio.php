
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

if (!isset($_SESSION["portfolio_csrf_token"])) {
    $_SESSION["portfolio_csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["portfolio_csrf_token"];

$title = "";
$description = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $csrf_token,
            $posted_token
        )
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");

        if ($title === "") {

            $error = "Please enter a title.";

        } elseif (mb_strlen($title) > 255) {

            $error = "Title must be 255 characters or less.";

        } elseif (mb_strlen($description) > 2000) {

            $error = "Description must be 2000 characters or less.";

        } elseif (
            !isset($_FILES["media"]) ||
            $_FILES["media"]["error"] === UPLOAD_ERR_NO_FILE
        ) {

            $error = "Please select a photo or video.";

        } else {

            $file = $_FILES["media"];

            if ($file["error"] !== UPLOAD_ERR_OK) {

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
                    $upload_errors[$file["error"]]
                    ?? "File upload failed.";

            } else {

                $original_name =
                    (string) ($file["name"] ?? "");

                $tmp_name =
                    (string) ($file["tmp_name"] ?? "");

                $file_size =
                    (int) ($file["size"] ?? 0);

                $extension = strtolower(
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

                $media_type = "";
                $max_size = 0;

                if (
                    in_array(
                        $extension,
                        $image_extensions,
                        true
                    )
                ) {

                    $media_type = "image";
                    $max_size = 5 * 1024 * 1024;

                } elseif (
                    in_array(
                        $extension,
                        $video_extensions,
                        true
                    )
                ) {

                    $media_type = "video";
                    $max_size = 50 * 1024 * 1024;

                } else {

                    $error =
                        "Only JPG, JPEG, PNG, WEBP, MP4, WEBM and MOV files are allowed.";
                }

                if (
                    $error === "" &&
                    $file_size <= 0
                ) {

                    $error = "The uploaded file is empty.";

                } elseif (
                    $error === "" &&
                    $file_size > $max_size
                ) {

                    if ($media_type === "image") {

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

                    $finfo = finfo_open(
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

                        if ($media_type === "image") {

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

                        } elseif ($media_type === "video") {

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

                    $file_name =
                        "provider_" .
                        $provider_id .
                        "_" .
                        time() .
                        "_" .
                        $random_name .
                        "." .
                        $extension;

                    $file_path =
                        $upload_dir .
                        $file_name;

                    if (
                        !move_uploaded_file(
                            $tmp_name,
                            $file_path
                        )
                    ) {

                        $error =
                            "Unable to save the uploaded file.";

                    } else {

                        $stmt = $conn->prepare("
                            INSERT INTO portfolio
                            (
                                provider_id,
                                title,
                                description,
                                image,
                                media_type
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        if (!$stmt) {

                            if (
                                file_exists($file_path) &&
                                is_file($file_path)
                            ) {
                                unlink($file_path);
                            }

                            $error =
                                "Database error while saving portfolio item.";

                        } else {

                            $stmt->bind_param(
                                "issss",
                                $provider_id,
                                $title,
                                $description,
                                $file_name,
                                $media_type
                            );

                            if ($stmt->execute()) {

                                $notification_title =
                                    "Portfolio Media Added";

                                $notification_message =
                                    "Your portfolio item \"" .
                                    $title .
                                    "\" has been added successfully.";

                                $notification_type =
                                    "system";

                                $notification = $conn->prepare("
                                    INSERT INTO notifications
                                    (
                                        user_id,
                                        user_role,
                                        title,
                                        message,
                                        type,
                                        is_read
                                    )
                                    VALUES (?, 'provider', ?, ?, ?, 0)
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

                                $stmt->close();
                                $conn->close();

                                header(
                                    "Location: portfolio.php?success=added"
                                );

                                exit();

                            } else {

                                if (
                                    file_exists($file_path) &&
                                    is_file($file_path)
                                ) {
                                    unlink($file_path);
                                }

                                $error =
                                    "Unable to save portfolio item.";

                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Portfolio</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fff8f3;
            color: #5c4650;
        }

        .header {
            background: linear-gradient(
                135deg,
                #f8c8dc,
                #f6d77a
            );

            padding: 20px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .header h1 {
            color: #6b4052;
            font-size: 25px;
        }

        .back-btn {
            text-decoration: none;
            background: #fff;
            color: #8b5e3c;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: bold;
        }

        .back-btn:hover {
            background: #fff8f0;
        }

        .container {
            max-width: 850px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            padding: 30px;
            box-shadow:
                0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f2d9df;
        }

        .card h2 {
            color: #7a4b5d;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #654452;
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
            margin-top: 8px;
            font-size: 13px;
            color: #8c747d;
            line-height: 1.6;
        }

        .error {
            background: #ffe2e2;
            color: #a33a3a;
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .submit-btn {
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

        .submit-btn:hover {
            opacity: 0.9;
        }

        .preview {
            margin-top: 20px;
            display: none;
        }

        .preview img,
        .preview video {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 12px;
            background: #f8f1f3;
        }

        @media (max-width: 600px) {

            .header {
                padding: 16px;
            }

            .header h1 {
                font-size: 20px;
            }

            .container {
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<div class="header">

    <h1>
        📸 Add Portfolio
    </h1>

    <a
        href="portfolio.php"
        class="back-btn"
    >
        ← Back
    </a>

</div>

<div class="container">

    <div class="card">

        <h2>
            Add Photo or Video
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
                    placeholder="Example: Wedding Photography"
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
                    placeholder="Write something about this work..."
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
                    Photo or Video
                </label>

                <input
                    type="file"
                    id="media"
                    name="media"
                    accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov"
                    required
                >

                <div class="help-text">

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
                class="submit-btn"
            >
                Upload to Portfolio
            </button>

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

        preview.style.display = "none";

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
                document.createElement("img");

            image.src =
                previewUrl;

            image.alt =
                "Portfolio preview";

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
                document.createElement("video");

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
