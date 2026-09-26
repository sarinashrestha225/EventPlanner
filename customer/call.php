<?php

session_start();

require_once "../database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["active_role"]) ||
    $_SESSION["active_role"] !== "customer"
) {
    header("Location: login.php");
    exit;
}

$customer_id = (int) $_SESSION["user_id"];

$booking_id = isset($_GET["booking_id"]) ? (int) $_GET["booking_id"] : 0;
$call_type = isset($_GET["type"]) && $_GET["type"] === "audio" ? "audio" : "video";

if ($booking_id <= 0) {
    die("Invalid booking.");
}

$stmt = $conn->prepare("
    SELECT
        b.id,
        b.customer_id,
        b.provider_id,
        p.name AS provider_name,
        p.phone AS provider_phone
    FROM bookings b
    LEFT JOIN providers p ON p.id = b.provider_id
    WHERE b.id = ?
      AND b.customer_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();

if (!$booking) {
    die("Booking not found or access denied.");
}

$provider_id = (int) $booking["provider_id"];
$provider_name = $booking["provider_name"] ?: "Provider";
$provider_phone = $booking["provider_phone"] ?: "";

$api_url = "../call_api.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?php echo $call_type === "video" ? "Video Call" : "Audio Call"; ?></title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
    background: #111;
    font-family: Arial, sans-serif;
    overflow: hidden;
}

.call-wrapper {
    width: 100%;
    height: 100vh;
    position: relative;
    background: #111;
}

.remote-area {
    position: absolute;
    inset: 0;
    background: #1b1b1b;
    display: flex;
    align-items: center;
    justify-content: center;
}

#remoteVideo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: #111;
}

.remote-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: white;
    z-index: 2;
}

.remote-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #ff5fa2;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 42px;
    font-weight: bold;
    margin-bottom: 18px;
}

.remote-name {
    font-size: 22px;
    font-weight: 600;
}

.call-status {
    margin-top: 8px;
    color: #ccc;
    font-size: 14px;
}

.local-video-box {
    position: absolute;
    right: 18px;
    top: 18px;
    width: 180px;
    height: 130px;
    border-radius: 14px;
    overflow: hidden;
    background: #222;
    border: 2px solid rgba(255,255,255,0.35);
    z-index: 10;
    box-shadow: 0 8px 25px rgba(0,0,0,0.35);
}

#localVideo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.audio-remote {
    display: none;
}

.top-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 18px 22px;
    color: white;
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(
        to bottom,
        rgba(0,0,0,0.55),
        transparent
    );
    z-index: 20;
}

.top-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #ff5fa2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.top-info {
    display: flex;
    flex-direction: column;
}

.top-name {
    font-size: 16px;
    font-weight: 600;
}

.top-status {
    font-size: 12px;
    color: #ddd;
}

.controls {
    position: absolute;
    bottom: 35px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    z-index: 30;
}

.control-btn {
    width: 52px;
    height: 52px;
    border: none;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    color: white;
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
}

.control-btn:hover {
    background: rgba(255,255,255,0.28);
}

.end-btn {
    width: 60px;
    height: 60px;
    background: #ff315c;
    font-size: 24px;
}

.end-btn:hover {
    background: #e9284f;
}

.hidden {
    display: none !important;
}

.audio-screen {
    position: absolute;
    inset: 0;
    background: linear-gradient(145deg, #242424, #111);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: white;
}

.audio-avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: #ff5fa2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    font-weight: bold;
    margin-bottom: 25px;
}

.audio-name {
    font-size: 25px;
    font-weight: 600;
}

.audio-status {
    margin-top: 10px;
    color: #bbb;
}

#errorBox {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: min(90%, 450px);
    background: rgba(0,0,0,0.9);
    color: white;
    padding: 22px;
    border-radius: 15px;
    z-index: 100;
    display: none;
    text-align: center;
}

#errorBox button {
    margin-top: 15px;
    padding: 9px 18px;
    border: none;
    border-radius: 8px;
    background: #ff5fa2;
    color: white;
    cursor: pointer;
}

@media (max-width: 600px) {

    .local-video-box {
        width: 125px;
        height: 95px;
        right: 10px;
        top: 70px;
    }

    .controls {
        bottom: 20px;
        gap: 9px;
    }

    .control-btn {
        width: 46px;
        height: 46px;
    }

    .end-btn {
        width: 55px;
        height: 55px;
    }

}

</style>
</head>

<body>

<div class="call-wrapper">

    <div class="remote-area">

        <video
            id="remoteVideo"
            autoplay
            playsinline
        ></video>

        <div id="remotePlaceholder" class="remote-placeholder">

            <div class="remote-avatar">
                <?php echo strtoupper(substr($provider_name, 0, 1)); ?>
            </div>

            <div class="remote-name">
                <?php echo htmlspecialchars($provider_name); ?>
            </div>

            <div id="remoteStatus" class="call-status">
                Connecting...
            </div>

        </div>

    </div>

    <?php if ($call_type === "video"): ?>

        <div class="local-video-box">
            <video
                id="localVideo"
                autoplay
                muted
                playsinline
            ></video>
        </div>

    <?php else: ?>

        <div class="audio-screen">

            <div class="audio-avatar">
                <?php echo strtoupper(substr($provider_name, 0, 1)); ?>
            </div>

            <div class="audio-name">
                <?php echo htmlspecialchars($provider_name); ?>
            </div>

            <div id="audioStatus" class="audio-status">
                Connecting...
            </div>

        </div>

    <?php endif; ?>

    <div class="top-bar">

        <div class="top-avatar">
            <?php echo strtoupper(substr($provider_name, 0, 1)); ?>
        </div>

        <div class="top-info">

            <div class="top-name">
                <?php echo htmlspecialchars($provider_name); ?>
            </div>

            <div class="top-status" id="connectionStatus">
                Calling...
            </div>

        </div>

    </div>

    <div class="controls">

        <button
            type="button"
            class="control-btn"
            id="micBtn"
            title="Mute microphone"
        >
            🎤
        </button>

        <?php if ($call_type === "video"): ?>

            <button
                type="button"
                class="control-btn"
                id="cameraBtn"
                title="Turn camera off"
            >
                📷
            </button>

        <?php endif; ?>

        <button
            type="button"
            class="control-btn"
            id="speakerBtn"
            title="Speaker"
        >
            🔊
        </button>

        <button
            type="button"
            class="control-btn end-btn"
            id="endBtn"
            title="End call"
        >
            ☎
        </button>

    </div>

    <div id="errorBox">

        <div id="errorText"></div>

        <button type="button" onclick="closeError()">
            OK
        </button>

    </div>

</div>

<script>

const bookingId = <?php echo $booking_id; ?>;
const callType = "<?php echo $call_type; ?>";
const providerId = <?php echo $provider_id; ?>;
const apiUrl = "<?php echo $api_url; ?>";

let peerConnection = null;
let localStream = null;

let lastSignalId = 0;
let polling = true;
let callStarted = false;
let remoteDescriptionReady = false;

let pendingIceCandidates = [];

const remoteVideo = document.getElementById("remoteVideo");
const localVideo = document.getElementById("localVideo");

const remotePlaceholder = document.getElementById("remotePlaceholder");
const remoteStatus = document.getElementById("remoteStatus");

const connectionStatus = document.getElementById("connectionStatus");

const micBtn = document.getElementById("micBtn");
const cameraBtn = document.getElementById("cameraBtn");
const speakerBtn = document.getElementById("speakerBtn");
const endBtn = document.getElementById("endBtn");

const audioStatus = document.getElementById("audioStatus");

function showError(message) {

    document.getElementById("errorText").textContent = message;
    document.getElementById("errorBox").style.display = "block";
}

function closeError() {

    document.getElementById("errorBox").style.display = "none";
}

function updateStatus(message) {

    connectionStatus.textContent = message;

    if (remoteStatus) {
        remoteStatus.textContent = message;
    }

    if (audioStatus) {
        audioStatus.textContent = message;
    }
}

async function sendSignal(signalType, signalData = {}) {

    const formData = new FormData();

    formData.append("action", "send_signal");
    formData.append("booking_id", bookingId);
    formData.append("signal_type", signalType);
    formData.append(
        "signal_data",
        JSON.stringify(signalData)
    );

    const response = await fetch(apiUrl, {
        method: "POST",
        body: formData
    });

    const data = await response.json();

    if (!data.success) {
        throw new Error(
            data.message || "Unable to send call signal."
        );
    }

    return data;
}

async function getSignals() {

    if (!polling) {
        return;
    }

    try {

        const url =
            apiUrl +
            "?action=get_signals" +
            "&booking_id=" +
            encodeURIComponent(bookingId) +
            "&after_id=" +
            encodeURIComponent(lastSignalId);

        const response = await fetch(url, {
            cache: "no-store"
        });

        const data = await response.json();

        if (!data.success) {
            return;
        }

        if (!Array.isArray(data.signals)) {
            return;
        }

        for (const signal of data.signals) {

            if (signal.id > lastSignalId) {
                lastSignalId = signal.id;
            }

            await handleSignal(signal);
        }

    } catch (error) {

        console.log(
            "Signal polling error:",
            error
        );
    }
}

async function handleSignal(signal) {

    let signalData = {};

    try {

        signalData =
            signal.signal_data
                ? JSON.parse(signal.signal_data)
                : {};

    } catch (error) {

        console.log(
            "Invalid signal data:",
            error
        );

        return;
    }

    if (signal.signal_type === "answer") {

        if (!peerConnection) {
            return;
        }

        try {

            await peerConnection.setRemoteDescription(
                new RTCSessionDescription(signalData)
            );

            remoteDescriptionReady = true;

            await flushPendingIceCandidates();

            updateStatus("Connected");

        } catch (error) {

            console.error(
                "Answer error:",
                error
            );
        }

        return;
    }

    if (signal.signal_type === "ice") {

        await handleRemoteIce(signalData);

        return;
    }

    if (signal.signal_type === "end") {

        updateStatus("Call ended");

        stopCall(false);

        return;
    }

    if (signal.signal_type === "reject") {

        updateStatus("Call rejected");

        stopCall(false);

        return;
    }

}

async function handleRemoteIce(candidateData) {

    if (!candidateData) {
        return;
    }

    if (!peerConnection) {
        return;
    }

    if (!remoteDescriptionReady) {

        pendingIceCandidates.push(
            candidateData
        );

        return;
    }

    try {

        await peerConnection.addIceCandidate(
            new RTCIceCandidate(candidateData)
        );

    } catch (error) {

        console.error(
            "ICE candidate error:",
            error
        );
    }
}

async function flushPendingIceCandidates() {

    if (!peerConnection) {
        return;
    }

    if (!remoteDescriptionReady) {
        return;
    }

    while (pendingIceCandidates.length > 0) {

        const candidate =
            pendingIceCandidates.shift();

        try {

            await peerConnection.addIceCandidate(
                new RTCIceCandidate(candidate)
            );

        } catch (error) {

            console.error(
                "Pending ICE error:",
                error
            );
        }
    }
}

function createPeerConnection() {

    const pc = new RTCPeerConnection({

        iceServers: [

            {
                urls: "stun:stun.l.google.com:19302"
            },

            {
                urls: "stun:stun1.l.google.com:19302"
            }

        ]

    });

    pc.onicecandidate = async function(event) {

        if (!event.candidate) {
            return;
        }

        try {

            await sendSignal(
                "ice",
                event.candidate.toJSON()
            );

        } catch (error) {

            console.error(
                "ICE send error:",
                error
            );
        }
    };

    pc.ontrack = function(event) {

        if (!event.streams || !event.streams[0]) {
            return;
        }

        const remoteStream =
            event.streams[0];

        if (remoteVideo) {

            remoteVideo.srcObject =
                remoteStream;

            remotePlaceholder.classList.add(
                "hidden"
            );

            remoteVideo.play().catch(() => {});

        }

        updateStatus("Connected");
    };

    pc.onconnectionstatechange = function() {

        const state =
            pc.connectionState;

        console.log(
            "WebRTC connection:",
            state
        );

        if (state === "connected") {

            updateStatus("Connected");

        } else if (state === "connecting") {

            updateStatus("Connecting...");

        } else if (state === "disconnected") {

            updateStatus("Disconnected");

        } else if (state === "failed") {

            updateStatus("Connection failed");

        } else if (state === "closed") {

            updateStatus("Call ended");
        }
    };

    pc.oniceconnectionstatechange =
        function() {

            console.log(
                "ICE state:",
                pc.iceConnectionState
            );
        };

    return pc;
}

async function startLocalMedia() {

    const constraints = {

        audio: true,

        video:
            callType === "video"
                ? {
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    },
                    facingMode: "user"
                }
                : false

    };

    try {

        localStream =
            await navigator.mediaDevices.getUserMedia(
                constraints
            );

    } catch (error) {

        console.error(
            "getUserMedia error:",
            error
        );

        if (error.name === "NotAllowedError") {

            throw new Error(
                "Camera/microphone permission was denied. Please allow permission and try again."
            );

        }

        if (error.name === "NotFoundError") {

            throw new Error(
                "Camera or microphone was not found."
            );

        }

        throw new Error(
            "Could not access camera or microphone."
        );
    }

    if (localVideo) {

        localVideo.srcObject =
            localStream;

        localVideo.play().catch(() => {});
    }

    return localStream;
}

async function startCall() {

    try {

        updateStatus("Starting call...");

        localStream =
            await startLocalMedia();

        peerConnection =
            createPeerConnection();

        localStream
            .getTracks()
            .forEach(function(track) {

                peerConnection.addTrack(
                    track,
                    localStream
                );

            });

        const offer =
            await peerConnection.createOffer();

        await peerConnection.setLocalDescription(
            offer
        );

        await sendSignal(
            "offer",
            peerConnection.localDescription
        );

        callStarted = true;

        updateStatus(
            "Calling " +
            <?php echo json_encode($provider_name); ?>
            + "..."
        );

    } catch (error) {

        console.error(
            "Start call error:",
            error
        );

        showError(
            error.message ||
            "Unable to start call."
        );
    }
}

function toggleMic() {

    if (!localStream) {
        return;
    }

    const audioTracks =
        localStream.getAudioTracks();

    if (!audioTracks.length) {
        return;
    }

    const enabled =
        !audioTracks[0].enabled;

    audioTracks.forEach(function(track) {
        track.enabled = enabled;
    });

    micBtn.textContent =
        enabled ? "🎤" : "🔇";

    micBtn.title =
        enabled
            ? "Mute microphone"
            : "Unmute microphone";
}

function toggleCamera() {

    if (!localStream) {
        return;
    }

    const videoTracks =
        localStream.getVideoTracks();

    if (!videoTracks.length) {
        return;
    }

    const enabled =
        !videoTracks[0].enabled;

    videoTracks.forEach(function(track) {
        track.enabled = enabled;
    });

    cameraBtn.textContent =
        enabled ? "📷" : "🚫";

    cameraBtn.title =
        enabled
            ? "Turn camera off"
            : "Turn camera on";
}

function toggleSpeaker() {

    if (!remoteVideo) {
        return;
    }

    remoteVideo.muted =
        !remoteVideo.muted;

    speakerBtn.textContent =
        remoteVideo.muted
            ? "🔇"
            : "🔊";
}

async function endCall() {

    try {

        await sendSignal(
            "end",
            {
                ended_by: "customer"
            }
        );

    } catch (error) {

        console.log(
            "End signal error:",
            error
        );
    }

    stopCall(true);
}

function stopCall(sendEndSignal) {

    polling = false;

    if (localStream) {

        localStream
            .getTracks()
            .forEach(function(track) {

                track.stop();

            });

        localStream = null;
    }

    if (peerConnection) {

        peerConnection.close();

        peerConnection = null;
    }

    if (remoteVideo) {
        remoteVideo.srcObject = null;
    }

    if (localVideo) {
        localVideo.srcObject = null;
    }

    if (sendEndSignal) {

        updateStatus(
            "Call ended"
        );

    }

    setTimeout(function() {

        window.location.href =
            "messages.php?booking_id=" +
            encodeURIComponent(bookingId);

    }, 1000);
}

if (micBtn) {

    micBtn.addEventListener(
        "click",
        toggleMic
    );
}

if (cameraBtn) {

    cameraBtn.addEventListener(
        "click",
        toggleCamera
    );
}

if (speakerBtn) {

    speakerBtn.addEventListener(
        "click",
        toggleSpeaker
    );
}

if (endBtn) {

    endBtn.addEventListener(
        "click",
        endCall
    );
}

setInterval(
    getSignals,
    700
);

window.addEventListener(
    "beforeunload",
    function() {

        polling = false;

        if (localStream) {

            localStream
                .getTracks()
                .forEach(function(track) {

                    track.stop();

                });
        }

        if (peerConnection) {
            peerConnection.close();
        }

    }
);

window.addEventListener(
    "load",
    function() {

        startCall();

    }
);

</script>

</body>
</html>