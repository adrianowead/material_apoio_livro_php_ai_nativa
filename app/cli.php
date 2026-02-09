#!/usr/bin/env php
<?php

/**
 * Motor de Credito 360 - Pipeline de Inferencia
 * 
 * Orquestra a decisao de credito integrando:
 * 1. FANN (Seguranca/Fraude)
 * 2. RubixML (Analise de Risco)
 * 3. AHPd (Decisao de Produto)
 */

declare(strict_types=1);

// Resolve o path raiz do projeto (caminho: projeto/)
// Exemplo de uso de dirname com nivel 2 (app -> projeto)
$root = dirname(__FILE__, 2);

require_once $root . '/src/rubixml/vendor/autoload.php';
require_once $root . '/src/fann/inferir.php';
require_once $root . '/src/rubixml/inferir.php';
require_once $root . '/src/ahpd/decidir.php';

use App\FANN;
use App\Rubix;
use App\AHPd;

/**
 * Normaliza os dados brutos para o intervalo 0.0 - 1.0
 */
function normalizar(
    float $renda,
    float $divida,
    float $score,
    float $tempo,
    float $idade
): array {
    return [
        min($renda / 50000, 1.0),   // Renda teto 50k
        min($divida / 200000, 1.0), // Divida teto 200k
        $score / 1000,              // Score max 1000
        min($tempo / 480, 1.0),     // Tempo max 40 anos
        min($idade / 100, 1.0),     // Idade max 100
    ];
}

/**
 * Executa a lógica de decisão do AHPd para clientes aprovados
 */
function decidir_oferta(array $input_real): string
{
    $rank = AHPd\decidir_limite($input_real);
    
    // Identifica pontuacao do cliente e benchmarks
    $score_cli = $rank['Cliente Atual'] ?? 0;
    $score_gold = $rank['Benchmark Gold'] ?? 0;
    $score_silver = $rank['Benchmark Silver'] ?? 0;

    // Regra de Negocio: 90% do benchmark ja qualifica
    if ($score_cli >= $score_gold * 0.9) {
        return "OFERTA: GOLD/PLATINUM";
    }
    
    if ($score_cli >= $score_silver * 0.9) {
        return "OFERTA: SILVER";
    }

    return "OFERTA: BRONZE/CONST";
}

/**
 * Processa um unico cliente através da pipeline
 */
function processar_cliente(
    string $id,
    float $renda,
    float $divida,
    float $score,
    float $tempo,
    float $idade,
    $modelo_fann,
    $modelo_rubix
): array {
    // 1. Normalizacao
    $dados_norm = normalizar(
        renda: $renda,
        divida: $divida,
        score: $score,
        tempo: $tempo,
        idade: $idade
    );

    // 2. FANN (Seguranca)
    $score_fann = FANN\inferir($modelo_fann, $dados_norm);
    $classe_fann = FANN\classificar($score_fann);

    // 3. RubixML (Risco)
    [$classe_rubix, $probs] = Rubix\inferir($modelo_rubix, $dados_norm);

    // 4. Decisao (AHPd)
    $decisao = "N/A (Reprovado)";
    $is_safe = ($classe_fann === 'NORMAL'); // FANN OK?
    $is_good = ($classe_rubix === 'bom');   // Rubix OK?

    if ($is_safe && $is_good) {
        $decisao = decidir_oferta([
            'renda'    => $renda,
            'divida'   => $divida,
            'score'    => $score,
            'emprego'  => $tempo,
            'idade'    => $idade,
            'prob_bom' => $probs['bom'],
        ]);
    } elseif (!$is_safe) {
        $decisao = "Bloqueio de Seguranca";
    } elseif (!$is_good) {
        $decisao = "Negado por Risco";
    }

    return [$classe_fann, $classe_rubix, $decisao];
}

// === FASE DE INICIALIZACAO ===

echo ">>> Iniciando Motor de Credito 360 <<<\n";
echo "[SYSTEM] Carregando modelos...\n";

try {
    $path_fann = $root . '/models/fann/credit_fraud.net';
    $path_rubix = $root . '/models/rubixml/credit_risk.rbx';

    $net = FANN\carregar_modelo($path_fann);
    $estimator = Rubix\carregar_modelo($path_rubix);
} catch (Exception $e) {
    die("[FATAL] Erro ao carregar modelos: " . $e->getMessage() . "\n");
}

// === FASE DE EXECUCAO ===

$csv_path = __DIR__ . '/clientes.csv';
if (!file_exists($csv_path)) {
    die("[FATAL] CSV nao encontrado: $csv_path\n");
}

$linhas = file($csv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
array_shift($linhas); // Remove cabecalho

// Setup da Tabela de Saida
$mask = "| %-6s | %-12s | %-12s | %-30s |\n";
printf(
    $mask,
    "ID", "FANN", "Rubix", "Decisao"
);
echo str_repeat('-', 105) . "\n";

foreach ($linhas as $linha) {
    $cols = explode(';', $linha);
    if (count($cols) < 6) continue;

    $id     = $cols[0];
    $renda  = (float)$cols[1];
    $divida = (float)$cols[2];
    $score  = (float)$cols[3];
    $tempo  = (float)$cols[4];
    $idade  = (float)$cols[5];

    [$res_fann, $res_rubix, $res_ahpd] = processar_cliente(
        id: $id,
        renda: $renda,
        divida: $divida,
        score: $score,
        tempo: $tempo,
        idade: $idade,
        modelo_fann: $net,
        modelo_rubix: $estimator
    );

    printf(
        $mask,
        $id,
        $res_fann,
        strtoupper($res_rubix),
        $res_ahpd
    );
}

fann_destroy($net);
echo "\n[SYSTEM] Processamento Concluido.\n";
