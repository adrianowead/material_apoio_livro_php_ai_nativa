#!/usr/bin/env php
<?php

/**
 * Inferencia RubixML - Motor de Credito (Risco)
 * 
 * Classifica cliente como 'good' (Bom Pagador) ou 'bad' (Inadimplente).
 * 
 * CLI: php inferir.php <modelo> <renda> <divida> <score> <emp> <idade>
 */

declare(strict_types=1);

namespace App\Rubix;

require_once __DIR__ . '/vendor/autoload.php';

use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Datasets\Unlabeled;

function carregar_modelo(string $arq): PersistentModel
{
    if (!file_exists($arq)) {
        throw new RuntimeException("Modelo nao encontrado: {$arq}");
    }
    
    return PersistentModel::load(
        new Filesystem($arq)
    );
}

function inferir(PersistentModel $modelo, array $entrada): array 
{
    // RubixML espera features como array de arrays
    $dataset = new Unlabeled([$entrada]);
    
    $classe = $modelo->predict($dataset)[0];
    // Probabilidades só funcionam se o estimador suportar (RandomForest suporta)
    try {
        $probs = $modelo->proba($dataset)[0];
    } catch (\Throwable $e) {
        $probs = [];
    }
    
    return [$classe, $probs];
}

// === CLI ===

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    
    $dir = dirname(
        path: __FILE__,
        levels: 3
    );
    
    $modelo_padrao = $dir . '/models/rubixml/credit_risk.rbx';
    
    // Demo mode
    if ($argc === 1) {
        echo "===========================================\n";
        echo "  INFERENCIA RUBIXML - CREDIT ENGINE DEMO\n";
        echo "===========================================\n\n";
        
        if (!file_exists($modelo_padrao)) {
            fwrite(STDERR, "Modelo nao encontrado em: $modelo_padrao\n");
            exit(1);
        }
        
        $modelo = carregar_modelo($modelo_padrao);
        
        // Features: [Renda, Divida, Score, Emprego, Idade] 
        // Lógica de Geração: 
        // Score = +Renda -Divida +ScoreExt +Emprego
        
        $casos = [
            // [0] Bom Pagador: Renda Alta, Divida Baixa, Score Alto
            [0.8, 0.1, 0.9, 0.8, 0.6],
            
            // [1] Mau Pagador: Renda Baixa, Divida Alta
            [0.2, 0.9, 0.3, 0.2, 0.3],
            
            // [2] Incerto (Médio): Renda Média, Divida Média
            [0.5, 0.5, 0.5, 0.5, 0.4],
        ];
        
        echo "Modelo: {$modelo_padrao}\n\n";
        echo "Renda Divid Score Empr  Idade  Classe  Prob(bom)\n";
        echo "--------------------------------------------------\n";
        
        foreach ($casos as $e) {
            [$cls, $probs] = inferir($modelo, $e);
            
            // Probabilidade de ser 'bom'
            $prob_good = $probs['bom'] ?? 0.0;
            
            printf(
                "%.2f  %.2f  %.2f  %.2f  %.2f   %s    %.4f\n",
                $e[0], $e[1], $e[2], 
                $e[3], $e[4],
                str_pad($cls, 4), $prob_good
            );
        }
        
        echo "--------------------------------------------------\n";
        exit(0);
    }
    
    // Manual Mode
    if ($argc < 7) {
        fwrite(STDERR, 
            "Uso: php inferir.php <modelo> <renda> <divida> <score> <emp> <idade>\n"
        );
        exit(1);
    }
    
    $arq = $argv[1];
    // Se passar 'default' ou arquivo não existir, tenta o padrão
    if ($arq === 'default' || !file_exists($arq)) {
        $arq = $modelo_padrao;
    }

    $e = [
        (float) $argv[2],
        (float) $argv[3],
        (float) $argv[4],
        (float) $argv[5],
        (float) $argv[6],
    ];
    
    $modelo = carregar_modelo($arq);
    [$cls, $probs] = inferir($modelo, $e);
    
    echo "Entrada: " . implode(' ', $e) . "\n";
    echo "Classe Prevista: {$cls}\n";
    print_r($probs);
}
