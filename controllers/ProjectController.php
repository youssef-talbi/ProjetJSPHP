<?php
class ProjectController {
    private $model;
    private $auth;
    private $baseUrl;

    public function __construct(ProjectModel $model, $auth, $baseUrl) {
        $this->model = $model;
        $this->auth = $auth;
        $this->baseUrl = $baseUrl;
    }

    public function viewAction(int $projectId) {
        try {
            // Get all project data from Model
            $projectData = $this->model->getFullProjectDetails($projectId);

            if (!$projectData) {
                $this->redirectToList('project_not_found');
            }

            // Prepare view variables
            $viewData = [
                'project' => $projectData,
                'skills' => $projectData['skills'],
                'attachments' => $projectData['attachments'],
                'proposals' => $projectData['proposals'],
                'client_stats' => $projectData['client_stats'],
                'baseUrl' => $this->baseUrl,
                'isProjectOwner' => $this->isProjectOwner($projectData),
                'hasSubmittedProposal' => $this->checkProposalSubmission($projectId),
                'showProposals' => $this->shouldShowProposals($projectData),
                'showProposalButton' => $this->shouldShowProposalButton($projectData),
                'csrfToken' => $this->generateCsrfToken()
            ];

            // Extract variables for the view
            extract($viewData);

            // Load view file
            require __DIR__ . "/../../views/projects/view.php";

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $this->redirectToList('database_error');
        }
    }

    private function shouldShowProposals(array $project): bool {
        return $this->auth->isLoggedIn() &&
            ($this->isProjectOwner($project) || $this->auth->isAdmin());
    }

    private function shouldShowProposalButton(array $project): bool {
        return $project['status'] === 'open' &&
            $this->auth->isFreelancer() &&
            !$this->isProjectOwner($project);
    }

    private function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    private function isProjectOwner(array $project): bool {
        return $this->auth->isLoggedIn() &&
            $this->auth->getUser()['user_id'] == $project['client_user_id'];
    }

    private function checkProposalSubmission(int $projectId): bool {
        if (!$this->auth->isLoggedIn() || !$this->auth->isFreelancer()) {
            return false;
        }

        $userId = $this->auth->getUser()['user_id'];
        $stmt = $this->model->getDb()->prepare("SELECT COUNT(*) 
                                               FROM proposals 
                                               WHERE project_id = :project_id 
                                               AND freelancer_id = :user_id");
        $stmt->execute([':project_id' => $projectId, ':user_id' => $userId]);
        return $stmt->fetchColumn() > 0;
    }

    private function redirectToList(string $error) {
        header("Location: {$this->baseUrl}/projects/list?error=$error");
        exit;
    }

    private function loadView(string $viewPath, array $data) {
        extract($data);
        require __DIR__ . "/../pages/projects/view.php";
    }
}