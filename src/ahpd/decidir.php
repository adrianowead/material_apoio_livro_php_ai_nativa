#!/usr/bin/env php
<?php

/**
 * Decisao de Limite de Credito com AHPd
 * 
 * Compara o cliente atual contra 3 perfis de referencia (Gold, Silver, Bronze)
 * para sugerir o melhor produto.
 * 
 * AGORA COM: Integracao do score de probabilidade do RubixML
 * 
 * CLI: php decidir.php <renda> <divida> <score> <emp> <idade> [prob_bom]
 */

declare(strict_types=1);

namespace App\AHPd;

// A extensao 'ahpd' deve estar carregada no ambiente (php_ai)
if (!extension_loaded('ahpd')) {
    fwrite(STDERR, "Erro: Extensao 'ahpd' nao carregada.\n");
    exit(1);
}

use AHPd\Data;

function decidir_limite(array $cliente): array
{
    $ahp = new Data();

    // 1. Definir Criterios (Min/Max)
    // NOVO: prob_bom = probabilidade de ser bom pagador (do RubixML)
    $ahp->setCriteria([
        'renda'    => 'max', // Maior renda é melhor
        'score'    => 'max', // Maior score é melhor
        'emprego'  => 'max', // Mais tempo é melhor
        'idade'    => 'max', // Mais velho correlaciona com estabilidade
        'divida'   => 'min', // Menor dívida é melhor
        'prob_bom' => 'max', // Maior probabilidade de ser bom pagador é melhor
    ]);

    // 2. Definir Arquétipos (Benchmarks do Banco)
    // Os benchmarks representam o "cliente ideal" para cada produto
    // prob_bom é a expectativa de probabilidade para cada perfil
    
    // Perfil GOLD (Ideal)
    $ahp->setOption('Benchmark Gold', [
        'renda'    => 25000,
        'divida'   => 2000,
        'score'    => 900,
        'emprego'  => 60, // 5 anos
        'idade'    => 45,
        'prob_bom' => 0.98, // Esperamos 98% de prob para Gold
    ]);

    // Perfil SILVER (Medio)
    $ahp->setOption('Benchmark Silver', [
        'renda'    => 8000,
        'divida'   => 5000,
        'score'    => 700,
        'emprego'  => 24, // 2 anos
        'idade'    => 30,
        'prob_bom' => 0.85, // Esperamos 85% de prob para Silver
    ]);

    // Perfil BRONZE (Entrada)
    $ahp->setOption('Benchmark Bronze', [
        'renda'    => 3000,
        'divida'   => 3000,
        'score'    => 450,
        'emprego'  => 6,  // 6 meses
        'idade'    => 22,
        'prob_bom' => 0.70, // Esperamos 70% de prob para Bronze
    ]);

    // 3. Adicionar o Cliente Atual
    $ahp->setOption('Cliente Atual', $cliente);

    // 4. Executar
    $resultado = $ahp->run();
    $rank = $resultado['rank'];

    // 5. Ordenar decrescente
    arsort($rank);
    
    return $rank;
}

// === CLI ===

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {

    // Demo Mode
    if ($argc === 1) {
        echo "===========================================\n";
        echo "  DECISAO DE LIMITES (AHPd + RubixML)\n";
        echo "===========================================\n\n";

        // Caso 1: Cliente Alto Padrao
        // prob_bom simulado = 0.96 (96% chance de ser bom pagador)
        $cliente_rico = [
            'renda'    => 18000,
            'divida'   => 1500,
            'score'    => 850,
            'emprego'  => 48,
            'idade'    => 40,
            'prob_bom' => 0.96, // RubixML disse 96%
        ];

        echo "--- Caso 1: Cliente Alto Padrao ---\n";
        echo "Renda: R\$ {$cliente_rico['renda']}\n";
        echo "Divida: R\$ {$cliente_rico['divida']}\n";
        echo "Score: {$cliente_rico['score']}\n";
        echo "Emprego: {$cliente_rico['emprego']} meses\n";
        echo "Idade: {$cliente_rico['idade']} anos\n";
        echo "Prob. Bom (RubixML): " . ($cliente_rico['prob_bom'] * 100) . "%\n";
        echo "\nRanking de Prioridade:\n";
        
        $rank = decidir_limite($cliente_rico);
        
        $posicao = 1;
        foreach ($rank as $nome => $score) {
            printf("%d. %-20s : %.4f\n", $posicao++, $nome, $score);
        }
        
        echo "\nAnalise: ";
        if ($rank['Cliente Atual'] > $rank['Benchmark Silver']) {
            echo "Ofertar Cartao GOLD/PLATINUM\n";
        } elseif ($rank['Cliente Atual'] > $rank['Benchmark Bronze']) {
            echo "Ofertar Cartao SILVER\n";
        } else {
            echo "Ofertar Cartao BASICO ou Garantido\n";
        }
        echo "\n";

        // Caso 2: Cliente Iniciante
        // prob_bom simulado = 0.55 (55% - incerto, zona de risco)
        $cliente_ini = [
            'renda'    => 2500,
            'divida'   => 500,
            'score'    => 400,
            'emprego'  => 3,
            'idade'    => 19,
            'prob_bom' => 0.55, // RubixML disse apenas 55%
        ];
        
        echo "--- Caso 2: Cliente Iniciante ---\n";
        echo "Renda: R\$ {$cliente_ini['renda']}\n";
        echo "Divida: R\$ {$cliente_ini['divida']}\n";
        echo "Score: {$cliente_ini['score']}\n";
        echo "Emprego: {$cliente_ini['emprego']} meses\n";
        echo "Idade: {$cliente_ini['idade']} anos\n";
        echo "Prob. Bom (RubixML): " . ($cliente_ini['prob_bom'] * 100) . "%\n";
        echo "\nRanking de Prioridade:\n";
        
        $rank2 = decidir_limite($cliente_ini);
        
        $posicao = 1;
        foreach ($rank2 as $nome => $score) {
            printf("%d. %-20s : %.4f\n", $posicao++, $nome, $score);
        }

        echo "\nAnalise: ";
        if ($rank2['Cliente Atual'] > $rank2['Benchmark Silver']) {
             echo "Ofertar Cartao GOLD/PLATINUM\n";
        } elseif ($rank2['Cliente Atual'] > $rank2['Benchmark Bronze']) {
             echo "Ofertar Cartao SILVER\n";
        } else {
             echo "Ofertar Cartao BASICO (Cliente abaixo do benchmark Bronze)\n";
        }

        exit(0);
    }

    // Manual Mode
    if ($argc < 7) {
        fwrite(STDERR, "Uso: php decidir.php <renda> <divida> <score> <emp_meses> <idade> <prob_bom>\n");
        fwrite(STDERR, "Exemplo: php decidir.php 18000 1500 850 48 40 0.96\n");
        exit(1);
    }

    $cliente = [
        'renda'    => (float)$argv[1],
        'divida'   => (float)$argv[2],
        'score'    => (float)$argv[3],
        'emprego'  => (float)$argv[4],
        'idade'    => (float)$argv[5],
        'prob_bom' => (float)$argv[6],
    ];

    echo "Processando AHPd para:\n";
    print_r($cliente);
    echo "\n";

    $rank = decidir_limite($cliente);
    print_r($rank);
}
