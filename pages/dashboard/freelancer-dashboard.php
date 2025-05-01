<?php
require_once __DIR__ . '/../../includes/header.php';
$baseUrl = '/improved';

if (!is_logged_in() || get_user_type() !== 'freelancer') {
    redirect($baseUrl.'/pages/auth/login.php', 'Unauthorized access. Please login as a freelancer.', 'danger');
}

$db = getDbConnection();
$user_id = get_current_user_id();

$totalProposals = 0;
$totalEarnings = 0;
$recentProposals = [];

if ($db) {
    // Total proposals
    $stmt = $db->prepare("SELECT COUNT(*) FROM proposals WHERE freelancer_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $totalProposals = $stmt->fetchColumn();

    // Total earnings from completed contracts
    $stmt2 = $db->prepare("SELECT SUM(total_amount) FROM contracts WHERE freelancer_id = :user_id AND status = 'completed'");
    $stmt2->execute(['user_id' => $user_id]);
    $totalEarnings = $stmt2->fetchColumn() ?? 0;

    // Recent proposals (last 5)
    $stmt3 = $db->prepare("SELECT p.title, pr.price, pr.estimated_completion_days, pr.submission_date
                           FROM proposals pr
                           JOIN projects p ON pr.project_id = p.project_id
                           WHERE pr.freelancer_id = :user_id
                           ORDER BY pr.submission_date DESC
                           LIMIT 5");
    $stmt3->execute(['user_id' => $user_id]);
    $recentProposals = $stmt3->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-5">
    <h2>👋 Welcome, <?php echo get_user_name(); ?></h2>
    <p class="text-muted">This is your freelancer dashboard. Here you can manage your profile, proposals, and contracts.</p>
    <p><strong>Total Proposals:</strong> <?php echo $totalProposals; ?></p>
    <p><strong>Total Earnings:</strong> <?php echo format_currency($totalEarnings); ?></p>

    <div class="grid mt-4">
        <div class="card">
            <div class="card-content">
                <h3>📄 My Proposals</h3>
                <p>Track the proposals you submitted to projects.</p>
                <a href="/pages/proposals/my_proposals.php" class="btn btn-sm">View Proposals</a>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <h3>🧑‍💻 My Profile</h3>
                <p>Update your freelancer profile, skills, and bio.</p>
                <a href="/pages/profile/edit.php" class="btn btn-sm">Edit Profile</a>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <h3>📊 My Contracts</h3>
                <p>See your active and completed contracts.</p>
                <a href="/pages/contracts/my_contracts.php" class="btn btn-sm">View Contracts</a>
            </div>
        </div>
    </div>

    <div class="card mt-5">
        <div class="card-content">
            <h3>🕒 Recent Proposals</h3>
            <?php if (empty($recentProposals)): ?>
                <p class="text-muted">You haven't submitted any proposals yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($recentProposals as $proposal): ?>
                        <li style="margin-bottom: 0.8rem;">
                            <strong><?php echo htmlspecialchars($proposal['title']); ?></strong><br>
                            <?php echo format_currency($proposal['price']); ?> &middot;
                            <?php echo $proposal['estimated_completion_days']; ?> days &middot;
                            <small><?php echo time_ago($proposal['submission_date']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
