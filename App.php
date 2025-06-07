<?php
session_start(); // Memulai sesi di paling atas
require_once 'db_connect.php';

// Jika pengguna belum login (tidak ada user_id di session),
// arahkan ke halaman login/registrasi.
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php'); // Pastikan auth.php adalah halaman login/registrasi Anda
    exit;
}

// Ambil username dari session untuk ditampilkan
$current_username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Pengguna';
$user_id = $_SESSION['user_id'];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: auth.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    switch ($action) {
        case 'get_tasks':
            try {
                $stmt = $conn->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY deadline ASC");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("i", $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                
                $result = $stmt->get_result();
                $tasks = [];
                while ($row = $result->fetch_assoc()) {
                    // Convert date strings to proper format
                    if ($row['deadline']) {
                        $row['deadline'] = date('Y-m-d', strtotime($row['deadline']));
                    }
                    if ($row['completion_date']) {
                        $row['completion_date'] = date('Y-m-d H:i:s', strtotime($row['completion_date']));
                    }
                    $tasks[] = $row;
                }
                
                $response = [
                    'success' => true,
                    'tasks' => $tasks,
                    'debug' => [
                        'user_id' => $user_id,
                        'task_count' => count($tasks)
                    ]
                ];
            } catch (Exception $e) {
                $response = [
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage(),
                    'debug' => [
                        'user_id' => $user_id,
                        'error' => $e->getMessage()
                    ]
                ];
            }
            break;

        case 'add_task':
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $deadline = $_POST['deadline'] ?? null;
            $parent_id = $_POST['parent_id'] ?? null;

            $stmt = $conn->prepare("INSERT INTO tasks (user_id, name, description, deadline, parent_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $user_id, $name, $description, $deadline, $parent_id);
            
            if ($stmt->execute()) {
                $response = [
                    'success' => true,
                    'message' => 'Task added successfully',
                    'task_id' => $conn->insert_id
                ];
            } else {
                $response = ['success' => false, 'message' => 'Failed to add task'];
            }
            break;

        case 'update_task':
            $task_id = $_POST['task_id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $deadline = $_POST['deadline'] ?? null;

            $stmt = $conn->prepare("UPDATE tasks SET name = ?, description = ?, deadline = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sssii", $name, $description, $deadline, $task_id, $user_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Task updated successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update task'];
            }
            break;

        case 'delete_task':
            $task_id = $_POST['task_id'] ?? 0;
            
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $task_id, $user_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Task deleted successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete task'];
            }
            break;

        case 'complete_task':
            $task_id = $_POST['task_id'] ?? 0;
            
            $stmt = $conn->prepare("UPDATE tasks SET completed = 1, completion_date = NOW() WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $task_id, $user_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Task marked as completed'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to complete task'];
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi - Sistem Manajemen Proyek Mini</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #111827; 
            color: #d1d5db; 
        }
        .navbar-app {
            background-color: #3730a3; /* indigo-800 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .task-card {
            background-color: #1f2937; 
            border: 1px solid #4b5563; 
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(167, 139, 250, 0.1), 0 4px 6px -2px rgba(167, 139, 250, 0.05);
        }
        .chat-history::-webkit-scrollbar { width: 8px; }
        .chat-history::-webkit-scrollbar-track { background: #374151; border-radius: 10px; }
        .chat-history::-webkit-scrollbar-thumb { background: #6b7280; border-radius: 10px; }
        .chat-history::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); }
        .modal-content { background-color: #374151; margin: 10% auto; padding: 20px; border: 1px solid #4b5563; width: 90%; max-width: 500px; border-radius: 0.5rem; color: #d1d5db; }
        
        .status-menunggu { background-color: #22c55e; color: white; }
        .status-segera { background-color: #f59e0b; color: #1f2937; }
        .status-selesai { background-color: #60a5fa; color: white; }
        .status-terlambat { background-color: #ef4444; color: white; }

        .loader { border: 4px solid #4b5563; border-top: 4px solid #8b5cf6; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; }
        .button-loader { width: 16px; height: 16px; border-width: 2px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        input[type="text"], input[type="date"], textarea { background-color: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 0.375rem; padding: 0.5rem 0.75rem; transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
        input[type="text"]:focus, input[type="date"]:focus, textarea:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.3); }
        #chatInput { background-color: #1a202c; border: 1px solid #6b7280; color: #cbd5e1; }
        #chatInput:focus { border-color: #2dd4bf; box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.3); }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(0.8) brightness(1.2); }
        label { color: #a0aec0; font-weight: 500; }
    </style>
</head>
<body class="min-h-screen">
    <nav class="navbar-app sticky top-0 z-40">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="index.html" class="flex-shrink-0 text-white text-2xl font-bold"> Proyek<span class="text-purple-300">Mini</span> AI
                    </a>
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <span class="text-purple-200 px-3 py-2 rounded-md text-sm font-medium">
                            Halo, <?php echo $current_username; ?>!
                        </span>
                        <a href="?logout=1" class="text-gray-300 hover:bg-red-600 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                            Logout <i class="fas fa-sign-out-alt ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="md:hidden">
                    <button id="app-mobile-menu-button" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-300 hover:text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" aria-controls="app-mobile-menu" aria-expanded="false">
                        <span class="sr-only">Buka menu</span>
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="md:hidden hidden" id="app-mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <span class="text-purple-200 block px-3 py-2 rounded-md text-base font-medium">Halo, <?php echo $current_username; ?>!</span>
                <a href="?logout=1" class="text-gray-300 hover:bg-red-600 hover:text-white block px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">Logout</a>
                <a href="index.html" class="text-gray-300 hover:bg-purple-600 hover:text-white block px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">Kembali ke Beranda</a>
            </div>
        </div>
    </nav>

    <div class="container w-full mx-auto p-4 md:p-8">
        <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-purple-400">Sistem Manajemen Proyek Mini</h1>
            <p class="text-gray-400">Kelola proyek Anda dengan bantuan AI canggih!</p>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-1">
                <div class="bg-gray-700 p-6 rounded-lg shadow-xl space-y-8 border border-purple-600">
                    <div>
                        <h2 class="text-xl font-semibold mb-4 text-purple-300">Tambah Tugas Manual</h2>
                        <form id="manualTaskForm" class="space-y-4">
                            <div>
                                <label for="taskName">Nama Tugas</label>
                                <input type="text" id="taskName" name="taskName" required class="mt-1 block w-full sm:text-sm">
                            </div>
                            <div>
                                <label for="taskDescription">Deskripsi</label>
                                <textarea id="taskDescription" name="taskDescription" rows="3" class="mt-1 block w-full sm:text-sm"></textarea>
                            </div>
                            <div>
                                <label for="taskDeadline">Deadline</label>
                                <input type="date" id="taskDeadline" name="taskDeadline" required class="mt-1 block w-full sm:text-sm">
                            </div>
                            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-purple-500">
                                Tambah Manual
                            </button>
                        </form>
                    </div>

                    <hr class="border-gray-600">

                    <div>
                        <h2 class="text-xl font-semibold mb-4 text-purple-300">✨ Buat Rencana dengan AI Chatbot</h2>
                        <div id="chatContainer" class="space-y-4">
                            <div id="chatHistory" class="h-64 border border-gray-600 rounded-md p-3 overflow-y-auto chat-history bg-gray-800 space-y-2">
                                <div class="p-2 rounded-lg bg-purple-900 text-purple-100 text-sm self-start max-w-xs shadow">
                                    Halo! Beritahu saya tentang proyek Anda (misalnya "buatkan rencana untuk aplikasi X dengan deadline Y"), dan saya akan membantu membuatkan daftar tugasnya. Tanggal hari ini akan otomatis disertakan untuk AI.
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input type="text" id="chatInput" placeholder="Ketik perintah untuk AI..." class="flex-grow sm:text-sm">
                                <button id="sendChatButton" class="bg-teal-500 hover:bg-teal-600 text-white font-semibold p-2 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-teal-500 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg>
                                </button>
                            </div>
                             <div id="aiChatLoadingIndicator" class="hidden text-sm text-gray-400 flex items-center mt-2">
                                <div class="loader mr-2"></div>
                                AI sedang memproses...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <h2 class="text-2xl font-semibold mb-6 text-purple-300">Daftar Tugas Proyek</h2>
                <div id="taskList" class="space-y-4">
                    <p id="noTasksMessage" class="text-gray-400">Belum ada tugas. Tambahkan tugas secara manual atau gunakan AI chatbot.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <h3 class="text-lg font-medium leading-6 text-purple-300 mb-4">Edit Tugas</h3>
            <form id="editTaskForm" class="space-y-4">
                <input type="hidden" id="editTaskId">
                <div>
                    <label for="editTaskName">Nama Tugas</label>
                    <input type="text" id="editTaskName" required class="mt-1 block w-full sm:text-sm">
                </div>
                <div>
                    <label for="editTaskDescription">Deskripsi</label>
                    <textarea id="editTaskDescription" rows="3" class="mt-1 block w-full sm:text-sm"></textarea>
                </div>
                <div>
                    <label for="editTaskDeadline">Deadline</label>
                    <input type="date" id="editTaskDeadline" required class="mt-1 block w-full sm:text-sm">
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-700 focus:ring-purple-500 sm:col-start-2 sm:text-sm">
                        Simpan Perubahan
                    </button>
                    <button type="button" id="closeModalButton" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-500 shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-gray-200 hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-700 focus:ring-purple-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="messageBox" class="fixed bottom-5 right-5 bg-gray-900 text-white p-4 rounded-lg shadow-xl text-sm z-50 hidden transition-opacity duration-300 opacity-0 border border-purple-500">
        <p id="messageText"></p>
    </div>

<script>
    // --- State Management ---
    let tasks = []; 
    let nextId = 1; 

    // --- DOM Elements ---
    const manualTaskForm = document.getElementById('manualTaskForm');
    const taskListDiv = document.getElementById('taskList');
    const noTasksMessage = document.getElementById('noTasksMessage');

    const chatInput = document.getElementById('chatInput');
    const sendChatButton = document.getElementById('sendChatButton');
    const chatHistoryDiv = document.getElementById('chatHistory');
    const aiChatLoadingIndicator = document.getElementById('aiChatLoadingIndicator');

    const editTaskModal = document.getElementById('editTaskModal');
    const editTaskForm = document.getElementById('editTaskForm');
    const closeModalButton = document.getElementById('closeModalButton');
    const messageBox = document.getElementById('messageBox');
    const messageText = document.getElementById('messageText');

    // --- Utility Functions ---
    function showMessage(message, type = 'info') {
        messageText.textContent = message;
        messageBox.classList.remove('hidden', 'opacity-0');
        messageBox.classList.add('opacity-100');
        
        messageBox.classList.remove('bg-red-600', 'bg-green-600', 'bg-gray-900', 'border-purple-500', 'border-red-500', 'border-green-500');

        if (type === 'error') {
            messageBox.classList.add('bg-red-600', 'border-red-500');
        } else if (type === 'success') {
            messageBox.classList.add('bg-green-600', 'border-green-500');
        } else { 
            messageBox.classList.add('bg-gray-900', 'border-purple-500');
        }
        
        setTimeout(() => {
            messageBox.classList.remove('opacity-100');
            messageBox.classList.add('opacity-0');
            setTimeout(() => messageBox.classList.add('hidden'), 300);
        }, 3000);
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            const dateWithTime = new Date(dateString + "T00:00:00");
            if (isNaN(dateWithTime.getTime())) {
                 console.warn("Invalid date string for formatDate:", dateString);
                 return 'Tanggal Tidak Valid';
            }
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return dateWithTime.toLocaleDateString('id-ID', options);
        }
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('id-ID', options);
    }

    function getTaskStatus(deadline, completed) {
        if (completed) {
            return { text: 'Selesai', colorClass: 'status-selesai', borderColorClass: 'border-blue-400' };
        }
        if (!deadline) {
            return { text: 'Tanpa Deadline', colorClass: 'bg-gray-500 text-white', borderColorClass: 'border-gray-500' };
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0); 
        
        let deadlineDate;
        if (deadline.includes('T')) { 
            deadlineDate = new Date(deadline.split('T')[0]);
        } else {
            deadlineDate = new Date(deadline);
        }
        if (isNaN(deadlineDate.getTime())) {
            deadlineDate = new Date(deadline + "T00:00:00");
             if (isNaN(deadlineDate.getTime())) {
                console.warn("Invalid deadline for getTaskStatus:", deadline);
                return { text: 'Deadline Error', colorClass: 'bg-gray-600 text-white', borderColorClass: 'border-gray-600' };
            }
        }
        deadlineDate.setHours(0,0,0,0); 

        const diffTime = deadlineDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays < 0) {
            return { text: 'Terlambat', colorClass: 'status-terlambat', borderColorClass: 'border-red-500' };
        } else if (diffDays <= 2) {
            return { text: 'Segera Selesaikan', colorClass: 'status-segera', borderColorClass: 'border-amber-500' };
        } else { 
            return { text: 'Menunggu', colorClass: 'status-menunggu', borderColorClass: 'border-green-500' };
        }
    }

    // --- Task Rendering ---
    function renderTasks() {
        taskListDiv.innerHTML = ''; 
        if (!tasks || tasks.length === 0) {
            noTasksMessage.style.display = 'block';
            return;
        }
        noTasksMessage.style.display = 'none';

        tasks.forEach(task => {
            const status = getTaskStatus(task.deadline, task.completed);
            const taskCard = document.createElement('div');
            taskCard.className = `task-card bg-gray-700 p-4 rounded-lg shadow-lg border-l-4 ${status.borderColorClass}`;
            taskCard.setAttribute('data-task-id', task.id);

            const safeName = task.name ? task.name.replace(/</g, "&lt;").replace(/>/g, "&gt;") : "Tugas Tanpa Nama";
            const safeDescription = (task.description || 'Tidak ada deskripsi.').replace(/</g, "&lt;").replace(/>/g, "&gt;");

            taskCard.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-semibold text-purple-300 ${task.completed ? 'line-through' : ''}">${safeName}</h3>
                        <p class="text-sm text-gray-300 ${task.completed ? 'line-through' : ''}">${safeDescription}</p>
                        <p class="text-sm text-gray-400">Deadline: ${formatDate(task.deadline)}</p>
                    </div>
                    <span class="text-xs font-semibold px-2 py-1 rounded-full ${status.colorClass}">
                        ${status.text}
                    </span>
                </div>
                <div class="mt-4 flex space-x-2 justify-end items-center">
                    ${!task.completed ? `
                    <button data-task-id="${task.id}" class="suggest-subtasks-btn text-sm bg-indigo-500 hover:bg-indigo-600 text-white py-1 px-3 rounded-md shadow-sm flex items-center">
                        ✨ Sarankan Sub-Tugas
                        <span class="loader button-loader ml-2 hidden"></span>
                    </button>
                    <button class="complete-btn text-sm bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md shadow-sm">Selesai</button>
                    <button class="edit-btn text-sm bg-amber-500 hover:bg-amber-600 text-gray-800 py-1 px-3 rounded-md shadow-sm">Edit</button>
                    ` : ''}
                    <button class="delete-btn text-sm bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded-md shadow-sm">Hapus</button>
                </div>
            `;
            taskListDiv.appendChild(taskCard);
        });
        attachTaskActionListeners();
    }
    
    function attachTaskActionListeners() {
        document.querySelectorAll('.complete-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const taskId = parseInt(e.target.closest('.task-card').dataset.taskId);
                completeTask(taskId);
            });
        });
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const taskId = parseInt(e.target.closest('.task-card').dataset.taskId);
                openEditModal(taskId);
            });
        });
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const taskId = parseInt(e.target.closest('.task-card').dataset.taskId);
                deleteTask(taskId);
            });
        });
        document.querySelectorAll('.suggest-subtasks-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const taskId = parseInt(e.currentTarget.dataset.taskId);
                handleSuggestSubTasks(taskId, e.currentTarget);
            });
        });
    }

    // --- Task CRUD Operations ---
    async function fetchTasks() {
        try {
            const response = await fetch('App.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_tasks'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Fetched tasks:', data); // Debug log
            
            if (data.success) {
                tasks = data.tasks;
                renderTasks();
            } else {
                showMessage('Failed to fetch tasks: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error fetching tasks:', error);
            showMessage('Error fetching tasks: ' + error.message, 'error');
        }
    }

    async function addTask(name, description, deadline, fromAI = false, parentId = null) {
        try {
            const formData = new FormData();
            formData.append('action', 'add_task');
            formData.append('name', name);
            formData.append('description', description);
            formData.append('deadline', deadline);
            if (parentId) formData.append('parent_id', parentId);

            const response = await fetch('App.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                await fetchTasks(); // Refresh tasks after adding
                if (!fromAI) {
                    showMessage(`Task "${name}" added successfully.`, 'success');
                }
            } else {
                showMessage('Failed to add task: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error adding task:', error);
            showMessage('Error adding task', 'error');
        }
    }

    async function completeTask(taskId) {
        try {
            const formData = new FormData();
            formData.append('action', 'complete_task');
            formData.append('task_id', taskId);

            const response = await fetch('App.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                await fetchTasks(); // Refresh tasks after completing
                showMessage('Task marked as completed.', 'success');
            } else {
                showMessage('Failed to complete task: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error completing task:', error);
            showMessage('Error completing task', 'error');
        }
    }

    async function deleteTask(taskId) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete_task');
            formData.append('task_id', taskId);

            const response = await fetch('App.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                await fetchTasks(); // Refresh tasks after deleting
                showMessage('Task deleted successfully.', 'success');
            } else {
                showMessage('Failed to delete task: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error deleting task:', error);
            showMessage('Error deleting task', 'error');
        }
    }

    function openEditModal(taskId) {
        const task = tasks.find(t => t.id === taskId);
        if (task) {
            document.getElementById('editTaskId').value = task.id;
            document.getElementById('editTaskName').value = task.name; 
            document.getElementById('editTaskDescription').value = task.description;
            let deadlineForInput = '';
            if (task.deadline) {
                try {
                    const d = new Date(task.deadline + "T00:00:00"); 
                    if (!isNaN(d.getTime())) {
                        deadlineForInput = d.toISOString().split('T')[0];
                    }
                } catch(e) { console.error("Error parsing deadline for modal:", task.deadline, e); }
            }
            document.getElementById('editTaskDeadline').value = deadlineForInput;
            editTaskModal.style.display = 'block';
        }
    }

    function closeEditModal() {
        editTaskModal.style.display = 'none';
        editTaskForm.reset();
    }

    // --- Event Listeners ---
    manualTaskForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = e.target.taskName.value.trim();
        const description = e.target.taskDescription.value.trim();
        const deadline = e.target.taskDeadline.value; 

        if (name && deadline) {
            addTask(name, description, deadline);
            manualTaskForm.reset();
        } else {
            showMessage('Nama tugas dan deadline wajib diisi.', 'error');
        }
    });

    editTaskForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const id = parseInt(document.getElementById('editTaskId').value);
        const name = document.getElementById('editTaskName').value.trim();
        const description = document.getElementById('editTaskDescription').value.trim();
        const deadline = document.getElementById('editTaskDeadline').value; 

        const taskIndex = tasks.findIndex(t => t.id === id);
        if (taskIndex > -1 && name && deadline) {
            tasks[taskIndex] = { ...tasks[taskIndex], name, description, deadline }; 
            renderTasks(); 
            closeEditModal();
            showMessage(`Tugas "${name}" berhasil diperbarui.`, 'success');
            saveTasksToLocalStorage();
        } else {
             showMessage('Gagal memperbarui tugas. Nama dan deadline wajib diisi.', 'error');
        }
    });

    closeModalButton.addEventListener('click', closeEditModal);
    window.addEventListener('click', (event) => { 
        if (event.target == editTaskModal) {
            closeEditModal();
        }
    });

    // --- Chatbot Logic ---
    function appendChatMessage(message, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.textContent = message; 
        messageDiv.classList.add('p-2', 'rounded-lg', 'text-sm', 'max-w-md', 'break-words', 'shadow');
        if (sender === 'user') {
            messageDiv.classList.add('bg-indigo-500', 'text-indigo-100', 'self-end', 'ml-auto');
        } else { 
            messageDiv.classList.add('bg-purple-900', 'text-purple-100', 'self-start', 'mr-auto');
        }
        chatHistoryDiv.appendChild(messageDiv);
        chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight; 
    }

    async function handleChatbotInteraction() {
        const userInput = chatInput.value.trim();
        if (!userInput) return;

        appendChatMessage(userInput, 'user');
        chatInput.value = '';
        aiChatLoadingIndicator.classList.remove('hidden');
        sendChatButton.disabled = true;

        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0'); 
        const day = String(today.getDate()).padStart(2, '0');
        const currentDateForAI = `${year}-${month}-${day}`;

        const enhancedPrompt = `Konteks: Tanggal hari ini adalah ${currentDateForAI}. Permintaan pengguna: "${userInput}". Jika pengguna meminta estimasi waktu seperti "minggu depan" atau "besok", gunakan tanggal hari ini (${currentDateForAI}) sebagai referensi utama. Pastikan semua tanggal deadline dalam format YYYY-MM-DD.`; // Bagian yang terpotong ditambahkan di sini

        const taskGenerationSchema = {
            type: "OBJECT",
            properties: {
                tasks: {
                    type: "ARRAY",
                    items: {
                        type: "OBJECT",
                        properties: {
                            name: { type: "STRING", description: "Nama tugas yang jelas dan singkat." },
                            description: { type: "STRING", description: "Deskripsi singkat mengenai tugas tersebut (opsional)." },
                            deadline: { type: "STRING", description: `Tanggal deadline dalam format YYYY-MM-DD. Gunakan tanggal hari ini (${currentDateForAI}) sebagai referensi jika pengguna meminta estimasi waktu (misal: "1 minggu dari sekarang").` }
                        },
                        required: ["name", "deadline"]
                    }
                },
                message: { type: "STRING", description: "Pesan tambahan atau klarifikasi dari AI jika diperlukan, atau respons umum jika input tidak terkait pembuatan tugas."}
            }
        };

        try {
            const aiResponse = await callGenericGeminiAPI(enhancedPrompt, taskGenerationSchema);

            if (aiResponse && aiResponse.tasks && Array.isArray(aiResponse.tasks) && aiResponse.tasks.length > 0) {
                appendChatMessage(aiResponse.message || `Baik, berdasarkan tanggal hari ini (${formatDate(currentDateForAI)}), berikut adalah daftar tugas yang berhasil saya buat:`, 'ai');
                aiResponse.tasks.forEach(taskData => {
                    if (taskData.name && taskData.deadline) {
                        if (!/^\d{4}-\d{2}-\d{2}$/.test(taskData.deadline)) {
                             console.warn("Format deadline dari AI tidak valid:", taskData.deadline, "untuk tugas:", taskData.name);
                             appendChatMessage(`Format deadline (${taskData.deadline}) untuk tugas "${taskData.name}" dari AI tidak sesuai, mungkin perlu diperbaiki manual.`, 'ai');
                        } else {
                            addTask(taskData.name, taskData.description || '', taskData.deadline, true);
                        }
                    } else {
                        console.warn("AI task data incomplete:", taskData);
                        appendChatMessage(`Saya menemukan data tugas yang kurang lengkap dari AI: ${JSON.stringify(taskData)}`, 'ai');
                    }
                });
                showMessage('Tugas berhasil dibuat oleh AI!', 'success');
            } else if (aiResponse && aiResponse.message) { 
                 appendChatMessage(aiResponse.message, 'ai');
            }
            else { 
                appendChatMessage("Maaf, terjadi kesalahan saat memproses permintaan Anda dengan AI atau format respon tidak sesuai.", 'ai');
                showMessage('Gagal mendapatkan respon yang valid dari AI.', 'error');
            }
        } catch (error) {
            console.error("Error interacting with AI for task generation:", error);
            appendChatMessage(`Maaf, saya mengalami kendala teknis: ${error.message}. Coba lagi nanti.`, 'ai');
            showMessage('Terjadi kesalahan teknis dengan AI.', 'error');
        } finally {
            aiChatLoadingIndicator.classList.add('hidden');
            sendChatButton.disabled = false;
        }
    }

    sendChatButton.addEventListener('click', handleChatbotInteraction);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            handleChatbotInteraction();
        }
    });

    // --- Generic Gemini API Call Function ---
    async function callGenericGeminiAPI(prompt, responseSchema) {
        const apiKey = "AIzaSyCfI1ycQSFb06Lbx4-trXj9JGEu4PWJvN8"; 
        const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${apiKey}`;
        
        const payload = {
            contents: [{ role: "user", parts: [{ text: prompt }] }],
            generationConfig: {
                responseMimeType: "application/json",
                responseSchema: responseSchema
            }
        };

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: { message: "Gagal memparsing error dari API."} }));
                console.error("Gemini API Error HTTP Status:", response.status, errorData);
                throw new Error(`Permintaan API gagal dengan status ${response.status}: ${errorData.error?.message || response.statusText || 'Unknown API error'}`);
            }

            const result = await response.json();

            if (result.candidates && result.candidates.length > 0 &&
                result.candidates[0].content && result.candidates[0].content.parts &&
                result.candidates[0].content.parts.length > 0 && result.candidates[0].content.parts[0].text) {
                try {
                    const parsedJson = JSON.parse(result.candidates[0].content.parts[0].text);
                    return parsedJson; 
                } catch (parseError) {
                    console.error("Error parsing JSON response from Gemini:", parseError, result.candidates[0].content.parts[0].text);
                    throw new Error("Gagal memparsing JSON dari respon AI.");
                }
            } else {
                console.error("Unexpected Gemini API response structure:", result);
                if (result.candidates && result.candidates.length > 0 && result.candidates[0].finishReason === 'SAFETY') {
                     throw new Error("Respon diblokir karena alasan keamanan oleh AI.");
                }
                if (result.promptFeedback && result.promptFeedback.blockReason) {
                    throw new Error(`Prompt diblokir oleh AI: ${result.promptFeedback.blockReason}`);
                }
                throw new Error("Format respon dari AI tidak sesuai atau kosong.");
            }
        } catch (error) {
            console.error('Error calling Gemini API:', error);
            throw error; 
        }
    }

    // --- Suggest Sub-Tasks Feature ---
    async function handleSuggestSubTasks(mainTaskId, buttonElement) {
        const mainTask = tasks.find(t => t.id === mainTaskId);
        if (!mainTask) {
            showMessage("Tugas utama tidak ditemukan.", "error");
            return;
        }

        const loader = buttonElement.querySelector('.loader');
        let originalButtonTextNode = null;
        for (let i = 0; i < buttonElement.childNodes.length; i++) {
            if (buttonElement.childNodes[i].nodeType === Node.TEXT_NODE && buttonElement.childNodes[i].textContent.trim() !== "") {
                originalButtonTextNode = buttonElement.childNodes[i];
                break;
            }
        }
        const originalText = originalButtonTextNode ? originalButtonTextNode.textContent : "✨ Sarankan Sub-Tugas";
        
        buttonElement.disabled = true;
        if (originalButtonTextNode) originalButtonTextNode.textContent = originalText.includes("✨") ? "✨ Meminta... " : "Meminta... ";
        if(loader) loader.classList.remove('hidden');
        
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const currentDateForAI = `${year}-${month}-${day}`;

        const subTaskPrompt = `Konteks: Tanggal hari ini adalah ${currentDateForAI}. Untuk tugas utama "${mainTask.name}" (Deskripsi: "${mainTask.description || 'Tidak ada deskripsi.'}" dengan deadline ${mainTask.deadline || 'tidak ditentukan'}), sarankan 2 hingga 4 sub-tugas yang relevan. Setiap sub-tugas harus berupa objek dengan properti 'name' (nama sub-tugas yang singkat dan jelas) dan 'description' (deskripsi singkat untuk sub-tugas, jika ada, maksimal 1 kalimat). Kembalikan dalam format array JSON. Contoh: [{"name": "Sub-Tugas A", "description": "Lakukan X"}, {"name": "Sub-Tugas B", "description": "Lakukan Y"}]`;
        
        const subTaskSchema = {
            type: "ARRAY",
            items: {
                type: "OBJECT",
                properties: {
                    name: { type: "STRING", description: "Nama sub-tugas yang jelas dan ringkas." },
                    description: { type: "STRING", description: "Deskripsi singkat untuk sub-tugas (opsional)." }
                },
                required: ["name"]
            }
        };

        try {
            const suggestedSubTasks = await callGenericGeminiAPI(subTaskPrompt, subTaskSchema);

            if (suggestedSubTasks && Array.isArray(suggestedSubTasks) && suggestedSubTasks.length > 0) {
                appendChatMessage(`AI menyarankan sub-tugas berikut untuk "${mainTask.name}":`, 'ai');
                suggestedSubTasks.forEach(subTaskData => {
                    if (subTaskData.name) {
                        const subTaskName = `Sub: ${subTaskData.name} (dari: ${mainTask.name})`;
                        const subTaskDeadline = mainTask.deadline || currentDateForAI;
                        addTask(subTaskName, subTaskData.description || `Sub-tugas untuk ${mainTask.name}`, subTaskDeadline, true, mainTask.id);
                        appendChatMessage(`- ${subTaskData.name} ${subTaskData.description ? '('+subTaskData.description+')' : ''}`, 'ai');
                    }
                });
                showMessage(`Sub-tugas berhasil disarankan dan ditambahkan untuk "${mainTask.name}"!`, 'success');
            } else if (suggestedSubTasks && Array.isArray(suggestedSubTasks) && suggestedSubTasks.length === 0) {
                appendChatMessage(`AI tidak menemukan saran sub-tugas untuk "${mainTask.name}".`, 'ai');
                showMessage(`AI tidak memberikan saran sub-tugas untuk "${mainTask.name}".`, 'info');
            }
            else { 
                const errorMessage = (suggestedSubTasks && suggestedSubTasks.message) ? suggestedSubTasks.message : 'Tidak ada saran sub-tugas yang valid dari AI.';
                appendChatMessage(errorMessage + ` untuk "${mainTask.name}".`, 'ai');
                showMessage('Gagal mendapatkan saran sub-tugas yang valid dari AI.', 'error');
            }
        } catch (error) { 
            console.error("Error suggesting sub-tasks:", error);
            appendChatMessage(`Gagal mendapatkan saran sub-tugas: ${error.message}`, 'ai');
            showMessage(`Gagal menyarankan sub-tugas: ${error.message}`, 'error');
        } finally { 
            buttonElement.disabled = false;
            if (originalButtonTextNode) originalButtonTextNode.textContent = originalText;
            if(loader) loader.classList.add('hidden');
        }
    } 
    
    // --- LocalStorage Persistence ---
    function saveTasksToLocalStorage() {
        localStorage.setItem('projectMiniTasks_v4_fixed_script', JSON.stringify(tasks));
        localStorage.setItem('projectMiniNextId_v4_fixed_script', nextId.toString());
    }

    function loadTasksFromLocalStorage() {
        const storedTasks = localStorage.getItem('projectMiniTasks_v4_fixed_script');
        const storedNextId = localStorage.getItem('projectMiniNextId_v4_fixed_script');
        if (storedTasks) {
            tasks = JSON.parse(storedTasks);
        }
        if (storedNextId) {
            nextId = parseInt(storedNextId);
        }
    }

    // --- Initial Load ---
    function initializeApp() {
        console.log('Initializing app...'); // Debug log
        fetchTasks(); // Load tasks from database
        
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const todayDateString = `${year}-${month}-${day}`;

        document.getElementById('taskDeadline').setAttribute('min', todayDateString);
        document.getElementById('editTaskDeadline').setAttribute('min', todayDateString);
        
        const initialChatMessageDiv = chatHistoryDiv.querySelector('.bg-purple-900'); 
        if (initialChatMessageDiv) {
            initialChatMessageDiv.innerHTML = `Halo! Beritahu saya tentang proyek Anda (misalnya "buatkan rencana untuk aplikasi X dengan deadline Y"), dan saya akan membantu membuatkan daftar tugasnya. Tanggal hari ini (${formatDate(todayDateString)}) akan otomatis disertakan untuk AI.`;
        }
    }
    
    // Tambahkan event listener untuk memastikan DOM sudah dimuat
    document.addEventListener('DOMContentLoaded', initializeApp);

</script>
</body>
</html>
