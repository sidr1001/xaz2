<?php
declare(strict_types=1);

namespace App\Controller\Agent;

use App\Service\Database;
use App\Service\PdfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ProfileController
{
    private const FIELDS = [
        'email','phone','first_name','last_name','middle_name','inn','ceo','ceo_basis','org_name','org_short_name','legal_address','contract_number','contract_date_from','contract_date_to','email_alt','phone_alt','actual_address','okpo','account_number','account_currency','bank_name','bank_code','bank_address','ogrn','bank_inn','kpp'
    ];

    public function form(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $data = $this->getProfile($pdo, $agentId);
        $view = Twig::fromRequest($request);
        return $view->render($response, 'agent/profile.twig', ['data' => $data]);
    }

    public function save(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $in = (array)$request->getParsedBody();
        $pairs = [];
        foreach (self::FIELDS as $f) {
            $pairs[$f] = trim((string)($in[$f] ?? ''));
        }
        // Upsert into agent_profiles table
        $columns = implode(',', array_map(fn($k)=>"`$k`", array_keys($pairs)));
        $placeholders = implode(',', array_map(fn($k)=>":$k", array_keys($pairs)));
        $updates = implode(',', array_map(fn($k)=>"`$k`=VALUES(`$k`)", array_keys($pairs)));
        $sql = "INSERT INTO agent_profiles(agent_id,$columns) VALUES(:agent_id,$placeholders) ON DUPLICATE KEY UPDATE $updates";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':agent_id', $agentId, \PDO::PARAM_INT);
        foreach ($pairs as $k=>$v) { $stmt->bindValue(":$k", $v); }
        $stmt->execute();
        return $response->withHeader('Location','/agent/profile')->withStatus(302);
    }

    public function contract(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $data = $this->getProfile($pdo, $agentId);
        // If admin uploaded custom file, redirect to it; else generate from template
        $file = dirname(__DIR__, 3)."/public/uploads/agents/$agentId/contract.pdf";
        if (is_file($file)) {
            return $response->withHeader('Location', str_replace(dirname(__DIR__,3).'/public','',$file))->withStatus(302);
        }
        $out = dirname(__DIR__, 3)."/public/uploads/agents/$agentId/contract_generated.pdf";
        PdfService::renderTemplateToFile($request, 'documents/agent_contract.twig', ['profile'=>$data], $out);
        return $response->withHeader('Location', str_replace(dirname(__DIR__,3).'/public','',$out))->withStatus(302);
    }

    private function getProfile(\PDO $pdo, int $agentId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM agent_profiles WHERE agent_id=:id');
        $stmt->execute([':id'=>$agentId]);
        $row = $stmt->fetch() ?: [];
        $row['agent_id'] = $agentId;
        foreach (self::FIELDS as $f) { if (!array_key_exists($f, $row)) { $row[$f] = ''; } }
        return $row;
    }
}

