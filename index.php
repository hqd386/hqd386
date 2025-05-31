<?php
session_start();
require_once 'config.php';


$error = '';
$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Xử lý đăng nhập nếu form được submit và chưa đăng nhập
if (!$isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputUsername = $conn->real_escape_string(trim($_POST['username']));
    $inputPassword = trim($_POST['password']);

    $sql = "SELECT id, username, password, role FROM users WHERE username = '$inputUsername' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($inputPassword, $user['password'])) {
            // Đăng nhập thành công, lưu session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Cập nhật trạng thái đăng nhập
            $isLoggedIn = true;
            $username = $user['username'];
            $role = $user['role'];

        } else {
            $error = "Sai mật khẩu.";
        }
    } else {
        $error = "Tài khoản không tồn tại.";
    }
}



$isAjax = isset($_GET['ajax']);



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_token'])) {


    $deleteToken = $conn->real_escape_string($_POST['delete_token']);



    // Xóa cả trong bảng `monitoring_data` nếu cần

    $conn->query("DELETE FROM monitoring_data WHERE computer_token = '$deleteToken'");



    // Xóa trong bảng `computers`

    if ($conn->query("DELETE FROM computers WHERE token = '$deleteToken'")) {

        // Xóa thành công

        header("Location: index.php"); // reload trang

        exit;

    } else {

        echo "Lỗi khi xóa: " . $conn->error;

    }

}

if (!$isLoggedIn) {
    // Nếu chưa đăng nhập, không thực hiện query SQL để tránh lỗi
    $result = false;
    $numRecords = 0;

    // Nếu là request ajax (fetchData), trả về mảng rỗng
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
} else {

if ($role === 'admin') {
$sql = "SELECT 
            c.token, 
            c.computer_name, 
            c.username, 
            c.local_ip, 
            c.public_ip,
            m.cpu_name,
            m.cpu_speed,
            m.cpu_cores,
            m.cpu_threads,
            m.cpu_temp,
            m.cpu_load,
            m.ram_usage,
            m.ram_total,
            m.ram_free,
            m.mainboard,
            m.vga,
            m.win_info,
            m.timestamp
        FROM computers c
        LEFT JOIN (
            SELECT t1.*
            FROM monitoring_data t1
            INNER JOIN (
                SELECT computer_token, MAX(timestamp) AS max_timestamp
                FROM monitoring_data
                GROUP BY computer_token
            ) AS t2 ON t1.computer_token = t2.computer_token AND t1.timestamp = t2.max_timestamp
        ) AS m ON m.computer_token = c.token
        ORDER BY c.computer_name";
} else {
$user_id = $_SESSION['user_id'];

$sql = "
    SELECT 
        c.token, 
        c.computer_name, 
        c.username, 
        c.local_ip,
        m.cpu_name,
        m.cpu_speed,
        m.cpu_cores,
        m.cpu_threads,
        m.cpu_temp,
        m.cpu_load,
        m.ram_usage,
        m.ram_total,
        m.ram_free,
        m.mainboard,
        m.vga,
        m.win_info,
        m.timestamp
    FROM computers c
    INNER JOIN user_permissions up 
        ON c.token = up.token AND up.user_id = $user_id
    LEFT JOIN (
        SELECT t1.*
        FROM monitoring_data t1
        INNER JOIN (
            SELECT computer_token, MAX(timestamp) AS max_timestamp
            FROM monitoring_data
            GROUP BY computer_token
        ) AS t2 
        ON t1.computer_token = t2.computer_token AND t1.timestamp = t2.max_timestamp
    ) AS m 
    ON m.computer_token = c.token
    ORDER BY c.computer_name
";

}


$result = $conn->query($sql);
if (!$result) {
    die("Lỗi truy vấn: " . $conn->error);
}
}


if ($isAjax) {

    $data = [];

    while ($row = $result->fetch_assoc()) {

        $isOnline = false;

        if (!empty($row['timestamp'])) {

            $isOnline = (time() - strtotime($row['timestamp'])) < 300;

        }

         if (!empty($row['cpu_temp']) && $row['cpu_temp'] > 80) {
        $warningLog = "[".date('d-m-Y H:i:s')."] Máy tính '{$row['computer_name']}' (Token: {$row['token']}) cảnh báo: Nhiệt độ CPU {$row['cpu_temp']}°C\n";
        file_put_contents('cpu_warning.log', $warningLog, FILE_APPEND);
				}


        $ramTotal = round($row['ram_total']/1024, 2);

        $ramFree = round($row['ram_free']/1024, 2);

       $data[] = [

    'token' => $row['token'],

    'computer_name' => $row['computer_name'],

    'username' => $row['username'],

    'local_ip' => $row['local_ip'],

    'public_ip' => $row['public_ip'],

    'cpu_name' => $row['cpu_name'],

    'cpu_speed' => $row['cpu_speed'],

    'cpu_cores' => $row['cpu_cores'],

    'cpu_threads' => $row['cpu_threads'],

    'cpu_temp' => $row['cpu_temp'],

    'cpu_load' => $row['cpu_load'],

    'ram_usage' => $row['ram_usage'],

    'ram_total' => round($row['ram_total']/1024, 2),

    'ram_free' => round($row['ram_free']/1024, 2),

    'main_name' => $row['mainboard'], // Thêm dữ liệu Mainboard

    'vga_name' => $row['vga'], // Thêm dữ liệu VGA

     'win_info'      => $row['win_info'],  // Dữ liệu win_info

    'timestamp' => $row['timestamp'],

    'isOnline' => $isOnline

];

    }
		

    header('Content-Type: application/json');

    echo json_encode($data);
		
		

    exit;

}



$numRecords = $result->num_rows;

?>

<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>System Monitoring Dashboard</title>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
.btn-circle {
    width: 30px;     /* giảm từ 40px còn 20px */
    height: 30px;
    padding: 0;
    border-radius: 50%;
    text-align: center;
    line-height: 20px;
    font-size: 15px;  /* thu nhỏ icon bên trong */
}


.log-line {
    padding: 1px 1px; /* giảm padding */
    border-radius: 1px;
    background-color: #f8f9fa;
    font-size: 0.75rem; /* đảm bảo font nhỏ gọn */
    line-height: 1; /* giảm khoảng cách dòng */
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1px; /* nếu cần khoảng cách nhỏ giữa các dòng */
}

.log-line:hover {
    background-color: #e9ecef;
}
		.login-toggle {

    position: fixed;

    top: 40%;

    right: 0;

    z-index: 1050;

    transform: translateY(-50%);

}



.login-sidebar {

    position: fixed;

    top: 0;

    right: -300px;

    width: 280px;

    height: 100%;

    background: #fff;

    transition: right 0.3s ease-in-out;

    z-index: 1060;

    border-left: 1px solid #ddd;

    overflow-y: auto;

}



.login-sidebar.show {

    right: 0;

}

        :root {

            --primary: #4361ee;

            --secondary: #3f37c9;

            --success: #4caf50;

            --danger: #f44336;

            --warning: #ff9800;

            --info: #2196f3;

            --light: #f8f9fa;

            --dark: #212529;

            --bg-light: #f5f7ff;

            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);

        }

        

        body {

            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

            background-color: var(--bg-light);

            color: #333;

            min-height: 100vh;

        }

        

        .dashboard-header {

            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);

            color: white;

            padding: 15px 0;

            margin-bottom: 20px;

            border-radius: 0 0 20px 20px;

            box-shadow: var(--card-shadow);

        }

        

        .stats-container {

            margin-bottom: 5px;

        }

        

.stat-card {

    display: flex;

    align-items: center; /* Căn giữa theo chiều dọc */

    justify-content: space-between; /* Đảm bảo khoảng cách hợp lý */

    gap: 10px; /* Tạo khoảng cách giữa các phần */

    padding: 10px;

    min-height: 60px; /* Đảm bảo chiều cao phù hợp */

		border-bottom: 4px solid rgba(0, 0, 0, 0.2); /* Đường kẻ dưới */

    padding-bottom: 5px;

}



.stat-card:nth-child(even) {

    background-color: #f0f7ff; /* Xanh nhạt */

}





.stat-icon {

    width: 45px;

    height: 45px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%; /* Bo tròn icon */

    border: 2px solid rgba(0, 0, 0, 0.2); /* Viền màu xám */

    font-size: 18px;

    color: white;

}





.stat-info {

    display: flex;

    flex-direction: column;

    align-items: flex-start;

}



.stat-value {

    font-size: 16px;

    font-weight: bold;

    white-space: nowrap; /* Ngăn số lượng bị xuống dòng */

		margin-right: 50px;

}



.stat-label {

    font-size: 50px;

    color: #666;

    white-space: nowrap;

}



           

        .stat-icon.computers {

            background-color: var(--primary);

        }

        

        .stat-icon.online {

            background-color: var(--success);

        }

        

        .stat-icon.offline {

            background-color: var(--danger);

        }

        

        .stat-value {

            font-size: 24px;

            font-weight: bold;

            margin-bottom: 2px;

        }

        

        .stat-label {

            color: #666;

            font-size: 13px;

        }

        

                .system-card {

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 0;

            box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.08);

            transition: all 0.3s ease;

            margin-bottom: 15px;

            position: relative;

            overflow: hidden;

        }



        .system-card:nth-child(even) {

            background-color: #f0f7ff !important; /* Màu xanh nhạt */

            border-left: 4px solid var(--primary);

        }



        .system-card:nth-child(odd) {

            background-color: #fff6f0 !important; /* Màu cam nhạt */

            border-left: 4px solid var(--warning);

        }

        

        .system-card:hover {

            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);

            transform: translateY(-2px);

        }

        

        .system-card .card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 10px 15px;

            border-bottom: 1px solid rgba(0, 0, 0, 0.05);

            border-radius: 10px 10px 0 0;

        }

        

        .system-card:nth-child(even) .card-header {

            background-color: rgba(67, 97, 238, 0.08);

        }

        

        .system-card:nth-child(odd) .card-header {

            background-color: rgba(255, 152, 0, 0.08);

        }

        

        .status-badge {

            padding: 3px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

        }

        

        .status-online {

            background-color: rgba(76, 175, 80, 0.1);

            color: var(--success);

        }

        

        .status-offline {

            background-color: rgba(244, 67, 54, 0.1);

            color: var(--danger);

        }

        

        .system-card .card-body {

            padding: 12px 15px;

        }

        

        .info-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 8px;

        }



@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}



        .info-item {

            display: flex;

            align-items: flex-start;

            gap: 8px;

        }

        

        .info-icon {

            color: var(--primary);

            font-size: 14px;

            margin-top: 2px;

        }

        

        .info-content h6 {

            margin: 0;

            font-size: 11px;

            color: #666;

        }

        

        .info-content p {

            margin: 0;

            font-weight: 500;

            font-size: 13px;

        }

        

        .info-content small {

            font-size: 11px;

            color: #666;

        }

        

        .progress {

            height: 6px;

            border-radius: 3px;

            margin-top: 3px;

            margin-bottom: 10px;

        }

        

        .progress-cpu .progress-bar {

            background-color: var(--primary);

        }

        

        .progress-ram .progress-bar {

            background-color: var(--warning);

        }

        

        .metrics-container {

            display: flex;

            gap: 15px;

        }

        

        .metric-item {

            flex: 1;

        }

        

        .metric-label {

            display: flex;

            justify-content: space-between;

            font-size: 12px;

            margin-bottom: 3px;

        }

        

        .ip-badge {

            display: inline-block;

            background-color: #e9ecef;

            border-radius: 3px;

            padding: 2px 6px;

            font-size: 11px;

            margin-top: 2px;

            margin-right: 4px;

        }

        

        .card-footer {

            padding: 6px 15px;

            border-top: 1px solid rgba(0, 0, 0, 0.05);

            font-size: 11px;

            color: #666;

            border-radius: 0 0 10px 10px;

        }

        

        .system-card:nth-child(even) .card-footer {

            background-color: rgba(67, 97, 238, 0.05);

        }

        

        .system-card:nth-child(odd) .card-footer {

            background-color: rgba(255, 152, 0, 0.05);

        }

        

        .refresh-btn {

            background-color: var(--primary);

            color: white;

            border: none;

            border-radius: 50%;

            width: 45px;

            height: 45px;

            font-size: 18px;

            position: fixed;

            bottom: 25px;

            right: 25px;

            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);

            z-index: 1000;

            transition: all 0.3s ease;

            display: flex;

            align-items: center;

            justify-content: center;

        }

        

        .refresh-btn:hover {

            transform: rotate(180deg);

            background-color: var(--secondary);

        }

        

        .refresh-btn i {

            transition: transform 0.5s ease;

        }

        

        .refresh-btn.loading i {

            animation: spin 1s linear infinite;

        }

        

        @keyframes spin {

            0% { transform: rotate(0deg); }

            100% { transform: rotate(360deg); }

        }

        

        .empty-state {

            text-align: center;

            padding: 25px;

            color: #666;

        }

        

        .empty-icon {

            font-size: 40px;

            color: #ccc;

            margin-bottom: 12px;

        }

        

        .computer-token {

            font-size: 11px;

            color: #777;

        }

        

        h5.computer-name {

            margin-bottom: 0;

            font-size: 16px;

        }

				

				

				

				

/* Dùng font hiện đại */



/* Cho 3 cột chung một hàng, chia đều width với gap */

.stats-row {

  display: grid;

  grid-template-columns: repeat(3, 1fr);  /* 3 cột 1fr */

  gap: 1rem;                              /* khoảng cách giữa các cột */

  width: 100%;                            /* full container-fluid */

  box-sizing: border-box;

  padding: 0.75rem 1rem;                  /* nếu cần giữ margin hai bên */

	 min-height: 100px;         /* hoặc bỏ nếu muốn card co theo nội dung */

  height: auto;              /* co theo nội dung */

}





/* Reset margin của body để đảm bảo sát mép */

html, body {

  margin: 0;

  padding: 0;

}



/* Nội dung trong mỗi card vẫn xếp dọc, căn giữa */

.stat-card {

 flex: 1 1 0;

  display: flex;

  flex-direction: row;     /* icon – số – label xếp ngang */

  align-items: center;      /* căn giữa theo chiều dọc */

  text-align: center;

  padding: 0.5rem;

	  border-radius: 16px;            /* Bo tròn góc đẹp */

  /* Phần style bạn đã có */

  color: #fff;

  border: none;

  box-shadow: 0 4px 20px rgba(0,0,0,0.12);

  transition: transform .3s ease, box-shadow .3s ease;

  background-size: 200% 200%;

	  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);

  transition: transform 0.3s ease, box-shadow 0.3s ease;

  height: auto;

  gap: 0.5rem;              /* khoảng cách giữa các phần tử trong hàng */

	  font-size: 1.25rem;       /* cỡ chữ lớn hơn, in đậm */

  font-weight: 600;

}



/* Khoảng cách trong card */

.stat-card .icon-animated { margin-bottom: 0.5rem; }

.stat-card .stat-number  { margin-bottom: 0.25rem; }



/* Gradients và hover effect giữ nguyên */

.stat-total { background-image: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.stat-online{ background-image: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

.stat-offline{ background-image: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }



.stat-card:hover {

  transform: translateY(-5px);

  box-shadow: 0 8px 30px rgba(0,0,0,0.2);

  background-position: 100% 0;

}



/* Animation icon */

@keyframes pulse {

  0%,100% { transform: scale(1); opacity: 1; }

  50%     { transform: scale(1.15); opacity: 0.9; }

}

.icon-animated {

  font-size: 1.6rem;

  animation: pulse 2s ease-in-out infinite;

}





@media (max-width: 576px) {

  .stats-row {

    display: flex;

    flex-direction: row;

    flex-wrap: nowrap;        /* không cho xuống hàng */

    gap: 0;                   /* bỏ khoảng cách */

    overflow-x: hidden;       /* không cuộn ngang */

    padding: 0.5rem 0;

  }



  .stat-card {

    display: flex;

    flex-direction: column;   /* icon → số → label */

    align-items: center;

    text-align: center;

    white-space: normal;

    padding: 0.75rem 0;       /* chỉ padding dọc */

    margin: 0;                /* bỏ margin */

    box-sizing: border-box;

  border-radius: 16px;            /* Bo tròn góc đẹp */

    flex: 1 1 0;              /* mỗi card linh hoạt, cùng grow */

    max-width: 33.3333%;      /* tối đa 1/3 container */

    min-width: 0;             /* tránh nội dung ép rộng quá */

  }



  .stat-card .icon-animated {

    margin-bottom: 0.5rem;

  }

  .stat-card .stat-number {

    margin-bottom: 0.25rem;

  }

}





    </style>

</head>

<body>

    <div class="dashboard-header">

        <div class="container">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h1 class="mb-0 fs-4"><i class="fas fa-server me-2"></i>System Monitoring</h1>

                    <p class="mb-0 small">Theo dõi và giám sát hệ thống máy tính.</p>

                </div>

                <div class="text-end">

                    <div class="mb-1 time-display small" id="currentTime"></div>

                    <div class="last-update small" id="lastUpdate">Cập nhật lần cuối: --:--:--</div>

                </div>

            </div>

        </div>

    </div>



    <div class="container">

	<!-- Kéo full‑width viewport, bỏ padding hai bên -->

<div class="container-fluid px-0">

  <div class="stats-row">

    <div class="stat-card stat-total">

      <i class="fas fa-desktop icon-animated"></i>

     

      <div class="stat-label">Tổng số máy tính</div>

			 <div class="stat-number" id="totalComputers">0</div>

    </div>

    <div class="stat-card stat-online">

      <i class="fas fa-check-circle icon-animated"></i>

      

      <div class="stat-label">Đang hoạt động</div>

			<div class="stat-number" id="onlineComputers">0</div>

    </div>

    <div class="stat-card stat-offline">

      <i class="fas fa-times-circle icon-animated"></i>

      

      <div class="stat-label">Ngắt kết nối</div>

			<div class="stat-number" id="offlineComputers">0</div>

    </div>

  </div>

</div>





				

				

        

        <div id="computers-container">

            <!-- System cards will be inserted here -->

        </div>

        

        <div id="empty-state" class="empty-state d-none">

            <div class="empty-icon">

                <i class="fas fa-laptop-code"></i>

            </div>

            <h5>Không có dữ liệu</h5>

            <p>Hiện tại không có máy tính nào được kết nối hoặc giám sát.</p>

        </div>

    </div>

    

    <button class="refresh-btn" id="refreshBtn" title="Refresh Data">

        <i class="fas fa-sync-alt"></i>

    </button>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        // Format time function

        function formatTime(date) {

            return date.toLocaleTimeString('vi-VN', { 

                hour: '2-digit', 

                minute: '2-digit', 

                second: '2-digit',

                hour12: false 

            });

        }

        

        // Update current time

        function updateCurrentTime() {

            const now = new Date();

            document.getElementById('currentTime').textContent = now.toLocaleDateString('vi-VN', { 

                weekday: 'long', 

                year: 'numeric', 

                month: 'long', 

                day: 'numeric',

                hour: '2-digit',

                minute: '2-digit',

                second: '2-digit',

                hour12: false

            });

        }

        

        // Set interval for current time

        setInterval(updateCurrentTime, 1000);

        updateCurrentTime();

        

        // Refresh button animation

        const refreshBtn = document.getElementById('refreshBtn');

        

        refreshBtn.addEventListener('click', function() {

            fetchData(true);

        });

        

        // Function to fetch data

        function fetchData(manualRefresh = false) {

            if (manualRefresh) {

                refreshBtn.classList.add('loading');

            }

            

            fetch('?ajax=1')

                .then(response => response.json())

                .then(data => {

                    // Update last update time

                    document.getElementById('lastUpdate').textContent = 'Cập nhật lần cuối: ' + formatTime(new Date());

                    

                    // Update stats

                    const totalComputers = data.length;

                    let onlineComputers = 0;

                    data.forEach(computer => {

                        if (computer.isOnline) onlineComputers++;

                    });

                    const offlineComputers = totalComputers - onlineComputers;

                    

                    document.getElementById('totalComputers').textContent = totalComputers;

                    document.getElementById('onlineComputers').textContent = onlineComputers;

                    document.getElementById('offlineComputers').textContent = offlineComputers;

                    

                    // Show empty state if no data

                    if (data.length === 0) {

                        document.getElementById('empty-state').classList.remove('d-none');

                        document.getElementById('computers-container').innerHTML = '';

                    } else {

                        document.getElementById('empty-state').classList.add('d-none');

                        renderComputerCards(data);

                    }

                    

                    if (manualRefresh) {

                        setTimeout(() => {

                            refreshBtn.classList.remove('loading');

                        }, 500);

                    }

                })

                .catch(error => {

                    console.error('Lỗi khi tải dữ liệu:', error);

                    if (manualRefresh) {

                        refreshBtn.classList.remove('loading');

                    }

                });

        }

        

        // Function to render computer cards

        function renderComputerCards(data) {

    const container = document.getElementById('computers-container');

    container.innerHTML = '';

    

    data.forEach(computer => {

        const statusClass = computer.isOnline ? 'status-online' : 'status-offline';

        const statusText = computer.isOnline ? 

            '<i class="fas fa-circle me-1"></i>Online' : 

            '<i class="fas fa-circle me-1"></i>Offline';

        

        const cpuLoad = computer.cpu_load ? computer.cpu_load : 0;

        const ramUsage = computer.ram_usage ? computer.ram_usage : 0;

        const cpuTemp = computer.cpu_temp ? computer.cpu_temp : 'N/A';

        const isCpuHot = Number(computer.cpu_temp) > 80;

        const html = `

        <div class="system-card">

            <div class="card-header">

                <div>

                    <h5 class="computer-name">${escapeHtml(computer.computer_name)}  
										 ${isCpuHot ? `<span class="badge bg-danger ms-2" style="font-size:12px;">CẢNH BÁO: Nhiệt CPU cao (${cpuTemp}°C)</span>` : ''}
										<?php if ($_SESSION['role'] === 'admin'): ?>
    <form action="delete_token.php" method="post" 
          style="display: inline-flex; vertical-align: middle; margin-left: 6px; margin-top: 10px;" 
          onsubmit="return confirm('Bạn có chắc chắn muốn xóa token này?');">
        <input type="hidden" name="token" value="${computer.token}">
        <button type="submit" class="btn btn-danger btn-sm p-0" 
            style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; 
                   font-size: 0.75rem;">
            <i class="bi bi-trash" style="font-size: 0.85rem;"></i>
        </button>
    </form>
    <?php endif; ?>
		
		
                 <div class="computer-token">  Token: ${escapeHtml(computer.token)}</div>

							   
                </div>
<span class="status-badge ${statusClass}">${statusText}</span>	</h5>

								
            </div>

            <div class="card-body">

                <div class="info-grid">

                    <div class="info-item">

                        <div class="info-icon">

                            <i class="fas fa-user"></i>

                        </div>

                        <div class="info-content">

                            <h6>Người dùng</h6>

                            <p>${escapeHtml(computer.username)}</p>

                        </div>

                    </div>

                    <div class="info-item">

                        <div class="info-icon">

                            <i class="fas fa-microchip"></i>

                        </div>

                        <div class="info-content">

                            <h6>CPU</h6>

                            <p>${escapeHtml(computer.cpu_name || 'N/A')}</p>

                            <small>${computer.cpu_cores ? computer.cpu_cores + ' cores' : ''} ${computer.cpu_threads ? computer.cpu_threads + ' threads' : ''}</small>

                        </div>

                    </div>

								 <div class="info-item">

        <div class="info-icon">

            <i class="fas fa-server"></i>

        </div>

        <div class="info-content">

            <h6>Mainboard</h6>

            <p>${escapeHtml(computer.main_name || 'N/A')}</p>

        </div>

    </div>


<div class="info-item">
    <div class="info-icon">
        <i class="fas fa-server"></i>
    </div>
    <div class="info-content">
        <h6>Win Info</h6>
        <p>${escapeHtml(computer.win_info || 'N/A')}</p>
    </div>
</div>



  

                           <div class="info-item">

                        <div class="info-icon">

                            <i class="fas fa-memory"></i>

                        </div>

                        <div class="info-content">

                            <h6>RAM</h6>

                            <p>${computer.ram_total ? computer.ram_total + ' GB' : 'N/A'}</p>

                            <small>Free: ${computer.ram_free ? computer.ram_free + ' GB' : 'N/A'}</small>

                        </div>

                    </div>

                  

									

									    <div class="info-item">

    <div class="info-icon">

        <i class="fas fa-video"></i>

    </div>

    <div class="info-content">

        <h6>VGA</h6>

        <p><span>${escapeHtml(computer.vga_name || 'N/A')}</span></p>

    </div>

</div>



<div class="info-item">

                        <div class="info-icon">

                            <i class="fas fa-network-wired"></i>

                        </div>

                        <div class="info-content">

                            <h6>IP</h6>

                            <p>

                                <span class="ip-badge">L: ${escapeHtml(computer.local_ip)}</span>
	<?php if ($_SESSION['role'] === 'admin'): ?>
                                <span class="ip-badge">P: ${escapeHtml(computer.public_ip)}</span>
	<?php endif; ?>
                            </p>

                        </div>

                    </div>
									

									

  

                    <div class="metric-item">

                        <div class="metric-label">

                            <span>CPU Load</span>

                            <span>${cpuLoad}% (${cpuTemp}°C)</span>

                        </div>

                        <div class="progress progress-cpu">

                            <div class="progress-bar" role="progressbar" style="width: ${cpuLoad}%" aria-valuenow="${cpuLoad}" aria-valuemin="0" aria-valuemax="100"></div>

                        </div>

                    </div>

                    

                    <div class="metric-item">

                        <div class="metric-label">

                            <span>RAM Usage</span>

                            <span>${ramUsage}%</span>

                        </div>

                        <div class="progress progress-ram">

                            <div class="progress-bar" role="progressbar" style="width: ${ramUsage}%" aria-valuenow="${ramUsage}" aria-valuemin="0" aria-valuemax="100"></div>

                        </div>

                    </div>

                </div>



        

                

            

                

              ${computer.isOnline ? `

     <?php if ($_SESSION['role'] === 'admin'): ?>

		<div class="mt-3 d-flex flex-wrap gap-2">

    <!-- Nhóm nút điều khiển -->

    <button class="btn btn-sm btn-danger" onclick="sendAction('${escapeHtml(computer.token)}', 'shutdown')">

        <i class="fas fa-power-off"></i> Tắt máy

    </button>



    <button class="btn btn-sm btn-warning" onclick="sendAction('${escapeHtml(computer.token)}', 'restart')">

        <i class="fas fa-sync-alt"></i> Khởi động

    </button>



    <button class="btn btn-sm btn-secondary" onclick="sendAction('${escapeHtml(computer.token)}', 'logoff')">

        <i class="fas fa-sign-out-alt"></i> Đăng xuất

    </button>



    <button class="btn btn-sm btn-warning" onclick="sendAction('${escapeHtml(computer.token)}', 'close_all')">

        <i class="fas fa-window-close"></i> Đóng ứng dụng

    </button>



    <button class="btn btn-sm btn-info" onclick="sendAction('${escapeHtml(computer.token)}', 'screenshot')">

        <i class="fas fa-camera"></i> Chụp màn

    </button>



    <button class="btn btn-sm btn-primary" onclick="window.open('view_screenshots.php', '_blank')">

        <i class="fas fa-camera"></i> Xem

    </button>
   <button class="btn btn-sm btn-danger" onclick="sendAction('${escapeHtml(computer.token)}', 'anydesk')">

        <i class="fas fa-power-on"></i> Anydesk

    </button>

</div>



		  <?php endif; ?>

		` : ''}

            </div>

            <div class="card-footer">

                Cập nhật: ${computer.timestamp || 'N/A'}

            </div>

        </div>	 `;

        

        container.innerHTML += html;

    });

}

        

        // Escape HTML to prevent XSS

        function escapeHtml(unsafe) {

            if (!unsafe) return '';

            return unsafe

                 .replace(/&/g, "&amp;")

                 .replace(/</g, "&lt;")

                 .replace(/>/g, "&gt;")

                 .replace(/"/g, "&quot;")

                 .replace(/'/g, "&#039;");

        }

        

        // Initial fetch

        fetchData();

        

        // Set interval for auto-refresh

        setInterval(fetchData, 5000);

				

				// tat ung dung

function sendAction(token, action) {

    if (!token || token.trim() === '') {

        alert('Lỗi: Token không hợp lệ');

        return;

    }

    

    // Thêm xác nhận cho các hành động nguy hiểm

    if (action === 'shutdown') {

        if (!confirm('Bạn có chắc chắn muốn tắt máy tính này?')) {

            return;

        }

    } else if (action === 'restart') {

        if (!confirm('Bạn có chắc chắn muốn restart trên máy tính này?')) {

            return;

        }

    }

 else if (action === 'logoff') {

        if (!confirm('Bạn có chắc chắn muốn logoff trên máy tính này?')) {

            return;

        }

    }

 else if (action === 'close_all') {

        if (!confirm('Bạn có chắc chắn muốn đóng tất cả ứng dụng trên máy tính này?')) {

            return;

        }

    }

    // Có thể thêm xác nhận cho các hành động khác nếu cần



    // Hiển thị trạng thái đang xử lý

    const actionBtn = event.target.closest('button');

    const originalText = actionBtn.innerHTML;

    actionBtn.disabled = true;

    actionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';



    fetch('action_receiver.php?token=' + encodeURIComponent(token) + '&action=' + encodeURIComponent(action))

        .then(response => response.json())

        .then(data => {

            if (data.status === 'success') {

                alert('Đã gửi lệnh ' + action + ' đến máy tính thành công!');

            } else {

                alert('Lỗi: ' + (data.message || 'Không thể gửi lệnh đến máy tính'));

            }

        })

        .catch(error => {

            console.error('Lỗi gửi lệnh:', error);

            alert('Lỗi kết nối: Không thể gửi lệnh đến máy tính');

        })

        .finally(() => {

            actionBtn.disabled = false;

            actionBtn.innerHTML = originalText;

        });

}



    </script>

		

		<center>

       <div class="card-footer"> © 2025 wWw.hqd.io.vn <br> Chúng tôi luôn đồng hành cùng bạn!

<div>

</center>

<!-- Nút mở khung đăng nhập bằng icon -->

<div id="loginToggleBtn" class="login-toggle">
    <button class="btn btn-outline-primary btn-circle" type="button">
        <i class="fa fa-user"></i>
    </button>
</div>



<!-- Khung đăng nhập ẩn -->

<div id="loginSidebar" class="login-sidebar shadow">

    <div class="p-3">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <h5 class="mb-0"> <?php if ($_SESSION['role'] === 'admin'): ?>
 <a href="manage_users.php" class="btn btn-outline-primary btn-sm rounded-pill shadow-sm" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">
  <i class="bi bi-person-gear"></i> Quản lý user
</a>
  <?php endif; ?></h5>

            <button id="closeLogin" class="btn-close"></button>

        </div>



        <?php if (!$isLoggedIn): ?>

 <p style="text-align: center;"><h5> ĐĂNG NHẬP </h5></p>
 
            <?php if (isset($error)) echo "<p class='text-danger'>$error</p>"; ?>

            <form method="post">
        <div class="mb-3">
            <label>Tên đăng nhập:</label>
            <input type="text" name="username" class="form-control form-control-sm" required>
        </div>
        <div class="mb-3">
            <label>Mật khẩu:</label>
            <input type="password" name="password" class="form-control form-control-sm" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100">Đăng nhập</button>
    </form>
<?php else: ?>


	
 <p style="display: flex; align-items: center; gap: 8px;">
  Chào, <b><?= htmlspecialchars($_SESSION['username']) ?></b> |
  <a href="logout.php" class="btn btn-danger btn-sm" style="width: 100px; padding: 2px 8px; font-size: 0.75rem; line-height: 1; margin-top: 0;">Đăng xuất</a>
</p>

		 <?php if ($_SESSION['role'] === 'admin'): ?>
		<div id="logContainer">
		<b style="font-size:0.75rem;">Control ID:</b>
    <?php
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_line']) && $isLoggedIn) {
    $lineToDelete = $_POST['delete_line'];
    $logFile = "anydeck.log";
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);
        $newLines = [];
        $found = false;
        
        foreach ($lines as $line) {
            if (!$found && trim($line) === trim($lineToDelete)) {
                $found = true; // Bỏ qua dòng đầu tiên trùng khớp
                continue;
            }
            $newLines[] = $line;
        }
        
        file_put_contents($logFile, implode("\n", $newLines));
    }
     
}

// Xử lý XÓA TẤT CẢ - THÊM ĐOẠN NÀY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all']) && $isLoggedIn) {
    $logFile = "anydeck.log";
    if (file_exists($logFile)) {
        file_put_contents($logFile, ""); // Ghi nội dung trống vào file
    }

  
}
$logFile = "anydeck.log";

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    $hasContent = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine !== '') {
            $hasContent = true;
            echo '<div class="log-line" data-line="' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '">';
            echo '<span>' . htmlspecialchars($trimmedLine, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '<button class="btn btn-danger btn-sm btn-delete-line" '
                . 'style="font-size:0.7rem;" '
                . 'data-line="' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '">'
                . 'Xóa</button>';
            echo '</div>';
        }
    }

    if (!$hasContent) {
        echo '<div class="text-muted" style="font-size: 0.75rem;">Chưa có nội dung</div>';
    }

} else {
    echo '<div class="text-muted" style="font-size: 0.75rem;">Chưa có nội dung</div>';
}

// Nếu có nội dung thì hiển thị nút "Xóa tất cả"
if ($hasContent) {
    echo '<span class="mt-3">';
    echo '<button id="deleteAllBtn" class="btn btn-danger btn-sm" '
        . 'style="font-size:0.7rem; padding:0.2rem 0.4rem;">'
        . '<i class="bi bi-trash"></i>Tất cả</button>';
    echo '</span>';
}



?>
   
    
    </div>
		
<?php endif; ?>
<?php endif; ?>

 <?php if ($_SESSION['role'] === 'admin'): ?>
<?php include __DIR__ . '/update-temp/manage_update.php'; ?>
<?php include 'show_cpu_warning.php'; ?>
 <?php endif; ?>


    </div>

</div>



<script>

document.getElementById("loginToggleBtn").addEventListener("click", function() {

    document.getElementById("loginSidebar").classList.add("show");

});



document.getElementById("closeLogin").addEventListener("click", function() {

    document.getElementById("loginSidebar").classList.remove("show");

});

</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  let lastCount = document.querySelectorAll('#logContainer .log-line').length;
  const container = document.getElementById('logContainer');

  function fetchLog() {
    fetch('./anydeck.log?ts=' + Date.now(), { cache: 'no-store' })
      .then(r => r.text())
      .then(txt => {
        const lines = txt.trim().split('\n').filter(l => l);
        if (lines.length > lastCount) {
          const newLines = lines.slice(lastCount);
          newLines.forEach(line => {
            const div = document.createElement('div');
            div.className = 'log-line';
            div.style.fontSize = '0.75rem';
            div.innerHTML = `
              <span>${line.replace(/&/g,'&amp;').replace(/</g,'&lt;')}</span>
              <form method="post" class="d-inline">
                <input type="hidden" name="delete_line" value="${line.replace(/"/g,'&quot;')}">
                <button type="submit" class="btn btn-danger btn-sm"
                        style="font-size:0.7rem; padding:0.2rem 0.4rem;">
                  Xóa
                </button>
              </form>`;
            container.appendChild(div);
          });
          lastCount = lines.length;
        }
      })
      .catch(console.error);
  }

  setInterval(fetchLog, 1500);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Xóa 1 dòng
    document.querySelectorAll('.btn-delete-line').forEach(button => {
        button.addEventListener('click', function () {
            const line = this.dataset.line;
            if (!confirm('Bạn có chắc chắn muốn xóa dòng này?')) return;

            fetch('delete_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ delete_line: line })
            })
            .then(res => res.text())
            .then(response => {
                if (response === 'OK') {
                    this.closest('.log-line').remove();
                } else {
                    alert('Không xóa được: ' + response);
                }
            })
            .catch(err => console.error(err));
        });
    });

    // Xóa tất cả
    document.getElementById('deleteAllBtn')?.addEventListener('click', function () {
        if (!confirm('Bạn có chắc chắn muốn xóa tất cả?')) return;

        fetch('delete_log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ delete_all: 1 })
        })
        .then(res => res.text())
        .then(response => {
            if (response === 'OK') {
                document.querySelectorAll('.log-line').forEach(el => el.remove());
            } else {
                alert('Không xóa được: ' + response);
            }
        })
        .catch(err => console.error(err));
    });
});
</script>


</body>


</html>



