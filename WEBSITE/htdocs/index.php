<?php
session_start();

$chatFile = 'chat_history.json';
$defaultRoom = 'def';
$defaultUsername = 'Q';
$notificationFile = 'notifications.json';
$adminUsername = 'N30ㅤ'; // Admin username

// Function to sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to remove messages older than 1 hour
function cleanChatHistory($chat_history) {
    $current_time = time();
    return array_filter($chat_history, function($message) use ($current_time) {
        return ($current_time - $message['timestamp']) < 3600; // 1 hour
    });
}

// Function to read chat history from file
function readChatHistory() {
    global $chatFile;
    if (file_exists($chatFile)) {
        $chat_history = json_decode(file_get_contents($chatFile), true);
        return is_array($chat_history) ? $chat_history : [];
    }
    return [];
}

// Function to write chat history to file
function writeChatHistory($chat_history) {
    global $chatFile;
    file_put_contents($chatFile, json_encode($chat_history));
}

// Function to get ASCII art of an egg
function getEggAsciiArt() {
    return "
______________________________████______________________________
____________________________██░░░░██____________________________
__________________________██░░░░░░░░██__________________________
__________________________██░░░░░░░░██__________________________
________________________██░░░░░░░░░░░░██________________________
________________________██░░░░░░░░░░░░██________________________
________________________██░░░░░░░░░░░░██________________________
__________________________██░░░░░░░░██__________________________
____________________________████████____________________________
";
}

// Function to read notifications from file
function readNotifications() {
    global $notificationFile;
    if (file_exists($notificationFile)) {
        $notifications = json_decode(file_get_contents($notificationFile), true);
        return is_array($notifications) ? $notifications : [];
    }
    return [];
}

// Function to write notifications to file
function writeNotifications($notifications) {
    global $notificationFile;
    file_put_contents($notificationFile, json_encode($notifications));
}

// Admin command to delete messages
function deleteMessage(&$chat_history, $room, $message_code) {
    if (isset($chat_history[$room])) {
        foreach ($chat_history[$room] as $key => $message) {
            if ($message['code'] === $message_code) {
                unset($chat_history[$room][$key]);
                return true;
            }
        }
    }
    return false;
}

// Check if the user submitted a username and room to join the chat
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['room'])) {
    // Sanitize the input username and room
    $username = sanitizeInput($_POST['username']) ?: $defaultUsername;
    $room = sanitizeInput($_POST['room']) ?: $defaultRoom;

    // Handle special case for "|||N30|||"
    if ($username === '|||N30|||') {
        $username = 'N30ㅤ';
    }

    // Save username and room to session
    $_SESSION['username'] = $username;
    $_SESSION['room'] = $room;

    // Return success response
    echo json_encode(['status' => 'success', 'username' => $username, 'room' => $room]);
    exit;
}

// Check if the user submitted a message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message'])) {
    // Sanitize the input message
    $message = $_POST['message']; // Do not sanitize here to allow HTML
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : $defaultUsername;
    $room = isset($_POST['channel']) && !empty($_POST['channel']) ? sanitizeInput($_POST['channel']) : (isset($_SESSION['room']) ? $_SESSION['room'] : $defaultRoom);

    // Admin command to delete messages
    if ($username === $adminUsername && strpos($message, '/delete ') === 0) {
        $message_code = trim(substr($message, 8));
        $chat_history = readChatHistory();
        if (deleteMessage($chat_history, $room, $message_code)) {
            writeChatHistory($chat_history);
            echo json_encode(['status' => 'success', 'message_deleted' => $message_code]);
            exit;
        }
    }

    // Check if the message is the egg command
    if ($message === '/egg') {
        $message = getEggAsciiArt();
    }

    // Check if the message is a note command and the user is N30ㅤ
    if (strpos($message, '/note') === 0 && $username === 'N30ㅤ') {
        $noteMessage = trim(substr($message, 5));
        $notifications = readNotifications();
        $notifications[] = $noteMessage;
        writeNotifications($notifications);
        echo json_encode(['status' => 'success', 'note' => true]);
        exit;
    }

    // Read current chat history
    $chat_history = readChatHistory();

    // Initialize room if not exists
    if (!isset($chat_history[$room])) {
        $chat_history[$room] = [];
    }

    // Clean old messages from room chat history
    $chat_history[$room] = cleanChatHistory($chat_history[$room]);

    // Generate a unique code for the message
    $message_code = uniqid();

    // Add the new message to room chat history with username prefix
    $chat_history[$room][] = [
        'message' => $message,
        'timestamp' => time(),
        'code' => $message_code
    ];

    // Write updated chat history to file
    writeChatHistory($chat_history);

    // Return success response
    echo json_encode(['status' => 'success']);
    exit;
}

// Fetch chat updates if requested by client
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['fetch'])) {
    // Read current chat history
    $chat_history = readChatHistory();
    $room = isset($_SESSION['room']) ? $_SESSION['room'] : $defaultRoom;

    // Clean old messages from room chat history
    if (isset($chat_history[$room])) {
        $chat_history[$room] = cleanChatHistory($chat_history[$room]);
    } else {
        $chat_history[$room] = [];
    }

    // Write updated chat history to file
    writeChatHistory($chat_history);

    // Read notifications
    $notifications = readNotifications();

    header('Content-Type: application/json');
    echo json_encode(['chat' => $chat_history[$room], 'notifications' => $notifications]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IRC-Net</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #000;
            color: #0f0;
            margin: 0;
            padding: 0;
            overflow: hidden;
            position: relative;
        }
        .container {
            max-width: 800px;
            border: 1px solid #0f0;
            padding: 20px;
            background-color: rgba(17, 17, 17, 0.9);
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
            position: absolute;
            z-index: 10;
        }
        h1 {
            font-size: 28px;
            text-align: center;
            color: #0f0;
            text-shadow: 0 0 5px #0f0, 0 0 2px #0f0, 0 0 2px #0f0;
            margin: 0;
            padding: 0;
            cursor: move;
        }
        #chatArea {
            height: 300px;
            overflow-y: scroll;
            border: 1px solid #0f0;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #222;
        }
        form {
            margin-top: 10px;
        }
        form label {
            font-weight: bold;
        }
        input[type="text"], button, select {
            background-color: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px 10px;
            font-family: 'Courier New', Courier, monospace;
        }
        button {
            cursor: pointer;
        }
        button:hover {
            background-color: #0f0;
            color: #000;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: rgba(17, 17, 17, 0.9);
            border-top: 1px solid #0f0;
            padding: 10px;
            box-shadow: 0 -5px 10px rgba(0, 255, 0, 0.5);
            z-index: 20;
        }
        .footer input {
            width: calc(100% - 150px);
        }
        #backgroundIframe {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            z-index: 1;
        }
        #notifications {
            color: #f00;
            margin-bottom: 10px;
        }
        #systemConsole {
            position: fixed;
            top: 50px;
            left: 50px;
            width: 300px;
            height: 400px;
            background-color: rgba(0, 0, 0, 0.9);
            border: 1px solid #666;
            color: #999;
            display: none;
            z-index: 100;
            padding: 20px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        #systemConsole h2 {
            margin-top: 0;
            cursor: move;
        }
        #systemConsole input, #systemConsole select {
            width: 100%;
            margin-bottom: 10px;
        }
        #systemConsole textarea {
            width: 100%;
            height: 50%;
            background-color: #000;
            color: #999;
            border: 1px solid #666;
            padding: 10px;
            font-family: 'Courier New', Courier, monospace;
        }
        #systemConsole button {
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: #999;
            border: 1px solid #666;
            cursor: pointer;
        }
        #systemConsole button:hover {
            background-color: #666;
            color: #333;
        }
    </style>
</head>
<body>

<!-- Background Iframe -->
<iframe id="backgroundIframe" src="" frameborder="0"></iframe>

<div class="container" id="movableContainer">
    <h1 id="dragHandle">IRC-Net</h1>

    <!-- Join Form -->
    <form id="joinForm">
        <label for="username">Enter your alias:</label>
        <input type="text" id="username" name="username" autocomplete="off">
        <label for="room">Enter room:</label>
        <input type="text" id="room" name="room" autocomplete="off">
        <button type="submit">Join</button>
    </form>

    <!-- Notifications Area -->
    <div id="notifications"></div>

    <!-- Chat Area -->
    <div id="chatArea">
        <!-- Messages will be appended here dynamically -->
    </div>

    <!-- Message Form -->
    <form id="messageForm" style="margin-top: 10px; display: none;">
        <label for="message">Message:</label>
        <input type="text" id="message" name="message" autocomplete="off" required>
        <button type="submit">Send</button>
    </form>
</div>

<!-- Background URL Form as Footer -->
<div class="footer">
    <form id="backgroundForm">
        <label for="backgroundUrl">Background URL:</label>
        <input type="text" id="backgroundUrl" name="backgroundUrl" autocomplete="off" required>
        <button type="submit">Set Background</button>
    </form>
</div>

<!-- System Console -->
<div id="systemConsole">
    <h2 id="systemConsoleHandle">SYSTEM CONSOLE</h2>
    <input type="text" id="systemName" placeholder="Enter name" />
    <input type="color" id="systemColor" value="#999999" />
    <input type="text" id="systemChannel" placeholder="Enter channel or leave empty for all" />
    <textarea id="systemMessage"></textarea>
    <button id="sendSystemMessage">Send</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var username = '';
    var room = '';
    var clientChatHistory = [];
    var seenMessageCodes = new Set();

    // Konami code sequence
    var konamiCode = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight'];
    var konamiCodePosition = 0;

    // System console
    var systemConsole = document.getElementById('systemConsole');
    var systemMessageInput = document.getElementById('systemMessage');
    var systemNameInput = document.getElementById('systemName');
    var systemColorInput = document.getElementById('systemColor');
    var systemChannelInput = document.getElementById('systemChannel');
    var sendSystemMessageButton = document.getElementById('sendSystemMessage');

    // Request notification permission on page load
    if (Notification.permission !== 'granted') {
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                console.log('Notification permission granted.');
            } else {
                console.log('Notification permission denied.');
            }
        });
    }

    // Function to show a notification
    function showNotification(message) {
        if (Notification.permission === 'granted') {
            new Notification('New Notification', {
                body: message,
                icon: 'icon.png' // Optional: path to an icon image
            });
        }
    }

    // Join chat with chosen username and room
    document.getElementById('joinForm').addEventListener('submit', function(event) {
        event.preventDefault();
        username = document.getElementById('username').value.trim() || 'Q';
        room = document.getElementById('room').value.trim() || 'def';

        // Save username and room to session via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        username = response.username; // Update username from server response
                        room = response.room; // Update room from server response
                        document.getElementById('joinForm').style.display = 'none'; // Hide join form
                        document.getElementById('messageForm').style.display = 'block'; // Show message form
                        fetchChatUpdates(); // Start fetching chat updates
                    } else {
                        console.error('Error: ' + xhr.status);
                    }
                }
            }
        };
        xhr.send('username=' + encodeURIComponent(username) + '&room=' + encodeURIComponent(room));
    });

    // Submit message form via AJAX
    document.getElementById('messageForm').addEventListener('submit', function(event) {
        event.preventDefault();
        var message = document.getElementById('message').value.trim();

        // Send message to server via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'index.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        document.getElementById('message').value = ''; // Clear message input
                    }
                } else {
                    console.error('Error: ' + xhr.status);
                }
            }
        };
        xhr.send('message=' + encodeURIComponent(username + "//: " + message));
    });

    // Function to update chat area with new messages
    function updateChatArea(messages) {
        var chatArea = document.getElementById('chatArea');
        chatArea.innerHTML = ''; // Clear existing content

        messages.forEach(function(message) {
            var messageElement = document.createElement('div');
            messageElement.style.marginBottom = '5px';
            messageElement.innerHTML = message.message; // Use innerHTML to render HTML content

            chatArea.appendChild(messageElement);
        });

        // Scroll to bottom of chat area
        chatArea.scrollTop = chatArea.scrollHeight;
    }

    // Function to update notifications area with new notifications
    function updateNotificationsArea(notifications) {
        var notificationsArea = document.getElementById('notifications');
        notificationsArea.innerHTML = ''; // Clear existing content

        notifications.forEach(function(notification) {
            var notificationElement = document.createElement('div');
            notificationElement.style.marginBottom = '5px';
            notificationElement.innerHTML = "<span style='color: red;'>" + notification + "</span>";
            notificationsArea.appendChild(notificationElement);
            showNotification(notification); // Show browser notification
        });
    }

    // Function to fetch chat updates
    function fetchChatUpdates() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'index.php?fetch=true', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    var newMessages = response.chat.filter(function(message) {
                        return !seenMessageCodes.has(message.code);
                    });
                    newMessages.forEach(function(message) {
                        seenMessageCodes.add(message.code);
                        clientChatHistory.push(message);
                    });
                    updateChatArea(clientChatHistory); // Update chat area with new messages
                    updateNotificationsArea(response.notifications); // Update notifications area with new notifications
                } else {
                    console.error('Error: ' + xhr.status);
                }

                // Continue fetching updates
                setTimeout(fetchChatUpdates, 1000); // Poll every second (adjust as needed)
            }
        };
        xhr.send();
    }

    // Handle background URL form submission
    document.getElementById('backgroundForm').addEventListener('submit', function(event) {
        event.preventDefault();
        var backgroundUrl = document.getElementById('backgroundUrl').value.trim();
        document.getElementById('backgroundIframe').src = backgroundUrl;
        document.getElementById('backgroundUrl').value = ''; // Clear input field
    });

    // Konami code detection
    document.addEventListener('keydown', function(event) {
        if (event.key === konamiCode[konamiCodePosition]) {
            konamiCodePosition++;
            if (konamiCodePosition === konamiCode.length) {
                konamiCodePosition = 0;
                systemConsole.style.display = 'block';
            }
        } else {
            konamiCodePosition = 0;
        }
    });

    // Send system message
    sendSystemMessageButton.addEventListener('click', function() {
        var systemMessage = systemMessageInput.value.trim();
        var systemName = systemNameInput.value.trim() || '[SYSTEM]';
        var systemColor = systemColorInput.value;
        var systemChannel = systemChannelInput.value.trim();

        if (systemMessage) {
            var message = `<span style="color: ${systemColor};">${systemName}</span>: ${systemMessage}`;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            systemMessageInput.value = ''; // Clear system message input
                        }
                    } else {
                        console.error('Error: ' + xhr.status);
                    }
                }
            };
            xhr.send('message=' + encodeURIComponent(message) + '&channel=' + encodeURIComponent(systemChannel));
        }
    });

    // Make system console movable
    var isDraggingConsole = false;
    var startConsoleX, startConsoleY, initialConsoleX, initialConsoleY;
    var consoleHandle = document.getElementById('systemConsoleHandle');

    consoleHandle.addEventListener('mousedown', function(e) {
        isDraggingConsole = true;
        startConsoleX = e.clientX;
        startConsoleY = e.clientY;
        initialConsoleX = systemConsole.offsetLeft;
        initialConsoleY = systemConsole.offsetTop;
        document.addEventListener('mousemove', onConsoleMove);
        document.addEventListener('mouseup', onConsoleUp);
    });

    function onConsoleMove(e) {
        if (!isDraggingConsole) return;
        var dx = e.clientX - startConsoleX;
        var dy = e.clientY - startConsoleY;
        systemConsole.style.left = initialConsoleX + dx + 'px';
        systemConsole.style.top = initialConsoleY + dy + 'px';
    }

    function onConsoleUp() {
        isDraggingConsole = false;
        document.removeEventListener('mousemove', onConsoleMove);
        document.removeEventListener('mouseup', onConsoleUp);
    }

    // Make main container draggable
    var container = document.getElementById('movableContainer');
    var dragHandle = document.getElementById('dragHandle');
    var isDragging = false;
    var startX, startY, initialX, initialY;

    dragHandle.addEventListener('mousedown', function(e) {
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        initialX = container.offsetLeft;
        initialY = container.offsetTop;
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });

    function onMove(e) {
        if (!isDragging) return;
        var dx = e.clientX - startX;
        var dy = e.clientY - startY;
        container.style.left = initialX + dx + 'px';
        container.style.top = initialY + dy + 'px';
    }

    function onUp() {
        isDragging = false;
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    }
});
</script>

</body>
</html>
