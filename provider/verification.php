
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

$provider_id =
    (int) $_SESSION["provider_id"];

if ($provider_id <= 0) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

if (!isset($_SESSION["verification_csrf_token"])) {
    $_SESSION["verification_csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION["verification_csrf_token"];

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        citizenship_file,
        selfie_file,
        status
    FROM providers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    $error_message =
        $conn->error;

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
    "i",
    $provider_id
);

$stmt->execute();

$result =
    $stmt->get_result();

if (
    !$result ||
    $result->num_rows !== 1
) {

    $stmt->close();
    $conn->close();

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

$provider =
    $result->fetch_assoc();

$stmt->close();

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

        $old_citizenship_file =
            $provider["citizenship_file"] ?? "";

        $old_selfie_file =
            $provider["selfie_file"] ?? "";

        $citizenship_file =
            $old_citizenship_file;

        $selfie_file =
            $old_selfie_file;

        $new_uploaded_files = [];

        $upload_dir =
            __DIR__ .
            "/uploads/verification/";

        if (!is_dir($upload_dir)) {

            if (
                !mkdir(
                    $upload_dir,
                    0755,
                    true
                )
            ) {

                $error =
                    "Unable to create verification upload folder.";
            }
        }

        if (
            $error === "" &&
            !is_writable($upload_dir)
        ) {

            $error =
                "Verification upload folder is not writable.";
        }

        if (
            $error === "" &&
            isset($_FILES["citizenship"]) &&
            $_FILES["citizenship"]["error"] !==
            UPLOAD_ERR_NO_FILE
        ) {

            $file =
                $_FILES["citizenship"];

            if (
                $file["error"] !==
                UPLOAD_ERR_OK
            ) {

                $error =
                    "Citizenship upload failed.";

            } elseif (
                !is_uploaded_file(
                    $file["tmp_name"]
                )
            ) {

                $error =
                    "Invalid citizenship upload.";

            } elseif (
                (int) $file["size"] <= 0
            ) {

                $error =
                    "Citizenship file is empty.";

            } elseif (
                (int) $file["size"] >
                5 * 1024 * 1024
            ) {

                $error =
                    "Citizenship file must be 5MB or smaller.";

            } else {

                $extension =
                    strtolower(
                        pathinfo(
                            $file["name"],
                            PATHINFO_EXTENSION
                        )
                    );

                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp",
                    "pdf"
                ];

                if (
                    !in_array(
                        $extension,
                        $allowed_extensions,
                        true
                    )
                ) {

                    $error =
                        "Citizenship must be JPG, JPEG, PNG, WEBP or PDF.";
                }

                if ($error === "") {

                    $finfo =
                        finfo_open(
                            FILEINFO_MIME_TYPE
                        );

                    if ($finfo === false) {

                        $error =
                            "Unable to verify citizenship file.";

                    } else {

                        $mime_type =
                            finfo_file(
                                $finfo,
                                $file["tmp_name"]
                            );

                        finfo_close($finfo);

                        $allowed_mimes = [
                            "image/jpeg",
                            "image/png",
                            "image/webp",
                            "application/pdf"
                        ];

                        if (
                            !in_array(
                                $mime_type,
                                $allowed_mimes,
                                true
                            )
                        ) {

                            $error =
                                "Invalid citizenship file.";
                        }
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
                                "citizenship_",
                                true
                            );
                    }

                    $new_name =
                        "citizenship_" .
                        $provider_id .
                        "_" .
                        time() .
                        "_" .
                        $random_name .
                        "." .
                        $extension;

                    $target =
                        $upload_dir .
                        $new_name;

                    if (
                        !move_uploaded_file(
                            $file["tmp_name"],
                            $target
                        )
                    ) {

                        $error =
                            "Could not save citizenship file.";

                    } else {

                        $citizenship_file =
                            $new_name;

                        $new_uploaded_files[] =
                            $target;
                    }
                }
            }
        }

        if (
            $error === "" &&
            isset($_FILES["selfie"]) &&
            $_FILES["selfie"]["error"] !==
            UPLOAD_ERR_NO_FILE
        ) {

            $file =
                $_FILES["selfie"];

            if (
                $file["error"] !==
                UPLOAD_ERR_OK
            ) {

                $error =
                    "Selfie upload failed.";

            } elseif (
                !is_uploaded_file(
                    $file["tmp_name"]
                )
            ) {

                $error =
                    "Invalid selfie upload.";

            } elseif (
                (int) $file["size"] <= 0
            ) {

                $error =
                    "Selfie file is empty.";

            } elseif (
                (int) $file["size"] >
                5 * 1024 * 1024
            ) {

                $error =
                    "Selfie file must be 5MB or smaller.";

            } else {

                $extension =
                    strtolower(
                        pathinfo(
                            $file["name"],
                            PATHINFO_EXTENSION
                        )
                    );

                $allowed_extensions = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                if (
                    !in_array(
                        $extension,
                        $allowed_extensions,
                        true
                    )
                ) {

                    $error =
                        "Selfie must be JPG, JPEG or WEBP.";
                }

                if ($error === "") {

                    $finfo =
                        finfo_open(
                            FILEINFO_MIME_TYPE
                        );

                    if ($finfo === false) {

                        $error =
                            "Unable to verify selfie file.";

                    } else {

                        $mime_type =
                            finfo_file(
                                $finfo,
                                $file["tmp_name"]
                            );

                        finfo_close($finfo);

                        $allowed_mimes = [
                            "image/jpeg",
                            "image/png",
                            "image/webp"
                        ];

                        if (
                            !in_array(
                                $mime_type,
                                $allowed_mimes,
                                true
                            )
                        ) {

                            $error =
                                "Invalid selfie file.";
                        }
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
                                "selfie_",
                                true
                            );
                    }

                    $new_name =
                        "selfie_" .
                        $provider_id .
                        "_" .
                        time() .
                        "_" .
                        $random_name .
                        "." .
                        $extension;

                    $target =
                        $upload_dir .
                        $new_name;

                    if (
                        !move_uploaded_file(
                            $file["tmp_name"],
                            $target
                        )
                    ) {

                        $error =
                            "Could not save selfie.";

                    } else {

                        $selfie_file =
                            $new_name;

                        $new_uploaded_files[] =
                            $target;
                    }
                }
            }
        }

        if ($error === "") {

            $documents_changed =
                (
                    $citizenship_file !==
                    $old_citizenship_file
                ) ||
                (
                    $selfie_file !==
                    $old_selfie_file
                );

            if (!$documents_changed) {

                $error =
                    "Please upload at least one verification document.";

            } else {

                $new_status =
                    "pending";

                $update =
                    $conn->prepare("
                        UPDATE providers
                        SET
                            citizenship_file = ?,
                            selfie_file = ?,
                            status = ?
                        WHERE id = ?
                    ");

                if (!$update) {

                    foreach (
                        $new_uploaded_files
                        as $new_file
                    ) {

                        if (
                            file_exists($new_file) &&
                            is_file($new_file)
                        ) {

                            unlink($new_file);
                        }
                    }

                    $error =
                        "Database error while updating verification.";

                } else {

                    $update->bind_param(
                        "sssi",
                        $citizenship_file,
                        $selfie_file,
                        $new_status,
                        $provider_id
                    );

                    if ($update->execute()) {

                        $update->close();

                        if (
                            $citizenship_file !==
                            $old_citizenship_file &&
                            !empty($old_citizenship_file)
                        ) {

                            $old_file_name =
                                basename(
                                    $old_citizenship_file
                                );

                            $old_file_path =
                                $upload_dir .
                                $old_file_name;

                            if (
                                file_exists(
                                    $old_file_path
                                ) &&
                                is_file(
                                    $old_file_path
                                )
                            ) {

                                unlink(
                                    $old_file_path
                                );
                            }
                        }

                        if (
                            $selfie_file !==
                            $old_selfie_file &&
                            !empty($old_selfie_file)
                        ) {

                            $old_file_name =
                                basename(
                                    $old_selfie_file
                                );

                            $old_file_path =
                                $upload_dir .
                                $old_file_name;

                            if (
                                file_exists(
                                    $old_file_path
                                ) &&
                                is_file(
                                    $old_file_path
                                )
                            ) {

                                unlink(
                                    $old_file_path
                                );
                            }
                        }

                        $notification_title =
                            "Verification Submitted";

                        $notification_message =
                            "Your verification documents have been submitted successfully and are now pending admin verification.";

                        $notification_type =
                            "verification";

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

                        $provider[
                            "citizenship_file"
                        ] =
                            $citizenship_file;

                        $provider[
                            "selfie_file"
                        ] =
                            $selfie_file;

                        $provider["status"] =
                            $new_status;

                        $success =
                            "Verification documents uploaded successfully. Your account is now pending admin verification.";

                    } else {

                        $update->close();

                        foreach (
                            $new_uploaded_files
                            as $new_file
                        ) {

                            if (
                                file_exists($new_file) &&
                                is_file($new_file)
                            ) {

                                unlink($new_file);
                            }
                        }

                        $error =
                            "Failed to update verification.";
                    }
                }
            }
        } else {

            foreach (
                $new_uploaded_files
                as $new_file
            ) {

                if (
                    file_exists($new_file) &&
                    is_file($new_file)
                ) {

                    unlink($new_file);
                }
            }
        }
    }
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

<title>
    Verification - Provider
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #fff8f0;
    color: #4b3621;
}

.container {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow:
        0 5px 20px rgba(
            0,
            0,
            0,
            0.08
        );
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

h1 {
    margin: 0;
    color: #4b3621;
}

.back {
    text-decoration: none;
    background: #eee;
    color: #4b3621;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: bold;
}

.info {
    background: #fff8e8;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    line-height: 1.6;
}

.status {
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-weight: bold;
}

.status.pending {
    background: #fff3cd;
    color: #856404;
}

.status.active {
    background: #d4edda;
    color: #155724;
}

.status.rejected {
    background: #f8d7da;
    color: #721c24;
}

.status.inactive {
    background: #e2e3e5;
    color: #383d41;
}

.form-group {
    margin-bottom: 22px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input[type="file"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
}

.help {
    color: #777;
    font-size: 13px;
    margin-top: 6px;
    line-height: 1.5;
}

button {
    border: none;
    background: #d4af37;
    color: white;
    padding: 13px 22px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #b8860b;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.documents {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 20px;
}

.document {
    border: 1px solid #eee;
    padding: 15px;
    border-radius: 10px;
    background: #fafafa;
}

.document strong {
    display: block;
    margin-bottom: 7px;
}

.uploaded {
    color: #198754;
    font-weight: bold;
}

.not-uploaded {
    color: #dc3545;
    font-weight: bold;
}

@media(max-width:600px) {

    .container {
        margin: 15px auto;
        padding: 12px;
    }

    .card {
        padding: 20px;
    }

    .top {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .documents {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="top">

        <h1>
            🪪 Verification
        </h1>

        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>

    <div class="card">

        <div class="info">

            <strong>
                Provider:
            </strong>

            <?= htmlspecialchars(
                $provider["name"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <br>

            <strong>
                Email:
            </strong>

            <?= htmlspecialchars(
                $provider["email"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

        <div class="status <?= htmlspecialchars(
            $provider["status"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>">

            <?php if (
                $provider["status"] ===
                "pending"
            ): ?>

                ⏳ Verification Pending

                <br>

                <small>
                    Your documents are waiting for admin verification.
                </small>

            <?php elseif (
                $provider["status"] ===
                "active"
            ): ?>

                ✅ Verification Approved

                <br>

                <small>
                    Your provider account is active.
                </small>

            <?php elseif (
                $provider["status"] ===
                "rejected"
            ): ?>

                ❌ Verification Rejected

                <br>

                <small>
                    Please upload correct documents and submit again.
                </small>

            <?php else: ?>

                ⚠️ Account Inactive

            <?php endif; ?>

        </div>

        <?php if ($error !== ""): ?>

            <div class="error">

                ❌

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="success">

                ✅

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <div class="documents">

            <div class="document">

                <strong>
                    🪪 Citizenship
                </strong>

                <?php if (
                    !empty(
                        $provider["citizenship_file"]
                    )
                ): ?>

                    <span class="uploaded">
                        ✅ Uploaded
                    </span>

                <?php else: ?>

                    <span class="not-uploaded">
                        ❌ Not Uploaded
                    </span>

                <?php endif; ?>

            </div>

            <div class="document">

                <strong>
                    🤳 Selfie
                </strong>

                <?php if (
                    !empty(
                        $provider["selfie_file"]
                    )
                ): ?>

                    <span class="uploaded">
                        ✅ Uploaded
                    </span>

                <?php else: ?>

                    <span class="not-uploaded">
                        ❌ Not Uploaded
                    </span>

                <?php endif; ?>

            </div>

        </div>

        <hr
            style="
                margin:30px 0;
                border:none;
                border-top:1px solid #eee;
            "
        >

        <h2>
            📤 Upload Documents
        </h2>

        <p style="color:#777;">

            Upload your citizenship and selfie for
            provider verification.

        </p>

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

                <label for="citizenship">

                    🪪 Citizenship Document

                </label>

                <input
                    type="file"
                    name="citizenship"
                    id="citizenship"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                >

                <div class="help">

                    JPG, JPEG, PNG, WEBP or PDF.
                    Maximum size: 5MB.

                </div>

            </div>

            <div class="form-group">

                <label for="selfie">

                    🤳 Selfie

                </label>

                <input
                    type="file"
                    name="selfie"
                    id="selfie"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <div class="help">

                    Upload a clear photo of yourself.
                    Maximum size: 5MB.

                </div>

            </div>

            <button type="submit">

                📤 Submit for Verification

            </button>

        </form>

    </div>

</div>

</body>

</html>

