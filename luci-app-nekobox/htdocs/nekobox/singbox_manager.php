<?php
ob_start();
include './cfg.php';
$uploadDir = '/www/nekobox/proxy/';
$configDir = '/etc/neko/config/';

ini_set('memory_limit', '256M');

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['fileInput'])) {
        $file = $_FILES['fileInput'];
        $uploadFilePath = $uploadDir . basename($file['name']);

        if ($file['error'] === UPLOAD_ERR_OK) {
            if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
                echo 'File upload successful: ' . htmlspecialchars(basename($file['name']));
            } else {
                echo 'File upload failed!';
            }
        } else {
            echo 'Upload error: ' . $file['error'];
        }
    }

    if (isset($_FILES['configFileInput'])) {
        $file = $_FILES['configFileInput'];
        $uploadFilePath = $configDir . basename($file['name']);

        if ($file['error'] === UPLOAD_ERR_OK) {
            if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
                echo 'Configuration file upload successful: ' . htmlspecialchars(basename($file['name']));
            } else {
                echo 'Configuration file upload failed!';
            }
        } else {
            echo 'Upload error: ' . $file['error'];
        }
    }

    if (isset($_POST['deleteFile'])) {
        $fileToDelete = $uploadDir . basename($_POST['deleteFile']);
        if (file_exists($fileToDelete) && unlink($fileToDelete)) {
            echo 'File deletion successful: ' . htmlspecialchars(basename($_POST['deleteFile']));
        } else {
            echo 'File deletion failed!';
        }
    }

    if (isset($_POST['deleteConfigFile'])) {
        $fileToDelete = $configDir . basename($_POST['deleteConfigFile']);
        if (file_exists($fileToDelete) && unlink($fileToDelete)) {
            echo 'Configuration file deletion successful: ' . htmlspecialchars(basename($_POST['deleteConfigFile']));
        } else {
            echo 'Configuration file deletion failed!';
        }
    }

    if (isset($_POST['oldFileName'], $_POST['newFileName'], $_POST['fileType'])) {
        $oldFileName = basename($_POST['oldFileName']);
        $newFileName = basename($_POST['newFileName']);

        if ($_POST['fileType'] === 'proxy') {
            $oldFilePath = $uploadDir . $oldFileName;
            $newFilePath = $uploadDir . $newFileName;
        } elseif ($_POST['fileType'] === 'config') {
            $oldFilePath = $configDir . $oldFileName;
            $newFilePath = $configDir . $newFileName;
        } else {
            echo 'Invalid file type';
            exit;
        }

        if (file_exists($oldFilePath) && !file_exists($newFilePath)) {
            if (rename($oldFilePath, $newFilePath)) {
                echo 'File rename successful: ' . htmlspecialchars($oldFileName) . ' -> ' . htmlspecialchars($newFileName);
            } else {
                echo 'File rename failed!';
            }
        } else {
            echo 'File rename failed, file does not exist or new file name already exists.';
        }
    }

    if (isset($_POST['editFile']) && isset($_POST['fileType'])) {
        $fileToEdit = ($_POST['fileType'] === 'proxy') ? $uploadDir . basename($_POST['editFile']) : $configDir . basename($_POST['editFile']);
        $fileContent = '';
        $editingFileName = htmlspecialchars($_POST['editFile']);

        if (file_exists($fileToEdit)) {
            $handle = fopen($fileToEdit, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $fileContent .= htmlspecialchars($line);
                }
                fclose($handle);
            } else {
                echo 'Unable to open file';
            }
        }
    }

    if (isset($_POST['saveContent'], $_POST['fileName'], $_POST['fileType'])) {
        $fileToSave = ($_POST['fileType'] === 'proxy') ? $uploadDir . basename($_POST['fileName']) : $configDir . basename($_POST['fileName']);
        $contentToSave = $_POST['saveContent'];
        file_put_contents($fileToSave, $contentToSave);
        echo '<p>File content updated: ' . htmlspecialchars(basename($fileToSave)) . '</p>';
    }

    if (isset($_GET['customFile'])) {
        $customDir = rtrim($_GET['customDir'], '/') . '/';
        $customFilePath = $customDir . basename($_GET['customFile']);
        if (file_exists($customFilePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($customFilePath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($customFilePath));
            readfile($customFilePath);
            exit;
        } else {
            echo 'File does not exist!';
        }
    }
}

function formatFileModificationTime($filePath) {
    if (file_exists($filePath)) {
        $fileModTime = filemtime($filePath);
        return date('Y-m-d H:i:s', $fileModTime);
    } else {
        return 'File does not exist';
    }
}

$proxyFiles = scandir($uploadDir);
$configFiles = scandir($configDir);

if ($proxyFiles !== false) {
    $proxyFiles = array_diff($proxyFiles, array('.', '..'));
} else {
    $proxyFiles = []; 
}

if ($configFiles !== false) {
    $configFiles = array_diff($configFiles, array('.', '..'));
} else {
    $configFiles = []; 
}

function formatSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return round($size, 2) . ' ' . $units[$unit];
}
?>

<?php
$subscriptionPath = '/www/nekobox/proxy/';
$subscriptionFile = $subscriptionPath . 'subscriptions.json';
$subscriptions = [];
while (ob_get_level() > 0) {
    ob_end_flush();
}

function outputMessage($message) {
    if (!isset($_SESSION['update_messages'])) {
        $_SESSION['update_messages'] = array();
    }

    if (empty($_SESSION['update_messages'])) {
        $_SESSION['update_messages'][] = '<div class="text-warning" style="margin-bottom: 8px;"><strong>⚠️ Note:</strong> The current configuration file must be used with the <strong>Puernya</strong> kernel and does not support other kernels!</div>';
    }
    $_SESSION['update_messages'][] = $message;
}

if (!file_exists($subscriptionPath)) {
    mkdir($subscriptionPath, 0755, true);
}

if (!file_exists($subscriptionFile)) {
    file_put_contents($subscriptionFile, json_encode([]));
}

$subscriptions = json_decode(file_get_contents($subscriptionFile), true);
if (!$subscriptions) {
    for ($i = 0; $i < 3; $i++) {
        $subscriptions[$i] = [
            'url' => '',
            'file_name' => "subscription_{$i}.yaml",
        ];
    }
}
if (isset($_POST['saveSubscription'])) {
    $index = intval($_POST['index']);
    if ($index >= 0 && $index < 3) {
        $url = $_POST['subscription_url'] ?? '';
        $customFileName = $_POST['custom_file_name'] ?? "subscription_{$index}.yaml";
        $subscriptions[$index]['url'] = $url;
        $subscriptions[$index]['file_name'] = $customFileName;
        
        if (!empty($url)) {
            $finalPath = $subscriptionPath . $customFileName;
            $command = sprintf("curl -fsSL -o %s %s", 
                escapeshellarg($finalPath), 
                escapeshellarg($url)
            );
            
            exec($command . ' 2>&1', $output, $return_var);
            
            if ($return_var === 0) {
               outputMessage("Subscription link {$url} updated successfully! File saved to: {$finalPath}");
            } else {
                outputMessage("Configuration update failed! Error message: " . implode("\n", $output));
            }
        } else {
            outputMessage("The " . ($index + 1) . "th subscription link is empty!");
        }
        
        file_put_contents($subscriptionFile, json_encode($subscriptions));
    }
}
$updateCompleted = isset($_POST['saveSubscription']); 
?>

<?php
$subscriptionPath = '/etc/neko/config/';
$dataFile = $subscriptionPath . 'subscription_data.json';

$message = "";
$defaultSubscriptions = [
    [
        'url' => '',
        'file_name' => 'config.json',
    ],
    [
        'url' => '',
        'file_name' => '',
    ],
    [
        'url' => '',
        'file_name' => '',
    ]
];

if (!file_exists($subscriptionPath)) {
    mkdir($subscriptionPath, 0755, true);
}

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(['subscriptions' => $defaultSubscriptions], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$subscriptionData = json_decode(file_get_contents($dataFile), true);

if (!isset($subscriptionData['subscriptions']) || !is_array($subscriptionData['subscriptions'])) {
    $subscriptionData['subscriptions'] = $defaultSubscriptions;
}

if (isset($_POST['update_index'])) {
    $index = intval($_POST['update_index']);
    $subscriptionUrl = $_POST["subscription_url_$index"] ?? '';
    $customFileName = ($_POST["custom_file_name_$index"] ?? '') ?: 'config.json';

    if ($index < 0 || $index >= count($subscriptionData['subscriptions'])) {
        $message = "Invalid subscription index!";
    } elseif (empty($subscriptionUrl)) {
        $message = "The link for subscription $index is empty!";
    } else {
        $subscriptionData['subscriptions'][$index]['url'] = $subscriptionUrl;
        $subscriptionData['subscriptions'][$index]['file_name'] = $customFileName;
        $finalPath = $subscriptionPath . $customFileName;

        $originalContent = file_exists($finalPath) ? file_get_contents($finalPath) : '';

        $ch = curl_init($subscriptionUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $fileContent = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($fileContent === false) {
            $message = "Unable to download file for subscription $index. cURL error information: " . $error;
        } else {
            $fileContent = str_replace("\xEF\xBB\xBF", '', $fileContent);

            $parsedData = json_decode($fileContent, true);
            if ($parsedData === null && json_last_error() !== JSON_ERROR_NONE) {
                file_put_contents($finalPath, $originalContent);
                $message = "Failed to parse JSON data for subscription $index! Error information: " . json_last_error_msg();
            } else {
                if (isset($parsedData['inbounds'])) {
                    $newInbounds = [];

                    foreach ($parsedData['inbounds'] as $inbound) {
                        if (isset($inbound['type']) && $inbound['type'] === 'mixed' && $inbound['tag'] === 'mixed-in') {
                            $newInbounds[] = $inbound;
                        } elseif (isset($inbound['type']) && $inbound['type'] === 'tun') {
                            continue;
                        }
                    }

                    $newInbounds[] = [
                      "tag" => "tun",
                      "type" => "tun",
                      "inet4_address" => "172.19.0.0/30",
                      "inet6_address" => "fdfe:dcba:9876::0/126",
                      "stack" => "system",
                      "auto_route" => true,
                      "strict_route" => true,
                      "sniff" => true,
                      "platform" => [
                        "http_proxy" => [
                          "enabled" => true,
                          "server" => "0.0.0.0",
                          "server_port" => 7890
                        ]
                      ]
                    ];

                    $newInbounds[] = [
                      "tag" => "mixed",
                      "type" => "mixed",
                      "listen" => "0.0.0.0",
                      "listen_port" => 7890,
                      "sniff" => true
                    ];

                    $parsedData['inbounds'] = $newInbounds;
                }

                if (isset($parsedData['experimental']['clash_api'])) {
                    $parsedData['experimental']['clash_api'] = [
                        "external_ui" => "/etc/neko/ui/",
                        "external_controller" => "0.0.0.0:9090",
                        "secret" => "Akun"
                    ];
                }

                $fileContent = json_encode($parsedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if (file_put_contents($finalPath, $fileContent) === false) {
                  $message = "Unable to save the file for subscription $index to: $finalPath";
                } else {
                  $message = "Subscription $index updated successfully! File saved to: {$finalPath}, and JSON data parsed and replaced successfully.";
                }
            }
        }

        file_put_contents($dataFile, json_encode($subscriptionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
?>

<?php
$url = "https://github.com/Thaolga/neko/releases/download/1.2.0/nekoclash.zip";
$zipFile = "/tmp/nekoclash.zip";
$extractPath = "/www/nekobox";
$logFile = "/tmp/update_log.txt";

function logMessage($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function downloadFile($url, $path) {
    $fp = fopen($path, 'w+');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    logMessage("File downloaded successfully, saved to: $path");
}

function unzipFile($zipFile, $extractPath) {
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $filePath = $extractPath . '/' . preg_replace('/^nekoclash\//', '', $filename);

            if (substr($filename, -1) == '/') {
                if (!is_dir($filePath)) {
                    mkdir($filePath, 0755, true);
                }
            } else {
                copy("zip://".$zipFile."#".$filename, $filePath);
            }
        }

        $zip->close();
        logMessage("File extracted successfully");
        return true;
    } else {
        return false;
    }
}

if (isset($_POST['update'])) {
    downloadFile($url, $zipFile);
    
    if (unzipFile($zipFile, $extractPath)) {
        echo "Rules set updated successfully!";
        logMessage("Rules set updated successfully");
    } else {
        echo "Extraction failed!";
        logMessage("Extraction failed");
    }
}
?>

<!doctype html>
<html lang="en" data-bs-theme="<?php echo substr($neko_theme, 0, -4) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sing-box - Neko</title>
    <link rel="icon" href="./assets/img/nekobox.png">
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="./assets/css/custom.css" rel="stylesheet">
    <link href="./assets/theme/<?php echo $neko_theme ?>" rel="stylesheet">
    <script src="./assets/js/feather.min.js"></script>
    <script src="./assets/js/jquery-2.1.3.min.js"></script>
    <script src="./assets/js/neko.js"></script>
</head>
<body>
<div class="position-fixed w-100 d-flex justify-content-center" style="top: 20px; z-index: 1050">
    <div id="updateAlert" class="alert alert-success alert-dismissible fade" role="alert" style="display: none; min-width: 300px; max-width: 600px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
        <div class="d-flex align-items-center mb-2">
            <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
            <strong>Update Complete</strong>
        </div>
        <div id="updateMessages" class="small" style="word-break: break-all;">
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>
</div>

<div class="position-fixed w-100 d-flex justify-content-center" style="top: 60px; z-index: 1050">
    <div id="updateAlertSub" class="alert alert-success alert-dismissible fade" role="alert" style="display: none; min-width: 300px; max-width: 600px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
        <div class="d-flex align-items-center mb-2">
            <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
            <strong>Update Complete</strong>
        </div>
        <div id="updateMessagesSub" class="small" style="word-break: break-all;">
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>
</div>

<script>
function showUpdateAlert() {
    const alert = $('#updateAlert');
    const messages = <?php echo json_encode($_SESSION['update_messages'] ?? []); ?>;
    
    if (messages.length > 0) {
        const messagesHtml = messages.map(msg => `<div>${msg}</div>`).join('');
        $('#updateMessages').html(messagesHtml);
    }
    
    alert.show().addClass('show');
    
    setTimeout(function() {
        alert.removeClass('show');
        setTimeout(function() {
            alert.hide();
            $('#updateMessages').html('');
        }, 150);
    }, 18000); 
}

function showUpdateAlertSub(message) {
    const alert = $('#updateAlertSub');
    $('#updateMessagesSub').html(`<div>${message}</div>`);
    alert.show().addClass('show');
    
    setTimeout(function() {
        alert.removeClass('show');
        setTimeout(function() {
            alert.hide();
            $('#updateMessagesSub').html('');
        }, 150);
    }, 18000); 
}

<?php if ($updateCompleted): ?>
    $(document).ready(function() {
        showUpdateAlert();
    });
<?php endif; ?>

<?php if ($message): ?>
    $(document).ready(function() {
        showUpdateAlertSub(`<?php echo str_replace(["\r", "\n"], '', addslashes($message)); ?>`);
    });
<?php endif; ?>
</script>

<style>
#updateAlert .close {
    color: white;
    opacity: 0.8;
    text-shadow: none;
    padding: 0;
    margin: 0;
    position: absolute;
    right: 10px;
    top: 10px;
    font-size: 1.2rem;
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.2);
    transition: all 0.2s ease;
}

#updateAlert .close:hover {
    opacity: 1;
    background-color: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

#updateAlert .close span {
    position: relative;
    top: -1px;
}

@media (max-width: 767px) {
    .row a {
        font-size: 9px; 
    }
}

.table-responsive {
    width: 100%;
}

@media (max-width: 767px) {
    .table th,
    .table td {
        padding: 6px 8px; 
        font-size: 14px; 
    }

    .table th:nth-child(1), .table td:nth-child(1) {
        width: 10%; 
    }
    .table th:nth-child(2), .table td:nth-child(2) {
        width: 20%; 
    }
    .table th:nth-child(3), .table td:nth-child(3) {
        width: 25%; 
    }
    .table th:nth-child(4), .table td:nth-child(4) {
        width: 45%; 
        white-space: nowrap;
    }

    .btn-group {
        display: flex;
        flex-wrap: wrap; 
        justify-content: space-between; 
    }

    .btn-group .btn {
        flex: 1 1 22%; 
        margin-bottom: 5px; 
        margin-right: 5px; 
        text-align: center; 
        font-size: 9px; 
    }
</style>
<div class="container-sm container-bg callout border border-3 rounded-4 col-11">
    <div class="row">
        <a href="./index.php" class="col btn btn-lg">🏠 Home</a>
        <a href="./mihomo_manager.php" class="col btn btn-lg">📂 Mihomo</a>
        <a href="./singbox_manager.php" class="col btn btn-lg">🗂️ Sing-box</a>
        <a href="./box.php" class="col btn btn-lg">💹 Template</a>
        <a href="./filekit.php" class="col btn btn-lg">📦 File Assistant</a>
    </div>
    <div class="text-center">
        <h1 style="margin-top: 40px; margin-bottom: 20px;">Mihomo File Manager</h1>
        <div class="table-wrapper">
            <h5>Proxy File Management</h5>
<style>
    .btn-group {
        display: flex;
        gap: 10px; 
        justify-content: center; 
    }
    .btn {
        margin: 0; 
    }

    .table-dark {
        background-color: #6f42c1; 
        color: white; 
    }
    .table-dark th, .table-dark td {
        background-color: #5a32a3; 
    }
</style>
<div class="container">
    <table class="table table-striped table-bordered  text-center">
        <thead class="thead-dark">
            <tr>
                <th style="width: 30%;">File Name</th>
                <th style="width: 10%;">Size</th>
                <th style="width: 20%;">Modification Time</th>
                <th style="width: 40%;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($proxyFiles as $file): ?>
                <?php $filePath = $uploadDir . $file; ?>
                <tr>
                    <td class="align-middle"><a href="download.php?file=<?php echo urlencode($file); ?>"><?php echo htmlspecialchars($file); ?></a></td>
                    <td class="align-middle"><?php echo file_exists($filePath) ? formatSize(filesize($filePath)) : 'File not found'; ?></td>
                    <td class="align-middle"><?php echo htmlspecialchars(date('Y-m-d H:i:s', filemtime($filePath))); ?></td>
                    <td class="action-column">
                        <div class="btn-group">
                            <form action="" method="post" class="d-inline">
                                <input type="hidden" name="deleteFile" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this file?');"><i>🗑️</i> Delete</button>
                            </form>
                            <form action="" method="post" class="d-inline">
                                <input type="hidden" name="editFile" value="<?php echo htmlspecialchars($file); ?>">
                                <input type="hidden" name="fileType" value="proxy">
                                <button type="button" class="btn btn-success btn-sm btn-rename" data-toggle="modal" data-target="#renameModal" data-filename="<?php echo htmlspecialchars($file); ?>" data-filetype="proxy"><i>✏️</i> Rename</button>
                            </form>
                            <form action="" method="post" class="d-inline">
                                <input type="hidden" name="editFile" value="<?php echo htmlspecialchars($file); ?>">
                                <input type="hidden" name="fileType" value="proxy"> 
                                <button type="submit" class="btn btn-warning btn-sm"><i>📝</i> Edit</button>
                            </form>
                            <form action="" method="post" enctype="multipart/form-data" class="form-inline d-inline upload-btn">
                                <input type="file" name="fileInput" class="form-control-file" required id="fileInput-<?php echo htmlspecialchars($file); ?>" style="display: none;" onchange="this.form.submit()">
                                <button type="button" class="btn btn-info btn-sm" onclick="document.getElementById('fileInput-<?php echo htmlspecialchars($file); ?>').click();"><i>📤</i> Upload</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

        <div class="modal fade" id="renameModal" tabindex="-1" role="dialog" aria-labelledby="renameModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="renameModalLabel">Rename File</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="renameForm" action="" method="post">
                            <input type="hidden" name="oldFileName" id="oldFileName">
                            <input type="hidden" name="fileType" id="fileType">
                            <div class="form-group">
                                <label for="newFileName">Rename File</label>
                                <input type="text" class="form-control" id="newFileName" name="newFileName" required>
                            </div>
                            <p>Are you sure you want to rename this file?</p>
                            <div class="form-group text-right">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Confirm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<div class="container">
    <h5>Configuration File Management</h5>
    <table class="table table-striped table-bordered text-center">
        <thead class="thead-dark">
            <tr>
                <th style="width: 30%;">File Name</th>
                <th style="width: 10%;">Size</th>
                <th style="width: 20%;">Modification Time</th>
                <th style="width: 40%;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($configFiles as $file): ?>
                <?php $filePath = $configDir . $file; ?>
                <tr>
                    <td class="align-middle"><a href="download.php?file=<?php echo urlencode($file); ?>"><?php echo htmlspecialchars($file); ?></a></td>
                    <td class="align-middle"><?php echo file_exists($filePath) ? formatSize(filesize($filePath)) : 'File not found'; ?></td>
                    <td class="align-middle"><?php echo htmlspecialchars(date('Y-m-d H:i:s', filemtime($filePath))); ?></td>
                    <td>
                        <div class="btn-group">
                            <form action="" method="post" class="d-inline">
                                <input type="hidden" name="deleteConfigFile" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this file?');"><i>🗑️</i> Delete</button>
                            </form>
                            <form action="" method="post" class="d-inline">
                                <input type="hidden" name="editFile" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="button" class="btn btn-success btn-sm btn-rename" data-toggle="modal" data-target="#renameModal" data-filename="<?php echo htmlspecialchars($file); ?>" data-filetype="config"><i>✏️</i> Rename</button>
                            </form>
                            <form action="" method="post" class="d-inline">
                                <input type="hidden" name="editFile" value="<?php echo htmlspecialchars($file); ?>">
                                <input type="hidden" name="fileType" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="submit" class="btn btn-warning btn-sm"><i>📝</i> Edit</button>
                            </form>
                            <form action="" method="post" enctype="multipart/form-data" class="form-inline d-inline upload-btn">
                                <input type="file" name="configFileInput" class="form-control-file" required id="fileInput-<?php echo htmlspecialchars($file); ?>" style="display: none;" onchange="this.form.submit()">
                                <button type="button" class="btn btn-info btn-sm" onclick="document.getElementById('fileInput-<?php echo htmlspecialchars($file); ?>').click();"><i>📤</i> Upload</button>  
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if (isset($fileContent)): ?>
    <?php if (isset($_POST['editFile'])): ?>
        <?php $fileToEdit = ($_POST['fileType'] === 'proxy') ? $uploadDir . basename($_POST['editFile']) : $configDir . basename($_POST['editFile']); ?>
        <h2 class="mt-5">Edit File: <?php echo $editingFileName; ?></h2>
        <p>Last Updated Date: <?php echo date('Y-m-d H:i:s', filemtime($fileToEdit)); ?></p>

        <div class="btn-group mb-3">
            <button type="button" class="btn btn-primary" id="toggleBasicEditor">Plain text editor</button>
            <button type="button" class="btn btn-warning" id="toggleAceEditor">Advanced editor</button>
            <button type="button" class="btn btn-info" id="toggleFullScreenEditor">Fullscreen editing</button>
        </div>

        <div class="editor-container">
            <form action="" method="post">
                <textarea name="saveContent" id="basicEditor" class="editor"><?php echo $fileContent; ?></textarea><br>

                <div id="aceEditorContainer" class="d-none resizable" style="height: 400px; width: 100%;"></div>

                <div id="fontSizeContainer" class="d-none mb-3">
                    <label for="fontSizeSelector">font size:</label>
                    <select id="fontSizeSelector" class="form-control" style="width: auto; display: inline-block;">
                        <option value="18px">18px</option>
                        <option value="20px">20px</option>
                        <option value="24px">24px</option>
                        <option value="26px">26px</option>
                    </select>
                </div>

                <input type="hidden" name="fileName" value="<?php echo htmlspecialchars($_POST['editFile']); ?>">
                <input type="hidden" name="fileType" value="<?php echo htmlspecialchars($_POST['fileType']); ?>">
                <button type="submit" class="btn btn-primary mt-2" onclick="syncEditorContent()"><i>💾</i> Save Content</button>
            </form>
            <button id="closeEditorButton" class="close-fullscreen" onclick="closeEditor()">X</button>
            <div id="aceEditorError" class="error-popup d-none">
                <span id="aceEditorErrorMessage"></span>
                <button id="closeErrorPopup">Close</button>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
        <h1 style="margin-top: 20px; margin-bottom: 20px;">Sing-box Subscription</h1>
    <style>
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <form method="post">
        <button type="submit" name="update">🔄 Update Rules</button>
    </form>
</body>
     </br>
        <?php if ($message): ?>
            <p><?php echo nl2br(htmlspecialchars($message)); ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="row">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card subscription-card p-2">
                            <div class="card-body p-2">
                                <h6 class="card-title text-primary">Subscription Link <?php echo $i + 1; ?></h6>
                                <div class="form-group mb-2">
                                    <input type="text" name="subscription_url_<?php echo $i; ?>" id="subscription_url_<?php echo $i; ?>" class="form-control form-control-sm white-text" placeholder="Subscription Link" value="<?php echo htmlspecialchars($subscriptionData['subscriptions'][$i]['url'] ?? ''); ?>">
                                </div>
                                <div class="form-group mb-2">
                                    <label for="custom_file_name_<?php echo $i; ?>" class="text-primary">Custom File Name <?php echo ($i === 0) ? '(Fixed as config.json)' : ''; ?></label>
                                    <input type="text" name="custom_file_name_<?php echo $i; ?>" id="custom_file_name_<?php echo $i; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($subscriptionData['subscriptions'][$i]['file_name'] ?? ($i === 0 ? 'config.json' : '')); ?>" <?php echo ($i === 0) ? 'readonly' : ''; ?> >
                                </div>
                                <button type="submit" name="update_index" value="<?php echo $i; ?>" class="btn btn-info btn-sm"><i>🔄</i> Update Subscription <?php echo $i + 1; ?></button>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </form>
<h2 class="text-success text-center mt-4 mb-4">Subscription Management ➤ Exclusively for P Core</h2>
<div class="help-text mb-3 text-start">
    <strong>1. For first-time Sing-box users, it's essential to update the core to version v1.10.0 or above. We recommend using P core. Make sure to set both outbound and inbound firewall rules to "accept" and enable them.</strong>
</div>
<div class="help-text mb-3 text-start">
    <strong>2. Note:</strong> The general template (<code>puernya.json</code>) supports a maximum of <strong>3</strong> subscription links. Please do not change the default name.
</div>
 <div class="help-text mb-3 text-start"> 
    <strong>3. Only Clash and Sing-box subscription formats are supported. Universal format is not supported.</strong>
    </div>
<div class="help-text mb-3 text-start"> 
    <strong>4. Save and Update:</strong> After filling in the information, please click the "Update Configuration" button to save.
</div>
        <div class="row">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Subscription Link <?php echo ($i + 1); ?></h5>
                            <form method="post">
                                <div class="input-group mb-3">
                                    <input type="text" name="subscription_url" id="subscriptionurl<?php echo $i; ?>" 
                                           value="<?php echo htmlspecialchars($subscriptions[$i]['url']); ?>" required 
                                           class="form-control" placeholder="Enter link">
                                    <input type="text" name="custom_file_name" id="custom_filename<?php echo $i; ?>" 
                                           value="<?php echo htmlspecialchars($subscriptions[$i]['file_name']); ?>" 
                                           class="form-control" placeholder="Custom file name">
                                    <input type="hidden" name="index" value="<?php echo $i; ?>">
                                    <button type="submit" name="saveSubscription" class="btn btn-success ml-2">
                                        <i>🔄</i> Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endfor; ?>
</div>

    <script>
        function showCompletionMessage() {
            var messageDiv = document.createElement('div');
            messageDiv.textContent = 'Update completed';
            messageDiv.style.position = 'fixed';
            messageDiv.style.top = '20px';
            messageDiv.style.left = '50%';
            messageDiv.style.transform = 'translateX(-50%)';
            messageDiv.style.padding = '10px 20px';
            messageDiv.style.backgroundColor = '#4CAF50';
            messageDiv.style.color = 'white';
            messageDiv.style.borderRadius = '5px';
            messageDiv.style.zIndex = '1000';
            document.body.appendChild(messageDiv);

            setTimeout(function() {
                messageDiv.style.transition = 'opacity 0.5s';
                messageDiv.style.opacity = '0';
                setTimeout(function() {
                    document.body.removeChild(messageDiv);
                }, 500);
            }, 3000);
        }

        <?php if ($updateCompleted): ?>
        window.onload = showCompletionMessage;
        <?php endif; ?>
    </script>
<script src="./assets/bootstrap/jquery-3.5.1.slim.min.js"></script>
<script src="./assets/bootstrap/popper.min.js"></script>
<script src="./assets/bootstrap/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js"></script>

<script>
    $('#renameModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); 
        var oldFileName = button.data('filename'); 
        var fileType = button.data('filetype');
        var modal = $(this);
        modal.find('#oldFileName').val(oldFileName); 
        modal.find('#fileType').val(fileType);
        modal.find('#newFileName').val(oldFileName); 
    });

    function closeEditor() {
        window.location.href = window.location.href; 
    }

    var aceEditor = ace.edit("aceEditorContainer");
    aceEditor.setTheme("ace/theme/monokai");
    aceEditor.session.setMode("ace/mode/json");
    aceEditor.session.setUseWorker(true);
    aceEditor.getSession().setUseWrapMode(true);

    function setDefaultFontSize() {
        var defaultFontSize = '20px';
        document.getElementById('basicEditor').style.fontSize = defaultFontSize;
        aceEditor.setFontSize(defaultFontSize);
    }

    document.addEventListener('DOMContentLoaded', setDefaultFontSize);

    aceEditor.setValue(document.getElementById('basicEditor').value);

    aceEditor.session.on('changeAnnotation', function() {
        var annotations = aceEditor.getSession().getAnnotations();
        if (annotations.length > 0) {
            var errorMessage = annotations[0].text;
            var errorLine = annotations[0].row + 1;
            showErrorPopup('JSON syntax error: line ' + errorLine + ': ' + errorMessage);
        } else {
            hideErrorPopup();
        }
    });

    document.getElementById('toggleBasicEditor').addEventListener('click', function() {
        document.getElementById('basicEditor').classList.remove('d-none');
        document.getElementById('aceEditorContainer').classList.add('d-none');
        document.getElementById('fontSizeContainer').classList.remove('d-none');
    });

    document.getElementById('toggleAceEditor').addEventListener('click', function() {
        document.getElementById('basicEditor').classList.add('d-none');
        document.getElementById('aceEditorContainer').classList.remove('d-none');
        document.getElementById('fontSizeContainer').classList.remove('d-none');
        aceEditor.setValue(document.getElementById('basicEditor').value);
    });

    document.getElementById('toggleFullScreenEditor').addEventListener('click', function() {
        var editorContainer = document.getElementById('aceEditorContainer');
        if (!document.fullscreenElement) {
            editorContainer.requestFullscreen().then(function() {
                aceEditor.resize();
                enableFullScreenMode();
            });
        } else {
            document.exitFullscreen().then(function() {
                aceEditor.resize();
                disableFullScreenMode();
            });
        }
    });

    function syncEditorContent() {
        if (!document.getElementById('basicEditor').classList.contains('d-none')) {
            aceEditor.setValue(document.getElementById('basicEditor').value);
        } else {
            document.getElementById('basicEditor').value = aceEditor.getValue();
        }
    }

    document.getElementById('fontSizeSelector').addEventListener('change', function() {
        var newFontSize = this.value;
        aceEditor.setFontSize(newFontSize);
        document.getElementById('basicEditor').style.fontSize = newFontSize;
    });

    function enableFullScreenMode() {
        document.getElementById('aceEditorContainer').classList.add('fullscreen');
        document.getElementById('aceEditorError').classList.add('fullscreen-popup');
        document.getElementById('fullscreenCancelButton').classList.remove('d-none');
    }

    function disableFullScreenMode() {
        document.getElementById('aceEditorContainer').classList.remove('fullscreen');
        document.getElementById('aceEditorError').classList.remove('fullscreen-popup');
        document.getElementById('fullscreenCancelButton').classList.add('d-none');
    }

    function showErrorPopup(message) {
        var errorPopup = document.getElementById('aceEditorError');
        var errorMessage = document.getElementById('aceEditorErrorMessage');
        errorMessage.innerText = message;
        errorPopup.classList.remove('d-none');
    }

    function hideErrorPopup() {
        var errorPopup = document.getElementById('aceEditorError');
        errorPopup.classList.add('d-none');
    }

    document.getElementById('closeErrorPopup').addEventListener('click', function() {
        hideErrorPopup();
    });

    (function() {
        const resizable = document.querySelector('.resizable');
        if (!resizable) return;

        const handle = document.createElement('div');
        handle.className = 'resize-handle';
        resizable.appendChild(handle);

        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        function onMouseMove(e) {
            resizable.style.width = e.clientX - resizable.getBoundingClientRect().left + 'px';
            resizable.style.height = e.clientY - resizable.getBoundingClientRect().top + 'px';
            aceEditor.resize();
        }

        function onMouseUp() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }
    })();
</script>

<style>
    .btn--warning {
        background-color: #ff9800;
        color: white !important; 
        border: none; 
        padding: 10px 20px; 
        border-radius: 5px; 
        cursor: pointer; 
        font-family: Arial, sans-serif; 
        font-weight: bold; 
    }

    .resizable {
        position: relative;
        overflow: hidden;
    }

    .resizable .resize-handle {
        width: 10px;
        height: 10px;
        background: #ddd;
        position: absolute;
        bottom: 0;
        right: 0;
        cursor: nwse-resize;
        z-index: 10;
    }

    .fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 9999;
        background-color: #1a1a1a;
    }

    #aceEditorError {
        color: red;
        font-weight: bold;
        margin-top: 10px;
    }

    .fullscreen-popup {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        z-index: 9999;
    }

    .close-fullscreen {
        position: fixed;
        top: 10px;
        right: 10px;
        z-index: 10000;
        background-color: red;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    #aceEditorError button {
        margin-top: 10px;
        padding: 5px 10px;
        background-color: #ff6666;
        border: none;
        cursor: pointer;
    }

    textarea.editor {
        font-size: 20px;
        width: 100%; 
        height: 400px; 
        resize: both; 
    }

    .ace_editor {
        font-size: 20px;
    }
</style>
</body>
