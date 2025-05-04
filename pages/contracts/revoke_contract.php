<?php
session_start();
require_once __DIR__ . "/../../bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    $client_id = $_SESSION['user_id'];
    $conn=getDbConnection();
    // Fetch freelancer ID

    $stmt = $conn->prepare("SELECT freelancer_id FROM contracts WHERE project_id = ? AND client_id = ?");
    $stmt->execute([$project_id, $client_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$contract) {
        $_SESSION['error'] = "Unauthorized or contract not found.";
        header("Location: ../projects/list.php");
        exit;
    }

    $freelancer_id = $contract['freelancer_id'];

    // Delete contract
    $conn->query("DELETE FROM contracts WHERE project_id = $project_id");

    // Set project back to open
    $status_stmt = $conn->prepare("UPDATE projects SET status = 'open' WHERE project_id = ?");
    $status_stmt->execute([$project_id]);
    //Delete proposal
    $delteProposalStmt=$conn->prepare("DELETE FROM proposals WHERE project_id = ?");
    $delteProposalStmt->execute([$project_id]);
    // Insert notification for freelancer
    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
    $notif_stmt->bindParam(1, $freelancer_id, PDO::PARAM_INT);
    $notif_stmt->bindParam(2, $message, PDO::PARAM_STR);


    $_SESSION['success'] = "Award successfully revoked and freelancer notified.";
    header("Location: ../projects/list.php");
    exit;
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../projects/list.php");
    exit;
}
?>
