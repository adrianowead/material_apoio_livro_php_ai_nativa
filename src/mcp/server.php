<?php

declare(strict_types=1);

set_time_limit(300);

$ROOT = dirname(__FILE__, 3);

require_once $ROOT . '/src/rubixml/vendor/autoload.php';
require_once $ROOT . '/src/fann/inferir.php';
require_once $ROOT . '/src/rubixml/inferir.php';
require_once $ROOT . '/src/ahpd/decidir.php';

use App\FANN;
use App\Rubix;
use App\AHPd;

// Modelos pré-carregados (singleton)
$MODELOS = null;

function carregarModelos(): array
{
    global $MODELOS, $ROOT;
    
    if ($MODELOS !== null) {
        return $MODELOS;
    }
    
    $MODELOS = [
        'fann'  => FANN\carregar_modelo($ROOT . '/models/fann/credit_fraud.net'),
        'rubix' => Rubix\carregar_modelo($ROOT . '/models/rubixml/credit_risk.rbx'),
    ];
    
    return $MODELOS;
}

function normalizar(float $renda, float $divida, float $score, float $emprego, float $idade): array
{
    return [
        min($renda / 50000, 1.0),
        min($divida / 200000, 1.0),
        $score / 1000,
        min($emprego / 480, 1.0),
        min($idade / 100, 1.0),
    ];
}

// Definição das ferramentas MCP
$tools = [
    'analisar_cliente' => [
        'description' => 'Análise completa de crédito: verifica fraude (FANN), calcula risco (RubixML) e sugere oferta (AHPd).',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'renda'   => ['type' => 'number', 'description' => 'Renda mensal em reais'],
                'divida'  => ['type' => 'number', 'description' => 'Dívida total em reais'],
                'score'   => ['type' => 'number', 'description' => 'Score de crédito (0-1000)'],
                'emprego' => ['type' => 'number', 'description' => 'Tempo de emprego em meses'],
                'idade'   => ['type' => 'number', 'description' => 'Idade em anos'],
            ],
            'required' => ['renda', 'divida', 'score', 'emprego', 'idade'],
        ],
    ],
    
    'verificar_fraude' => [
        'description' => 'Detecta anomalias/fraudes usando rede neural FANN.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'renda'   => ['type' => 'number', 'description' => 'Renda mensal em reais'],
                'divida'  => ['type' => 'number', 'description' => 'Dívida total em reais'],
                'score'   => ['type' => 'number', 'description' => 'Score de crédito (0-1000)'],
                'emprego' => ['type' => 'number', 'description' => 'Tempo de emprego em meses'],
                'idade'   => ['type' => 'number', 'description' => 'Idade em anos'],
            ],
            'required' => ['renda', 'divida', 'score', 'emprego', 'idade'],
        ],
    ],
    
    'calcular_risco' => [
        'description' => 'Calcula probabilidade de inadimplência usando RubixML.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'renda'   => ['type' => 'number', 'description' => 'Renda mensal em reais'],
                'divida'  => ['type' => 'number', 'description' => 'Dívida total em reais'],
                'score'   => ['type' => 'number', 'description' => 'Score de crédito (0-1000)'],
                'emprego' => ['type' => 'number', 'description' => 'Tempo de emprego em meses'],
                'idade'   => ['type' => 'number', 'description' => 'Idade em anos'],
            ],
            'required' => ['renda', 'divida', 'score', 'emprego', 'idade'],
        ],
    ],
    
    'decidir_oferta' => [
        'description' => 'Sugere limite de crédito (Gold/Silver/Bronze) usando AHPd.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'renda'    => ['type' => 'number', 'description' => 'Renda mensal em reais'],
                'divida'   => ['type' => 'number', 'description' => 'Dívida total em reais'],
                'score'    => ['type' => 'number', 'description' => 'Score de crédito (0-1000)'],
                'emprego'  => ['type' => 'number', 'description' => 'Tempo de emprego em meses'],
                'idade'    => ['type' => 'number', 'description' => 'Idade em anos'],
                'prob_bom' => ['type' => 'number', 'description' => 'Probabilidade de bom pagador (0-1)'],
            ],
            'required' => ['renda', 'divida', 'score', 'emprego', 'idade', 'prob_bom'],
        ],
    ],
    
    'buscar_cliente' => [
        'description' => 'Busca dados pessoais e financeiros de um cliente pelo ID.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'ID do cliente (ex: 1001)'],
            ],
            'required' => ['id'],
        ],
    ],
    
    'listar_clientes' => [
        'description' => 'Lista todos os clientes cadastrados com nome e ID.',
        'parameters' => [
            'type' => 'object',
            'properties' => (object)[],
        ],
    ],
];

// Executores das ferramentas
function executar_analisar_cliente(array $args): array
{
    $modelos = carregarModelos();
    $norm = normalizar($args['renda'], $args['divida'], $args['score'], $args['emprego'], $args['idade']);
    
    // 1. FANN
    $score_fann = FANN\inferir($modelos['fann'], $norm);
    $classe_fann = FANN\classificar($score_fann);
    
    // 2. RubixML
    [$classe_rubix, $probs] = Rubix\inferir($modelos['rubix'], $norm);
    $prob_bom = $probs['bom'] ?? 0.0;
    
    // 3. Decisão
    $decisao = 'BLOQUEADO';
    $oferta = null;
    
    if ($classe_fann === 'NORMAL' && $classe_rubix === 'bom') {
        $rank = AHPd\decidir_limite([
            'renda'    => $args['renda'],
            'divida'   => $args['divida'],
            'score'    => $args['score'],
            'emprego'  => $args['emprego'],
            'idade'    => $args['idade'],
            'prob_bom' => $prob_bom,
        ]);
        
        $score_cli = $rank['Cliente Atual'] ?? 0;
        $score_gold = $rank['Benchmark Gold'] ?? 0;
        $score_silver = $rank['Benchmark Silver'] ?? 0;
        
        if ($score_cli >= $score_gold * 0.9) {
            $oferta = 'GOLD';
        } elseif ($score_cli >= $score_silver * 0.9) {
            $oferta = 'SILVER';
        } else {
            $oferta = 'BRONZE';
        }
        $decisao = 'APROVADO';
    } elseif ($classe_fann !== 'NORMAL') {
        $decisao = 'FRAUDE_DETECTADA';
    } else {
        $decisao = 'RISCO_ALTO';
    }
    
    return [
        'decisao' => $decisao,
        'oferta'  => $oferta,
        'fann'    => ['score' => $score_fann, 'classe' => $classe_fann],
        'rubix'   => ['classe' => $classe_rubix, 'prob_bom' => round($prob_bom, 4)],
    ];
}

function executar_verificar_fraude(array $args): array
{
    $modelos = carregarModelos();
    $norm = normalizar($args['renda'], $args['divida'], $args['score'], $args['emprego'], $args['idade']);
    
    $score = FANN\inferir($modelos['fann'], $norm);
    $classe = FANN\classificar($score);
    
    return ['score' => $score, 'classe' => $classe];
}

function executar_calcular_risco(array $args): array
{
    $modelos = carregarModelos();
    $norm = normalizar($args['renda'], $args['divida'], $args['score'], $args['emprego'], $args['idade']);
    
    [$classe, $probs] = Rubix\inferir($modelos['rubix'], $norm);
    
    return ['classe' => $classe, 'probabilidades' => $probs];
}

function executar_decidir_oferta(array $args): array
{
    $rank = AHPd\decidir_limite([
        'renda'    => $args['renda'],
        'divida'   => $args['divida'],
        'score'    => $args['score'],
        'emprego'  => $args['emprego'],
        'idade'    => $args['idade'],
        'prob_bom' => $args['prob_bom'],
    ]);
    
    $score_cli = $rank['Cliente Atual'] ?? 0;
    $score_gold = $rank['Benchmark Gold'] ?? 0;
    $score_silver = $rank['Benchmark Silver'] ?? 0;
    
    if ($score_cli >= $score_gold * 0.9) {
        $sugestao = 'GOLD';
    } elseif ($score_cli >= $score_silver * 0.9) {
        $sugestao = 'SILVER';
    } else {
        $sugestao = 'BRONZE';
    }
    
    return ['sugestao' => $sugestao, 'ranking' => $rank];
}

function carregarCsvClientes(): array
{
    global $ROOT;
    $clientes = [];
    
    $csvFinanceiro = $ROOT . '/app/clientes.csv';
    $csvDados = $ROOT . '/app/clientes_dados.csv';
    
    // Carregar dados financeiros
    if (file_exists($csvFinanceiro)) {
        $linhas = file($csvFinanceiro, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_shift($linhas);
        foreach ($linhas as $linha) {
            $cols = explode(';', $linha);
            if (count($cols) >= 6) {
                $clientes[$cols[0]] = [
                    'id' => $cols[0],
                    'renda' => (float)$cols[1],
                    'divida' => (float)$cols[2],
                    'score' => (float)$cols[3],
                    'tempo_emprego' => (int)$cols[4],
                    'idade' => (int)$cols[5],
                ];
            }
        }
    }
    
    // Carregar dados pessoais
    if (file_exists($csvDados)) {
        $linhas = file($csvDados, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_shift($linhas);
        foreach ($linhas as $linha) {
            $cols = explode(';', $linha);
            if (count($cols) >= 5 && isset($clientes[$cols[0]])) {
                $clientes[$cols[0]]['nome'] = $cols[1];
                $clientes[$cols[0]]['profissao'] = $cols[2];
                $clientes[$cols[0]]['genero'] = $cols[3];
                $clientes[$cols[0]]['data_nascimento'] = $cols[4];
            }
        }
    }
    
    return $clientes;
}

function executar_buscar_cliente(array $args): array
{
    $clientes = carregarCsvClientes();
    $id = (string)$args['id'];
    
    if (!isset($clientes[$id])) {
        return ['erro' => 'Cliente não encontrado'];
    }
    
    return $clientes[$id];
}

function executar_listar_clientes(array $args): array
{
    $clientes = carregarCsvClientes();
    $lista = [];
    
    foreach ($clientes as $c) {
        $lista[] = [
            'id' => $c['id'],
            'nome' => $c['nome'] ?? 'Sem nome',
        ];
    }
    
    return $lista;
}

// Servidor HTTP
if (php_sapi_name() === 'cli-server') {
    header('Content-Type: application/json');
    
    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET /tools - Lista ferramentas
    if ($method === 'GET' && $uri === '/tools') {
        $lista = [];
        foreach ($tools as $nome => $def) {
            $lista[] = array_merge(['name' => $nome], $def);
        }
        echo json_encode($lista);
        exit;
    }
    
    // POST /execute - Executa ferramenta
    if ($method === 'POST' && $uri === '/execute') {
        $input = json_decode(file_get_contents('php://input'), true);
        $nome = $input['tool'] ?? '';
        $args = $input['args'] ?? [];
        
        if (!isset($tools[$nome])) {
            http_response_code(400);
            echo json_encode(['error' => 'Ferramenta não encontrada']);
            exit;
        }
        
        try {
            $resultado = match ($nome) {
                'analisar_cliente' => executar_analisar_cliente($args),
                'verificar_fraude' => executar_verificar_fraude($args),
                'calcular_risco'   => executar_calcular_risco($args),
                'decidir_oferta'   => executar_decidir_oferta($args),
                'buscar_cliente'   => executar_buscar_cliente($args),
                'listar_clientes'  => executar_listar_clientes($args),
            };
            
            echo json_encode(['success' => true, 'output' => $resultado]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Rota não encontrada']);
    exit;
}

echo "Uso: php -S localhost:8080 server.php\n";
