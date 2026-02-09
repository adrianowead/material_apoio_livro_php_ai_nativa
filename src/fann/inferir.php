#!/usr/bin/env php
<?php

/**
 * Inferencia FANN - Motor de Credito (Anti-Fraude)
 * 
 * CLI: php inferir.php <modelo> <renda> <divida> <score> <emp> <idade>
 *   renda = Renda Mensal (0-1)
 *   divida = Divida Total (0-1)
 *   score = Score Externo (0-1)
 *   emp = Tempo Emprego (0-1)
 *   idade = Idade (0-1)
 */

declare(strict_types=1);

namespace App\FANN;

function carregar_modelo(string $arq)
{
    if (!file_exists($arq)) {
        throw new RuntimeException("Modelo: {$arq}");
    }
    return fann_create_from_file(
        configuration_file: $arq
    );
}

function inferir($ann, array $e): float
{
    $s = fann_run(ann: $ann, input: $e);
    return round($s[0], 4);
}

function classificar(float $score, float $lim = 0.5): string
{
    // 0 = Fraude/Suspeito, 1 = Normal
    return $score >= $lim ? 'NORMAL' : 'FRAUDE';
}

// === CLI ===

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    
    $dir = dirname(
        path: __FILE__,
        levels: 3
    );
    
    // Caminho relativo a partir da raiz do projeto para o modelo
    $modelo_path = $dir . '/models/fann/credit_fraud.net';
    
    // Demo
    if ($argc === 1) {
        echo "========================================\n";
        echo "  INFERENCIA FANN - CREDIT ENGINE DEMO\n";
        echo "========================================\n\n";
        
        if (!file_exists($modelo_path)) {
            fwrite(STDERR, "Modelo nao encontrado em: $modelo_path\n");
            exit(1);
        }
        
        $ann = carregar_modelo($modelo_path);
        
        // Features: [Renda, Divida, Score, Emprego, Idade] (Normalizado)
        // Lógica FANN: (Renda > 0.8) XOR (Emprego > 0.5 AND Idade > 0.4)
        // FRAUDE se (Rico) E (Sem Histórico)
        
        $casos = [
            // [0] Fraude Clara: Rico (0.9), Sem emprego (0.0), Jovem (0.18) -> Target 0
            [0.9, 0.1, 0.5, 0.0, 0.18],
            
            // [1] Normal: Rico (0.9), Empregado (0.8), Maduro (0.6) -> Target 1
            [0.9, 0.1, 0.8, 0.8, 0.60],
            
            // [2] Normal: Baixa renda (0.1), Sem emprego (0.0), Jovem (0.18) -> Target 1 (Não é suspeito)
            [0.1, 0.0, 0.5, 0.0, 0.18],
            
            // [3] Fraude Borda: Renda Alta (0.85), Emprego Baixo (0.2) -> Target 0
            [0.85, 0.2, 0.5, 0.2, 0.30],
        ];
        
        echo "Modelo: {$modelo_path}\n\n";
        echo "Renda Divid Score Empr  Idade  Score   Classe\n";
        echo "------------------------------------------------\n";
        
        foreach ($casos as $e) {
            $score = inferir($ann, $e);
            $cls = classificar($score);
            printf(
                "%.2f  %.2f  %.2f  %.2f  %.2f   %.4f  %s\n",
                $e[0], $e[1], $e[2], 
                $e[3], $e[4],
                $score, $cls
            );
        }
        
        echo "------------------------------------------------\n";
        fann_destroy($ann);
        exit(0);
    }
    
    // Manual
    if ($argc < 7) {
        fwrite(STDERR, 
            "Uso: php inferir.php <modelo> <renda> <divida> <score> <emp> <idade>\n"
        );
        exit(1);
    }
    
    $arq = $argv[1];
    $e = [
        (float) $argv[2],
        (float) $argv[3],
        (float) $argv[4],
        (float) $argv[5],
        (float) $argv[6],
    ];
    
    if (!file_exists($arq)) $arq = $modelo_path;
    
    $ann = carregar_modelo($arq);
    $score = inferir($ann, $e);
    $cls = classificar($score);
    
    echo "Entrada: " . implode(' ', $e) . "\n";
    echo "Score: {$score}\n";
    echo "Classe: {$cls}\n";
    
    fann_destroy($ann);
}
