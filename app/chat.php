#!/usr/bin/env php
<?php

declare(strict_types=1);

$MCP_URL = 'http://localhost:8080';
$OLLAMA_URL = 'http://ollama:11434/api/chat';
$SOUL_FILE = dirname(__FILE__, 2) . '/src/mcp/SOUL.md';

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
    $erro = curl_error($ch);
    curl_close($ch);
    
    if ($erro) {
        return null;
    }
    
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
    $erro = curl_error($ch);
    curl_close($ch);
    
    if ($code !== 200 || $erro) {
        file_put_contents('/tmp/ollama_debug.json', $json);
        file_put_contents('/tmp/ollama_response.txt', "HTTP $code\nErro: $erro\nResposta: $res");
        return null;
    }
    
    return json_decode($res, true);
}

function lerEntrada(string $prompt): string
{
    echo $prompt;
    return trim(fgets(STDIN));
}

// Apresentação
echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  LINA - Assistente de Analise de Credito (via Ollama)\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

// Verificar MCP e iniciar se necessário
echo "Conectando ao servidor MCP... ";
$mcpTools = chamarMcp('/tools');

if (!is_array($mcpTools) || empty($mcpTools)) {
    echo "não encontrado.\n";
    echo "Iniciando servidor MCP em background...\n";
    
    $mcpServer = dirname(__FILE__, 2) . '/src/mcp/server.php';
    $logFile = '/tmp/mcp_server.log';
    
    // Inicia servidor PHP em background
    $cmd = "php -S 0.0.0.0:8080 {$mcpServer} > {$logFile} 2>&1 &";
    exec($cmd);
    
    // Aguarda servidor iniciar
    $tentativas = 10;
    while ($tentativas > 0) {
        usleep(500000); // 500ms
        $mcpTools = chamarMcp('/tools');
        if (is_array($mcpTools) && !empty($mcpTools)) {
            break;
        }
        $tentativas--;
        echo ".";
    }
    
    if (!is_array($mcpTools) || empty($mcpTools)) {
        echo " [FALHA]\n";
        echo "\n[!] Nao foi possivel iniciar o servidor MCP.\n";
        echo "Tente manualmente: php -S 0.0.0.0:8080 {$mcpServer}\n\n";
        exit(1);
    }
}

echo "[OK] " . count($mcpTools) . " ferramentas\n";

// Preparar ferramentas para Ollama
$ollamaTools = [];
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

// Carregar system prompt
$systemPrompt = file_exists($SOUL_FILE) 
    ? file_get_contents($SOUL_FILE) 
    : "Você é a Lina, assistente de análise de crédito.";

$mensagens = [
    ['role' => 'system', 'content' => $systemPrompt],
];

echo "\n";
echo "Olá! Eu sou a Lina, sua assistente de análise de crédito.\n";
echo "Posso ajudar com: análise de clientes, verificação de fraude,\n";
echo "cálculo de risco e sugestão de oferta de cartão.\n";
echo "\n";
echo "Digite 'sair' para encerrar.\n";
echo "───────────────────────────────────────────────────────────────\n";

while (true) {
    echo "\n";
    $input = lerEntrada("Você: ");
    
    if (strtolower($input) === 'sair') {
        echo "\nAte logo! Foi um prazer ajudar.\n\n";
        break;
    }
    
    if (empty($input)) {
        continue;
    }
    
    $mensagens[] = ['role' => 'user', 'content' => $input];
    
    echo "\nPensando...\n";
    
    // Detectar se precisa de ferramentas
    $precisaFerramentas = preg_match(
        '/\b(analis|verific|list|risco|fraude|cliente|busca|calcul|oferta)\w*/i',
        $input
    );
    
    $toolsParaEnviar = $precisaFerramentas ? $ollamaTools : [];
    
    // Loop de tool-calling
    $maxTurnos = 5;
    
    for ($turno = 0; $turno < $maxTurnos; $turno++) {
        $resposta = chamarOllama('lina', $mensagens, $toolsParaEnviar);
        
        if (!$resposta || !isset($resposta['message'])) {
            echo "\n[ERRO] Falha ao comunicar com o Ollama.\n";
            echo "Verifique se o serviço está rodando.\n";
            break;
        }
        
        $msg = $resposta['message'];
        $mensagens[] = $msg;
        
        // Verifica chamadas de ferramentas
        if (!empty($msg['tool_calls'])) {
            foreach ($msg['tool_calls'] as $chamada) {
                $nome = $chamada['function']['name'];
                $args = $chamada['function']['arguments'];
                
                echo "[TOOL] Usando: {$nome}...\n";
                
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
            // Resposta final
            echo "\n";
            echo "Lina: " . ($msg['content'] ?? '(sem resposta)') . "\n";
            break;
        }
    }
}
