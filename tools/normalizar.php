<?php
// tools/normalizar.php

$dir = dirname(
    path: __FILE__,
    levels: 2
) . '/data/';

$raw_file = $dir . 'dados_brutos.txt';
$fann_file = $dir . 'dados_normalizados.data';
$rubix_file = $dir . 'dados_normalizados.csv';

if (!file_exists($raw_file)) {
    die("Arquivo bruto nao encontrado.");
}

$lines = file($raw_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$total = count($lines);

$handle_fann = fopen($fann_file, 'w');
$handle_rubix = fopen($rubix_file, 'w');

// FANN Header: num_train_data num_input num_output
fwrite($handle_fann, "$total 5 1\n");

// RubixML Header: features... class
fwrite($handle_rubix, "renda,divida,score,tempo_emprego_meses,idade,classe\n");

foreach ($lines as $line) {
    // raw format: renda;divida;score;tempo_emprego_meses;idade;target_fann;target_rubix
    $data = explode(';', $line);
    
    // Extrair
    $renda = (float)$data[0];
    $divida = (float)$data[1];
    $score = (float)$data[2];
    $tempo = (float)$data[3];
    $idade = (float)$data[4];
    $t_fann = $data[5];
    $t_rubix = $data[6];

    // Normalizar (Min-Max simples)
    $n_renda = min($renda / 50000, 1.0);
    $n_divida = min($divida / 200000, 1.0);
    $n_score = $score / 1000;
    $n_tempo = min($tempo / 480, 1.0);
    $n_idade = min($idade / 100, 1.0);

    // Salvar FANN
    // Input line
    fwrite($handle_fann, "$n_renda $n_divida $n_score $n_tempo $n_idade\n");
    // Output line
    fwrite($handle_fann, "$t_fann\n");

    // Salvar RubixML (CSV)
    fwrite($handle_rubix, "$n_renda,$n_divida,$n_score,$n_tempo,$n_idade,$t_rubix\n");
}

fclose($handle_fann);
fclose($handle_rubix);

echo "Dados normalizados gerados:\n";
echo "- FANN: {$fann_file} ($total registros)\n";
echo "- Rubix: {$rubix_file} ($total registros)\n";
