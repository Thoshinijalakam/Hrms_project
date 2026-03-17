<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. DATABASE CONNECTION
try {
    $conn = new mysqli("localhost", "root", "", "hrms_elite");
} catch (Exception $e) {
    die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:10px;'>
            <b>Database Connection Failed:</b> Please ensure 'hrms_elite' database exists.
         </div>");
}

$today = date('Y-m-d');
$error_msg = "";

// 2. EXPORT LOGIC
if (isset($_SESSION['authenticated']) && isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=utf-8');
    
    if ($type === 'all') {
        header('Content-Disposition: attachment; filename=all_employees_attendance_'.$today.'.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Employee ID', 'Name', 'Email', 'Department', 'Today Status'));
        $res = $conn->query("SELECT e.emp_id, e.name, e.email, e.department, a.status 
                             FROM employees e 
                             LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = '$today'");
        while ($row = $res->fetch_assoc()) fputcsv($output, $row);
    } 
    elseif ($type === 'individual' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $emp = $conn->query("SELECT name FROM employees WHERE id=$id")->fetch_assoc();
        header('Content-Disposition: attachment; filename='.str_replace(' ', '_', $emp['name']).'_logs.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Date', 'Status', 'Log ID'));
        $res = $conn->query("SELECT attendance_date, status, id FROM attendance WHERE employee_id=$id ORDER BY attendance_date DESC");
        while ($row = $res->fetch_assoc()) fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// 3. LOGOUT LOGIC
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 4. LOGIN LOGIC
if (isset($_POST['login'])) {
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    if ($user === "admin" && $pass === "123") {
        $_SESSION['authenticated'] = true;
        $_SESSION['show_welcome'] = true; 
    } else {
        $error_msg = "Access Denied: Invalid Credentials";
    }
}

// 5. PROTECTED ACTIONS
if (isset($_SESSION['authenticated'])) {
    if (isset($_GET['get_logs'])) {
        $emp_id = intval($_GET['get_logs']);
        $res = $conn->query("SELECT * FROM attendance WHERE employee_id=$emp_id ORDER BY attendance_date DESC LIMIT 200");
        if ($res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $color = ($row['status'] == 'Present') ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50';
                echo "<div class='flex justify-between items-center p-4 mb-3 rounded-2xl border border-white bg-white/60 backdrop-blur-sm shadow-sm'>
                        <div>
                            <span class='block font-bold text-slate-700 text-sm'>".date('M d, Y', strtotime($row['attendance_date']))."</span>
                            <span class='text-[10px] text-slate-400 font-medium tracking-tight'>Log ID: #".$row['id']."</span>
                        </div>
                        <span class='text-[10px] font-black px-4 py-1.5 rounded-full uppercase $color shadow-inner'>".$row['status']."</span>
                      </div>";
            }
        } else { echo "<div class='text-center py-10'><p class='text-slate-400 italic text-sm'>No history found.</p></div>"; }
        exit;
    }

    if (isset($_POST['add'])) {
        $emp_id = $_POST['emp_id']; $name = $_POST['name']; $dept = $_POST['department']; $email = $_POST['email'];
        $check = $conn->prepare("SELECT emp_id FROM employees WHERE emp_id=?");
        $check->bind_param("s", $emp_id); $check->execute();
        if ($check->get_result()->num_rows > 0) { $error_msg = "ID already exists!"; } 
        else {
            $stmt = $conn->prepare("INSERT INTO employees (emp_id, name, email, department) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $emp_id, $name, $email, $dept); $stmt->execute();
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
    }

    if (isset($_GET['mark'])) {
        $id = intval($_GET['mark']); $status = $_GET['status']; 
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
        $stmt->bind_param("iss", $id, $today, $status); $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']); 
        $conn->query("DELETE FROM employees WHERE id=$id");
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>HRMS Elite | Security Portal</title>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; height: 100vh; display: flex; flex-direction: column; overflow: hidden; color: #1e293b; }
        .mesh-bg { position: fixed; inset: 0; z-index: -1; background-image: radial-gradient(at 0% 0%, hsla(220,100%,95%,1) 0, transparent 50%), radial-gradient(at 100% 100%, hsla(260,100%,95%,1) 0, transparent 50%); }
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.8); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); }
        
        #welcomeSplash { position: fixed; inset: 0; background: #ffffff; z-index: 999; display: flex; align-items: center; justify-content: center; transition: opacity 0.8s ease-out; }
        .welcome-text { font-size: 4rem; font-weight: 800; background: linear-gradient(to right, #4f46e5, #9333ea); -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: scaleIn 0.5s ease-out; }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

        .marquee-container { background: white; border-radius: 12px; overflow: hidden; white-space: nowrap; padding: 10px 0; border: 1px solid rgba(79, 70, 229, 0.1); }
        .marquee-content { display: inline-block; animation: marquee 40s linear infinite; color: #4f46e5; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        
        table { border-collapse: separate; border-spacing: 0; width: 100%; table-layout: fixed; }
        thead { position: sticky; top: 0; z-index: 50; background: #f8fafc; }

        /* AUTOMATIC SCROLL LOGIC */
        #tableContainer { overflow-y: auto; scroll-behavior: smooth; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.2); backdrop-filter: blur(8px); z-index: 100; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="mesh-bg"></div>

    <?php if (isset($_SESSION['show_welcome'])): ?>
    <div id="welcomeSplash">
        <div class="text-center">
            <h1 class="welcome-text">Hi Welcome Thosh</h1>
            <p class="text-slate-400 font-bold tracking-[0.3em] uppercase mt-4">System Initializing...</p>
        </div>
    </div>
    <script>
        setTimeout(() => {
            const splash = document.getElementById('welcomeSplash');
            splash.style.opacity = '0';
            setTimeout(() => splash.remove(), 800);
        }, 2500);
    </script>
    <?php unset($_SESSION['show_welcome']); endif; ?>

    <?php if (!isset($_SESSION['authenticated'])): ?>
    <div class="flex-grow flex items-center justify-center">
        <div class="w-full max-w-md glass-card p-10 rounded-[2.5rem]">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-800 text-slate-900 tracking-tighter">HRMS <span class="text-indigo-600">ELITE</span></h1>
                <p class="text-slate-400 text-sm font-semibold mt-2">Administrative Gateway</p>
            </div>
            <?php if($error_msg): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-[10px] font-black uppercase mb-6 text-center border border-red-100"><?= $error_msg ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="text" name="user" placeholder="Username" required class="w-full p-4 bg-white border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-400">
                <input type="password" name="pass" placeholder="Password" required class="w-full p-4 bg-white border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-400">
                <button name="login" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-2xl shadow-lg hover:bg-indigo-700 transition-all uppercase text-xs tracking-widest mt-4">Secure Login</button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <div class="max-w-7xl mx-auto w-full h-full flex flex-col">
        <header class="flex flex-col md:flex-row justify-between items-center mb-4 shrink-0 px-2">
            <div>
                <h1 class="text-3xl font-800 text-slate-900 tracking-tighter">HRMS <span class="text-indigo-600">ELITE</span></h1>
                <p id="liveClock" class="text-xs font-bold text-slate-400 tracking-widest mt-1">00:00:00</p>
            </div>
            <div class="flex items-center gap-4 mt-4 md:mt-0">
                <a href="?export=all" class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 shadow-md transition-all">Full Report</a>
                <input type="text" id="liveSearch" placeholder="Search members..." class="pl-4 pr-4 py-2 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-400 text-sm w-64 shadow-sm">
                <a href="?logout=1" class="bg-white p-2.5 rounded-xl text-slate-400 hover:text-red-500 border border-slate-100 shadow-sm transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                </a>
            </div>
        </header>

        <div class="marquee-container mb-6 shrink-0 shadow-sm">
            <div class="marquee-content">🚀 Welcome to Elite HR Dashboard • Operational • <?= date('l, F j, Y') ?></div>
        </div>

        <div class="glass-card p-5 rounded-[2rem] mb-6 shrink-0">
            <?php if($error_msg): ?>
                <div class="text-red-500 text-[9px] font-black uppercase mb-3 ml-2 tracking-widest">⚠️ Error: <?= $error_msg ?></div>
            <?php endif; ?>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input name="name" required placeholder="Full Name" class="p-3 bg-white border border-slate-100 rounded-xl outline-none focus:ring-2 focus:ring-indigo-400 text-sm">
                <input name="emp_id" required placeholder="ID" class="p-3 bg-white border border-slate-100 rounded-xl outline-none focus:ring-2 focus:ring-indigo-400 text-sm">
                <input name="email" type="email" required placeholder="Email" class="p-3 bg-white border border-slate-100 rounded-xl outline-none focus:ring-2 focus:ring-indigo-400 text-sm">
                <select name="department" class="p-3 bg-white border border-slate-100 rounded-xl outline-none text-sm font-semibold text-slate-600">
                    <option>Engineering</option><option>Operations</option><option>HR/Legal</option><option>Marketing</option>
                </select>
                <button name="add" class="bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-md text-[10px] uppercase tracking-widest">Add Member</button>
            </form>
        </div>

        <div class="glass-card rounded-[2.5rem] overflow-hidden flex-grow flex flex-col border-white">
            <div class="custom-scroll flex-grow" id="tableContainer">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/90 backdrop-blur-md">
                        <tr class="text-slate-400 uppercase text-[10px] font-black tracking-widest">
                            <th class="p-6">Member Info</th>
                            <th class="p-6">Attendance Status</th>
                            <th class="p-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $res = $conn->query("SELECT e.*, a.status as today_status FROM employees e LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = '$today' ORDER BY e.id DESC");
                        while($row = $res->fetch_assoc()):
                        ?>
                        <tr class="hover:bg-indigo-50/40 transition-all table-row group">
                            <td class="p-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-white text-indigo-600 flex items-center justify-center font-800 text-sm border border-indigo-100 shadow-sm">
                                        <?= substr($row['name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="font-800 text-slate-800 text-sm leading-tight"><?= htmlspecialchars($row['name']) ?></div>
                                        <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-1"><?= $row['emp_id'] ?> • <?= $row['department'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-6">
                                <div class="inline-flex gap-1 p-1 bg-white/50 border border-slate-200 rounded-xl shadow-sm">
                                    <a href="?mark=<?= $row['id'] ?>&status=Present" class="px-4 py-2 rounded-lg text-[9px] font-black transition-all <?= $row['today_status']=='Present' ? 'bg-emerald-500 text-white shadow-md' : 'text-slate-400 hover:text-emerald-600 hover:bg-emerald-50' ?>">PRESENT</a>
                                    <a href="?mark=<?= $row['id'] ?>&status=Absent" class="px-4 py-2 rounded-lg text-[9px] font-black transition-all <?= $row['today_status']=='Absent' ? 'bg-red-500 text-white shadow-md' : 'text-slate-400 hover:text-red-600 hover:bg-red-50' ?>">ABSENT</a>
                                </div>
                            </td>
                            <td class="p-6 text-right space-x-2">
                                <button onclick="showLogs(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-[10px] font-bold shadow-sm hover:bg-indigo-600 transition-colors">Logs</button>
                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Archive record?')" class="text-slate-300 hover:text-red-500 p-2 transition-colors inline-block align-middle"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="logModal" class="modal" onclick="this.classList.remove('active')">
        <div class="glass-card p-8 rounded-[2.5rem] w-full max-w-md mx-4 shadow-2xl flex flex-col max-h-[85vh]" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-6 shrink-0">
                <div class="flex flex-col"><h3 id="modalName" class="text-xl font-800 text-slate-900 tracking-tight">Logs</h3><a id="downloadIndv" href="#" class="text-[9px] font-black text-indigo-600 uppercase mt-1 hover:underline">Download History</a></div>
                <button onclick="document.getElementById('logModal').classList.remove('active')" class="text-slate-400">✕</button>
            </div>
            <div id="logContent" class="overflow-y-auto custom-scroll flex-grow pr-2"></div>
        </div>
    </div>

    <script>
        setInterval(() => { document.getElementById('liveClock').innerText = new Date().toLocaleTimeString('en-US', { hour12: false }); }, 1000);
        
        document.getElementById('liveSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
            });
        });

        function showLogs(id, name) {
            document.getElementById('modalName').innerText = name;
            document.getElementById('downloadIndv').href = '?export=individual&id=' + id;
            document.getElementById('logModal').classList.add('active');
            document.getElementById('logContent').innerHTML = '<div class="text-center py-10 animate-pulse text-slate-400">Accessing...</div>';
            fetch('?get_logs=' + id).then(res => res.text()).then(data => { document.getElementById('logContent').innerHTML = data; });
        }

        // --- THE AUTO-SCROLL ENGINE ---
        const scrollContainer = document.getElementById('tableContainer');
        let scrollSpeed = 0.6; // Higher = Faster
        let isPaused = false;

        function autoScroll() {
            if (!isPaused) {
                scrollContainer.scrollTop += scrollSpeed;
                // If we hit the bottom, reset smoothly to top
                if (scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1) {
                    scrollContainer.scrollTop = 0;
                }
            }
            requestAnimationFrame(autoScroll);
        }

        // Initialize scroll
        autoScroll();

        // Pause on Hover (so user can click buttons easily)
        scrollContainer.addEventListener('mouseenter', () => isPaused = true);
        scrollContainer.addEventListener('mouseleave', () => isPaused = false);
        
        // Pause if user is searching
        document.getElementById('liveSearch').addEventListener('focus', () => isPaused = true);
        document.getElementById('liveSearch').addEventListener('blur', () => isPaused = false);
    </script>
    <?php endif; ?>
</body>
</html>