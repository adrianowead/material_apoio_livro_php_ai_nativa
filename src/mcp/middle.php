<?php

declare(strict_types=1);

set_time_limit(300);

$MCP_URL = 'http://localhost:8080';
$OLLAMA_URL = 'http://ollama:11434/api/chat';
$SOUL_FILE = __DIR__ . '/SOUL.md';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function enviarEvento(string $tipo, mixed $dados): void
{
    echo json_encode(['type' => $tipo, 'data' => $dados]) . "\n";
    flush();
}

function chamarMcp(string $endpoint, ?array $dados = null): mixed
{
    global $MCP_URL;
    
    $ch = curl_init($MCP_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    if ($dados !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $res = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($res);
}

function chamarOllama(string $modelo, array $mensagens, array $ferramentas = []): ?array
{
    global $OLLAMA_URL;
    
    $payload = [
        'model' => $modelo,
        'messages' => $mensagens,
        'stream' => false,
    ];
    
    if (!empty($ferramentas)) {
        $payload['tools'] = $ferramentas;
    }
    
    $json = json_encode($payload);
    
    $ch = curl_init($OLLAMA_URL);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json),
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200) {
        return null;
    }
    
    return json_decode($res, true);
}

// Rota principal: POST /chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/chat') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/x-ndjson');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $modelo = $input['model'] ?? 'lina';
    $mensagens = $input['messages'] ?? [];
    
    enviarEvento('status', 'Carregando ferramentas do MCP...');
    
    // 1. Buscar ferramentas do MCP
    $mcpTools = chamarMcp('/tools');
    $ollamaTools = [];
    
    if (is_array($mcpTools)) {
        foreach ($mcpTools as $tool) {
            $ollamaTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters,
                ],
            ];
        }
    }
    
    // 2. Preparar mensagens (injetar system prompt)
    $systemPrompt = file_exists($SOUL_FILE) 
        ? file_get_contents($SOUL_FILE) 
        : "Você é um assistente de análise de crédito.";
    
    if (empty($mensagens) || $mensagens[0]['role'] !== 'system') {
        array_unshift($mensagens, ['role' => 'system', 'content' => $systemPrompt]);
    }
    
    // 3. Loop de tool-calling
    $maxTurnos = 5;
    
    for ($turno = 0; $turno < $maxTurnos; $turno++) {
        enviarEvento('status', 'Pensando...');
        
        $resposta = chamarOllama($modelo, $mensagens, $ollamaTools);
        
        if (!$resposta || !isset($resposta['message'])) {
            enviarEvento('error', 'Falha ao comunicar com o modelo.');
            exit;
        }
        
        $msg = $resposta['message'];
        $mensagens[] = $msg;
        
        // Verificar chamadas de ferramenta
        if (!empty($msg['tool_calls'])) {
            foreach ($msg['tool_calls'] as $chamada) {
                $nome = $chamada['function']['name'];
                $args = $chamada['function']['arguments'];
                
                enviarEvento('tool_use', ['name' => $nome, 'args' => $args]);
                enviarEvento('status', "Executando: $nome...");
                
                $resultado = chamarMcp('/execute', ['tool' => $nome, 'args' => $args]);
                
                $conteudo = isset($resultado->output) 
                    ? json_encode($resultado->output) 
                    : json_encode($resultado);
                
                $mensagens[] = [
                    'role' => 'tool',
                    'content' => $conteudo,
                    'name' => $nome,
                ];
            }
        } else {
            enviarEvento('final', ['message' => $msg, 'history' => $mensagens]);
            exit;
        }
    }
    
    enviarEvento('error', 'Limite de turnos excedido.');
    exit;
}
