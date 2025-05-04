<?php
class ProjectModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getFullProjectDetails(int $projectId) {
        // Main project data
        $stmt = $this->db->prepare("SELECT p.*, c.category_name, 
                                  u.user_id as client_user_id, u.first_name, u.last_name, u.registration_date,
                                  cp.company_name, cp.website
                           FROM projects p
                           JOIN categories c ON p.category_id = c.category_id
                           JOIN users u ON p.client_id = u.user_id
                           LEFT JOIN client_profiles cp ON u.user_id = cp.user_id
                           WHERE p.project_id = :project_id");
        $stmt->execute([':project_id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) return null;

        // Related data
        $project['skills'] = $this->getProjectSkills($projectId);
        $project['attachments'] = $this->getProjectAttachments($projectId);
        $project['proposals'] = $this->getProjectProposals($projectId);
        $project['client_stats'] = $this->getClientStats($project['client_user_id']);

        return $project;
    }

    private function getProjectSkills(int $projectId) {
        $stmt = $this->db->prepare("SELECT s.skill_name 
                                  FROM skills s 
                                  JOIN project_skills ps ON s.skill_id = ps.skill_id 
                                  WHERE ps.project_id = :project_id");
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getProjectAttachments(int $projectId) {
        $stmt = $this->db->prepare("SELECT attachment_id, file_name, file_path, file_size 
                                  FROM project_attachments 
                                  WHERE project_id = :project_id");
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getProjectProposals(int $projectId) {
        $stmt = $this->db->prepare("SELECT pr.*, u.user_id as freelancer_user_id, 
                                  u.first_name, u.last_name, fp.headline, u.profile_picture,
                                  COALESCE(AVG(r.rating), 0) as avg_rating,
                                  COUNT(r.review_id) as review_count
                           FROM proposals pr
                           JOIN users u ON pr.freelancer_id = u.user_id
                           JOIN freelancer_profiles fp ON u.user_id = fp.user_id
                           LEFT JOIN reviews r ON u.user_id = r.reviewee_id
                           WHERE pr.project_id = :project_id
                           GROUP BY pr.proposal_id");
        $stmt->execute([':project_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getClientStats(int $clientId) {
        $stmt = $this->db->prepare("SELECT COUNT(p.project_id) as projects_posted,
                                  COALESCE(SUM(c.total_amount), 0) as total_spent
                           FROM projects p
                           LEFT JOIN contracts c ON p.project_id = c.project_id
                           WHERE p.client_id = :client_id");
        $stmt->execute([':client_id' => $clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}