<?php
$action = $_POST['action'] ?? '';
$filename = $_POST['filename'] ?? '';
$type = $_POST['type'] ?? '';

$pingFile = $_SERVER['DOCUMENT_ROOT'] . '/nekobox/ping.php';
$pingContent = file_get_contents($pingFile);

if ($action === 'set' && !empty($filename)) {
    if ($type === 'image') {
        $backgroundStyle = "\n<style>
            body {
                background-image: url('/nekobox/assets/Pictures/$filename');
                background-repeat: no-repeat;
                background-position: center center;
                background-attachment: fixed;
                background-size: cover;
            }
        </style>\n";
    } elseif ($type === 'video') {
        $backgroundStyle = "\n<style>
            body {
                background: transparent;
                position: relative;
                margin: 0;
                padding: 0;
                height: 100vh;
            }

            .video-background {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: contain;
                z-index: -1;
            }

            .control-toggle {
                position: absolute;
                top: 20px;
                left: 20px;
                padding: 10px 20px;
                background-color: #6f42c1;
                color: white;
                border: none;
                cursor: pointer;
                font-size: 16px;
                border-radius: 8px;
                transition: background 0.3s, transform 0.2s;
            }

            .control-toggle:hover {
                background: rgba(255, 255, 255, 0.3);
            }

            .popup {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.8);
                backdrop-filter: blur(10px);
                color: #333;
                padding: 20px;
                border-radius: 12px;
                z-index: 1000;
                text-align: center;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
                width: 280px;
            }

            .popup button {
                display: block;
                margin: 10px auto;
                padding: 12px 20px;
                font-size: 16px;
                cursor: pointer;
                border: none;
                border-radius: 8px;
                background-color: rgba(0, 0, 0, 0.1);
                color: #333;
                width: 100%;
                transition: background 0.3s, transform 0.2s;
            }

            .popup button:hover {
                background: rgba(0, 0, 0, 0.2);
                transform: scale(1.05);
            }

            .popup button:active {
                transform: scale(0.95);
            }
        </style>

        <video class=\"video-background\" autoplay loop id=\"background-video\">
            <source src='/nekobox/assets/Pictures/$filename' type='video/mp4'>
            Your browser does not support the video tag.
        </video>

        <button class=\"control-toggle\" onclick=\"togglePopup()\">🎛 Settings</button>
        <div class=\"popup\" id=\"popup\">
            <h3>🔧 Control Panel</h3>
            <button onclick=\"toggleAudio()\" id=\"audio-btn\">🔊 Toggle Audio</button>
            <button onclick=\"toggleObjectFit()\" id=\"object-fit-btn\">Toggle video display mode</button>
            <button onclick=\"toggleFullScreen()\" id=\"fullscreen-btn\">⛶ Toggle Full Screen</button>
            <button onclick=\"togglePopup()\">❌ Close</button>
        </div>\n";
    }

    if (strpos($pingContent, '<!-- BG_START -->') !== false && strpos($pingContent, '<!-- BG_END -->') !== false) {
        $pingContent = preg_replace('/<!-- BG_START -->.*<!-- BG_END -->/s', "<!-- BG_START -->$backgroundStyle<!-- BG_END -->", $pingContent);
    } else {
        $pingContent .= "\n<!-- BG_START -->$backgroundStyle<!-- BG_END -->\n";
    }

    file_put_contents($pingFile, $pingContent); 
    echo "Background successfully set";
} elseif ($action === 'remove') {
    $pingContent = preg_replace('/<!-- BG_START -->.*<!-- BG_END -->/s', '', $pingContent);
    file_put_contents($pingFile, $pingContent); 
    echo "Background successfully removed";
}
?>
