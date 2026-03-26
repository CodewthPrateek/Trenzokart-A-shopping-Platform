<?php
require '../config.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

$vendors_result = mysqli_query($conn, "SELECT id, name, shop_name FROM vendors WHERE status='approved' ORDER BY name");
$vendors = [];
while ($row = mysqli_fetch_assoc($vendors_result)) {
    $vendors[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {

    $type       = $_POST['type'];
    $title      = mysqli_real_escape_string($conn, trim($_POST['title']));
    $message    = mysqli_real_escape_string($conn, trim($_POST['message']));
    $priority   = $_POST['priority'];
    $vendor_id  = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 'NULL';
    $is_pinned  = isset($_POST['is_pinned']) ? 1 : 0;
    $valid_until = !empty($_POST['valid_until']) ? "'" . $_POST['valid_until'] . "'" : 'NULL';
    $admin_id   = $_SESSION['admin_id'];

    if (empty($title) || empty($message)) {
        $error = "Title aur message required hai!";
    } else {
        $vid_val = ($vendor_id === 'NULL') ? 'NULL' : $vendor_id;

        mysqli_query($conn, "INSERT INTO notifications
        (type,title,message,priority,vendor_id,is_pinned,valid_until,created_by)
        VALUES
        ('$type','$title','$message','$priority',$vid_val,'$is_pinned',$valid_until,'$admin_id')");

        $success = "Notification sent successfully!";
    }
}

if (isset($_GET['delete'])) {
    $nid = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM notifications WHERE id='$nid'");
    mysqli_query($conn, "DELETE FROM notification_reads WHERE notification_id='$nid'");
    $success = "Notification deleted!";
}

if (isset($_GET['pin'])) {
    $nid = intval($_GET['pin']);
    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_pinned FROM notifications WHERE id='$nid'"));
    $new_pin = $current['is_pinned'] ? 0 : 1;
    mysqli_query($conn, "UPDATE notifications SET is_pinned='$new_pin' WHERE id='$nid'");
    header("Location: notifications.php");
    exit();
}

$notifs_result = mysqli_query($conn, "
SELECT n.*,v.name as vendor_name,v.shop_name,
(SELECT COUNT(*) FROM notification_reads nr WHERE nr.notification_id=n.id) as read_count
FROM notifications n
LEFT JOIN vendors v ON n.vendor_id=v.id
ORDER BY n.is_pinned DESC,n.created_at DESC
");

$notifications = [];
while ($row = mysqli_fetch_assoc($notifs_result)) {
    $notifications[] = $row;
}

$total_vendors = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM vendors WHERE status='approved'"))['c'];
$total_notifs = count($notifications);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: Arial;
            background: #f5efe6;
            color: #2d1a0a;
        }

        nav {
            background: #1a0f02;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            color: white;
        }

        .main {
 
            margin-top: 20px;
            padding: 30px;
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 30px;
        }

        .compose-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .btn-send {
            width: 100%;
            padding: 10px;
            background: #d4622a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        /* FIXED PART */

        .notifs-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        /* notification card */

        .notif-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .notif-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .notif-message {
            color: #555;
            margin-bottom: 8px;
        }

        .notif-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-pin {
            background: #ffeaa7;
            color: #8e6b00;
        }

        .btn-del {
            background: #ffdddd;
            color: #b30000;
        }

        @media(max-width:768px){
          .main { grid-template-columns:1fr; padding:15px; margin-top:80px; }
          .stats-row { grid-template-columns:repeat(3,1fr); }
          nav { padding:0 20px; }
        }
        @media(max-width:480px){
          .stats-row { grid-template-columns:repeat(2,1fr); }
          nav { height:auto; flex-wrap:wrap; padding:0.6rem 15px; gap:0.5rem; }
          .main { margin-top:100px; }
        }
        </style>
</head>

<body>

    <nav>
        <div>TrenzoKart Admin</div>
        <div>
            <a href="dashboard.php" style="color:white;margin-right:15px;">Dashboard</a>
            <a href="logout.php" style="color:white;">Logout</a>
        </div>
    </nav>

    <div class="main">

        <div>

            <div class="stats-row">
                <div class="stat-card">
                    <h2><?= $total_notifs ?></h2>
                    <p>Total Notifications</p>
                </div>

                <div class="stat-card">
                    <h2><?= $total_vendors ?></h2>
                    <p>Active Vendors</p>
                </div>

                <div class="stat-card">
                    <h2><?= count(array_filter($notifications, fn($n) => $n['is_pinned'])) ?></h2>
                    <p>Pinned</p>
                </div>
            </div>

            <div class="compose-card">

                <h3>Send Notification</h3>

                <form method="POST">

                    <input type="hidden" name="send_notification" value="1">
                    <input type="hidden" name="type" value="broadcast">

                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Valid Until</label>
                        <input type="date" name="valid_until">
                    </div>

                    <label>
                        <input type="checkbox" name="is_pinned">
                        Pin notification
                    </label>

                    <br><br>

                    <button class="btn-send">Send</button>

                </form>

            </div>
        </div>

        <div class="notifs-panel">

            <h2>Sent Notifications</h2>

            <?php if (empty($notifications)): ?>

                <p>No notifications yet</p>

            <?php else: ?>

                <?php foreach ($notifications as $n): ?>

                    <div class="notif-card">

                        <div class="notif-title">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>

                        <div class="notif-message">
                            <?= nl2br(htmlspecialchars($n['message'])) ?>
                        </div>

                        <div class="notif-footer">

                            <span>
                                <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
                            </span>

                            <div>

                                <a class="action-btn btn-pin" href="?pin=<?= $n['id'] ?>">
                                    <?= $n['is_pinned'] ? 'Unpin' : 'Pin' ?>
                                </a>

                                <a class="action-btn btn-del"
                                    href="?delete=<?= $n['id'] ?>"
                                    onclick="return confirm('Delete notification?')">
                                    Delete
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>