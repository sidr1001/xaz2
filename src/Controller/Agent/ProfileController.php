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
        return $view->render($response, 'agent/profile.twig', [
            'data' => $data,
            'breadcrumbs' => [
                ['title' => 'Кабинет агента', 'url' => '/agent'],
                ['title' => 'Профиль'],
            ],
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $pdo = Database::getConnection();
        $in = (array)$request->getParsedBody();
        $pairs = [];
        foreach (self::FIELDS as $f) {
            $val = trim((string)($in[$f] ?? ''));
            if (in_array($f, ['contract_date_from','contract_date_to'], true)) {
                // Normalize empty/invalid dates to NULL to avoid SQLSTATE[22007]
                if ($val === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $val = null;
                }
            }
            $pairs[$f] = $val;
        }
        // Upsert into agent_profiles table
        $columns = implode(',', array_map(fn($k)=>"`$k`", array_keys($pairs)));
        $placeholders = implode(',', array_map(fn($k)=>":$k", array_keys($pairs)));
        $updates = implode(',', array_map(fn($k)=>"`$k`=VALUES(`$k`)", array_keys($pairs)));
        $sql = "INSERT INTO agent_profiles(agent_id,$columns) VALUES(:agent_id,$placeholders) ON DUPLICATE KEY UPDATE $updates";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':agent_id', $agentId, \PDO::PARAM_INT);
        foreach ($pairs as $k=>$v) { $stmt->bindValue(":$k", $v, $v===null?\PDO::PARAM_NULL:\PDO::PARAM_STR); }
        $stmt->execute();
        $accept = $request->getHeaderLine('Accept');
        if (stripos($accept, 'application/json') !== false || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Профиль агента сохранен'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }		
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
        // Provide absolute FS paths for images to PDF renderer
        $sigFs = dirname(__DIR__,3)."/public/uploads/agents/$agentId/signature.png";
        $stampFs = dirname(__DIR__,3)."/public/uploads/agents/$agentId/stamp.png";
        $tplData = ['profile'=>$data, 'signature_fs'=>$sigFs, 'stamp_fs'=>$stampFs];
        PdfService::renderTemplateToFile($request, 'documents/agent_contract.twig', $tplData, $out);
        return $response->withHeader('Location', str_replace(dirname(__DIR__,3).'/public','',$out))->withStatus(302);
    }

    public function uploadSignature(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $files = $request->getUploadedFiles();
        $file = $files['signature'] ?? null;
        if(!$file || $file->getError()!==UPLOAD_ERR_OK){ return $response->withHeader('Location','/agent/profile?error=sig')->withStatus(302);} 
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if(!in_array($ext, ['png','jpg','jpeg'], true)){ return $response->withHeader('Location','/agent/profile?error=sigtype')->withStatus(302);} 
        $dir = dirname(__DIR__,3)."/public/uploads/agents/$agentId";
        if(!is_dir($dir)) mkdir($dir, 0777, true);
        $file->moveTo($dir.'/signature.png');
        $accept = $request->getHeaderLine('Accept');
        if (stripos($accept, 'application/json') !== false || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Подпись сохранен'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }
        return $response->withHeader('Location','/agent/profile')->withStatus(302);
    }

    public function uploadStamp(Request $request, Response $response): Response
    {
        $agentId = (int)($_SESSION['agent_id'] ?? 0);
        $files = $request->getUploadedFiles();
        $file = $files['stamp'] ?? null;
        if(!$file || $file->getError()!==UPLOAD_ERR_OK){ return $response->withHeader('Location','/agent/profile?error=stamp')->withStatus(302);} 
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if(!in_array($ext, ['png','jpg','jpeg'], true)){ return $response->withHeader('Location','/agent/profile?error=stamptype')->withStatus(302);} 
        $dir = dirname(__DIR__,3)."/public/uploads/agents/$agentId";
        if(!is_dir($dir)) mkdir($dir, 0777, true);
        $file->moveTo($dir.'/stamp.png');
        $accept = $request->getHeaderLine('Accept');
        if (stripos($accept, 'application/json') !== false || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $response->getBody()->write(json_encode(['ok'=>true,'message'=>'Печать сохранена'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type','application/json');
        }
        return $response->withHeader('Location','/agent/profile')->withStatus(302);
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

