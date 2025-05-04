<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!is_logged_in() || get_user_type() !== 'client') {
    redirect('/pages/auth/login.php?error=unauthorized');
    exit;
}

$client_id = get_current_user_id();
$proposal_id = isset($_GET['proposal_id']) ? filter_input(INPUT_GET, 'proposal_id', FILTER_VALIDATE_INT) : null;

if (!$proposal_id) {
    redirect('/pages/dashboard/client-dashboard.php?error=invalid_proposal');
    exit;
}

$db = getDbConnection();
if (!$db) {
    redirect('/pages/dashboard/client-dashboard.php?error=db_error');
    exit;
}

try {
    // Get proposal + project + user info
    $stmt = $db->prepare("
        SELECT p.project_id, p.freelancer_id, p.price, p.estimated_completion_days, 
               pr.client_id, pr.project_type, pr.description
        FROM proposals p
        JOIN projects pr ON p.project_id = pr.project_id
        WHERE p.proposal_id = :proposal_id
    ");
    $stmt->execute([':proposal_id' => $proposal_id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal || $proposal['client_id'] != $client_id) {
        redirect('/pages/projects/list.php?error=unauthorized_access');
        exit;
    }

    // Check if already awarded
    $check = $db->prepare("SELECT contract_id FROM contracts WHERE proposal_id = :proposal_id");
    $check->execute([':proposal_id' => $proposal_id]);
    if ($check->fetch()) {
        redirect('/pages/projects/view.php?id=' . $proposal['project_id'] . '&error=already_awarded');
        exit;
    }

    // Insert contract
    $contract = $db->prepare("
        INSERT INTO contracts (project_id, client_id, freelancer_id, proposal_id, start_date, status, terms, contract_type, total_amount)
        VALUES (:project_id, :client_id, :freelancer_id, :proposal_id, NOW(), 'active', :terms, :contract_type, :total_amount)
    ");
    $contract->execute([
        ':project_id' => $proposal['project_id'],
        ':client_id' => $client_id,
        ':freelancer_id' => $proposal['freelancer_id'],
        ':proposal_id' => $proposal_id,
        ':terms' => $proposal['description'], // Using project desc as default terms
        ':contract_type' => $proposal['project_type'],
        ':total_amount' => $proposal['price']
    ]);

    // Update project status
    $db->prepare("UPDATE projects SET status = 'in progress' WHERE project_id = :pid")
        ->execute([':pid' => $proposal['project_id']]);

    // Notify freelancer
    create_notification(
        $proposal['freelancer_id'],
        'contract',
        '🎉 You have been awarded a new contract!',
        $proposal['project_id']
    );

    redirect('/pages/projects/view.php?id=' . $proposal['project_id'] . '&message=project_awarded');
    exit;

} catch (PDOException $e) {
    error_log("Award project error: " . $e->getMessage());
    redirect('/pages/dashboard/client-dashboard.php?error=db_error');
    exit;
}
