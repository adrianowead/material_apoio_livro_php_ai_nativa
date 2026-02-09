<?php
// src/fann/treinar.php

$dir = dirname(
    path: __FILE__,
    levels: 3
);

$dados_treino = $dir . '/data/dados_normalizados.data';
$arquivo_modelo = $dir . '/models/fann/credit_fraud.net';

if (!file_exists($dados_treino)) {
    die("Arquivo de treino nao encontrado: $dados_treino\n");
}

echo "==============================================\n";
echo "  TREINAMENTO FANN - CREDIT ENGINE\n";
echo "==============================================\n\n";

// Configuração da Rede
$num_input = 5;
$num_output = 1;
$num_layers = 4;
$layers = [$num_input, 10, 5, $num_output];

echo "Topologia: " . implode(' -> ', $layers) . "\n";
echo "Dataset: $dados_treino\n\n";

$ann = fann_create_standard_array($num_layers, $layers);

if (!$ann) {
    die("Erro ao criar FANN.\n");
}

fann_set_activation_function_hidden($ann, FANN_SIGMOID_SYMMETRIC);
fann_set_activation_function_output($ann, FANN_SIGMOID);

echo "Funcao de ativacao (ocultas): SIGMOID_SYMMETRIC\n";
echo "Funcao de ativacao (saida): SIGMOID\n\n";

// Parâmetros de Treinamento
$max_epochs = 5000;
$epochs_between_reports = 500;
$desired_error = 0.001;

echo "Parametros:\n";
echo "- Epocas maximas: $max_epochs\n";
echo "- Erro desejado (MSE): $desired_error\n";
echo "- Algoritmo: RPROP (padrao)\n\n";

echo "Iniciando treinamento...\n";
echo "----------------------------------------------\n";

$start = microtime(true);

fann_train_on_file($ann, $dados_treino, $max_epochs, $epochs_between_reports, $desired_error);

$elapsed = round(microtime(true) - $start, 2);

echo "----------------------------------------------\n\n";

// Métricas finais
$mse = fann_get_MSE($ann);
$bit_fail = fann_get_bit_fail($ann);

echo "Treinamento concluido em {$elapsed}s\n";
echo "MSE final: " . number_format($mse, 6) . "\n";
echo "Bit fail: $bit_fail\n\n";

// Salvar
if (!is_dir(dirname($arquivo_modelo))) {
    mkdir(dirname($arquivo_modelo), 0777, true);
}

fann_save($ann, $arquivo_modelo);
fann_destroy($ann);

echo "Modelo salvo em: $arquivo_modelo\n";
echo "==============================================\n";
