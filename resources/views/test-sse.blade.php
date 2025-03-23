<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSE Transport Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .panel {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background-color: #f9f9f9;
        }
        .panel h2 {
            margin-top: 0;
            font-size: 18px;
        }
        #messages {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #fff;
        }
        .message {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 3px;
        }
        .server {
            background-color: #e3f2fd;
            border-left: 3px solid #2196F3;
        }
        .client {
            background-color: #f0f4c3;
            border-left: 3px solid #cddc39;
            text-align: right;
        }
        .system {
            background-color: #ffebee;
            border-left: 3px solid #f44336;
            font-style: italic;
        }
        .controls {
            margin-top: 15px;
        }
        textarea {
            width: 100%;
            height: 80px;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            resize: vertical;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background-color: #45a049;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 3px;
            font-size: 13px;
            overflow-x: auto;
        }
        .time {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .connection-status {
            padding: 8px 12px;
            border-radius: 3px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .connected {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .disconnected {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
</head>
<body>
    <h1>SSE Transport Test Console</h1>
    
    <div class="connection-status disconnected" id="status">
        Not connected
    </div>
    
    <div class="container">
        <div class="panel">
            <h2>Server Events</h2>
            <div id="messages"></div>
            
            <div class="controls">
                <textarea id="message" placeholder="Type a message to send to the server..."></textarea>
                <button id="send">Send Message</button>
            </div>
        </div>
        
        <div class="panel">
            <h2>Connection Details</h2>
            <p><strong>Connection ID:</strong> <span id="connectionId">None</span></p>
            <p><strong>Connected at:</strong> <span id="connectedAt">-</span></p>
            <p><strong>Last heartbeat:</strong> <span id="lastHeartbeat">-</span></p>
            <p><strong>Total messages received:</strong> <span id="messageCount">0</span></p>
            
            <h3>Connection Controls</h3>
            <button id="connect">Connect to SSE</button>
            <button id="disconnect">Disconnect</button>
        </div>
    </div>
    
    <script>
        // Connection variables
        let eventSource = null;
        let connectionId = null;
        let messageCount = 0;
        let isConnected = false;
        
        // DOM elements
        const messagesEl = document.getElementById('messages');
        const statusEl = document.getElementById('status');
        const connectionIdEl = document.getElementById('connectionId');
        const connectedAtEl = document.getElementById('connectedAt');
        const lastHeartbeatEl = document.getElementById('lastHeartbeat');
        const messageCountEl = document.getElementById('messageCount');
        const messageInput = document.getElementById('message');
        
        // Connect to SSE
        document.getElementById('connect').addEventListener('click', function() {
            if (isConnected) {
                addMessage('Already connected', 'system');
                return;
            }
            
            connect();
        });
        
        // Disconnect from SSE
        document.getElementById('disconnect').addEventListener('click', function() {
            if (!isConnected) {
                addMessage('Not connected', 'system');
                return;
            }
            
            disconnect();
        });
        
        // Send message
        document.getElementById('send').addEventListener('click', function() {
            const message = messageInput.value.trim();
            if (!message) {
                return;
            }
            
            if (!isConnected || !connectionId) {
                addMessage('Cannot send message: not connected', 'system');
                return;
            }
            
            sendMessage(message);
            messageInput.value = '';
        });
        
        // Connect to SSE endpoint
        function connect() {
            addMessage('Connecting to SSE endpoint...', 'system');
            
            // Create EventSource for SSE connection
            eventSource = new EventSource('/test-sse');
            
            // Connection opened
            eventSource.addEventListener('open', function(e) {
                isConnected = true;
                addMessage('Connection established', 'system');
                updateStatus('Connected', true);
                connectedAtEl.textContent = new Date().toLocaleTimeString();
                
                // Extract connection ID from URL or cookies
                // In a real app, you would get this from the server response
                fetch('/test-sse/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ type: 'handshake' })
                })
                .then(response => response.json())
                .then(data => {
                    connectionId = data.connection_id || 'Unknown';
                    connectionIdEl.textContent = connectionId;
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
            
            // Connection error
            eventSource.addEventListener('error', function(e) {
                if (eventSource.readyState === EventSource.CLOSED) {
                    isConnected = false;
                    addMessage('Connection closed', 'system');
                    updateStatus('Disconnected', false);
                } else {
                    addMessage('Connection error', 'system');
                    updateStatus('Error', false);
                }
            });
            
            // Listen for SSE messages (default event)
            eventSource.addEventListener('message', function(e) {
                messageCount++;
                messageCountEl.textContent = messageCount;
                
                try {
                    const data = JSON.parse(e.data);
                    addMessage(`${data.message || data.type || 'Unknown message'}`, 'server', data);
                } catch (error) {
                    addMessage(e.data, 'server');
                }
            });
            
            // Listen for heartbeat comments (custom implementation)
            eventSource.addEventListener('heartbeat', function(e) {
                lastHeartbeatEl.textContent = new Date().toLocaleTimeString();
                console.log('Heartbeat received');
            });
        }
        
        // Disconnect from SSE
        function disconnect() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
                isConnected = false;
                addMessage('Disconnected from server', 'system');
                updateStatus('Disconnected', false);
            }
        }
        
        // Send message to server
        function sendMessage(message) {
            if (!connectionId) {
                addMessage('Connection ID not available', 'system');
                return;
            }
            
            // Add message to UI immediately
            addMessage(message, 'client');
            
            // Send to server
            fetch('/test-sse/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: message
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Message sending failed');
                }
                return response.json();
            })
            .then(data => {
                console.log('Message sent successfully:', data);
            })
            .catch(error => {
                addMessage(`Error sending message: ${error.message}`, 'system');
                console.error('Error:', error);
            });
        }
        
        // Add message to UI
        function addMessage(text, type, data = null) {
            const msgEl = document.createElement('div');
            msgEl.classList.add('message', type);
            
            // Format message based on type
            if (data) {
                let content = text;
                if (data.time) {
                    const timeEl = document.createElement('div');
                    timeEl.classList.add('time');
                    timeEl.textContent = new Date(data.time).toLocaleTimeString();
                    msgEl.appendChild(timeEl);
                }
                msgEl.innerHTML += content;
            } else {
                msgEl.textContent = text;
            }
            
            // Add timestamp for system messages
            if (type === 'system') {
                const timeEl = document.createElement('div');
                timeEl.classList.add('time');
                timeEl.textContent = new Date().toLocaleTimeString();
                msgEl.appendChild(timeEl);
            }
            
            messagesEl.appendChild(msgEl);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
        
        // Update connection status
        function updateStatus(text, isConnected) {
            statusEl.textContent = text;
            statusEl.className = 'connection-status ' + (isConnected ? 'connected' : 'disconnected');
        }
    </script>
</body>
</html>
