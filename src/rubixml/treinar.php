<?php
// src/rubixml/treinar.php

require_once __DIR__ . '/vendor/autoload.php';

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Classifiers\RandomForest;
use Rubix\ML\Classifiers\ClassificationTree;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Serializers\RBX;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\CrossValidation\Metrics\Accuracy;
use Rubix\ML\CrossValidation\Reports\ConfusionMatrix;

$dir = dirname(
    path: __FILE__,
    levels: 3
);

$trainingFile =  $dir . '/data/dados_normalizados.csv';
$modelFile = $dir . '/models/rubixml/credit_risk.rbx';

if (!file_exists($trainingFile)) {
    die("Dataset nao encontrado: $trainingFile\n");
}

echo "==============================================\n";
echo "  TREINAMENTO RUBIXML - CREDIT ENGINE\n";
echo "==============================================\n\n";

echo "Carregando dataset: $trainingFile\n";

// CSV tem header
$dataset = Labeled::fromIterator(
    new CSV($trainingFile, true) 
);

// Converter strings numéricas para float/int
$dataset->apply(new NumericStringConverter());

echo "Total de registros: " . $dataset->count() . "\n";
echo "Features: " . $dataset->numFeatures() . "\n";
echo "Classes: " . implode(', ', $dataset->possibleOutcomes()) . "\n\n";

// --- VALIDAÇÃO: Separar treino e teste ---
echo "Separando dados: 80% treino, 20% teste...\n";

$dataset->randomize();
[$training, $testing] = $dataset->split(0.8);

echo "Treino: " . $training->count() . " registros\n";
echo "Teste:  " . $testing->count() . " registros\n\n";

// Definir o Estimador (Random Forest)
$estimator = new RandomForest(new ClassificationTree(10), 100);

echo "Treinando Random Forest (100 árvores, profundidade 10)...\n";

$start = microtime(true);

try {
    $estimator->train($training);
} catch (\Throwable $e) {
    echo "Erro fatal no treino: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}

$elapsed = round(microtime(true) - $start, 2);
echo "Treinamento concluído em {$elapsed}s\n\n";

// --- VALIDAÇÃO: Testar no conjunto de teste ---
echo "Validando modelo no conjunto de teste...\n";

$predictions = $estimator->predict($testing);
$labels = $testing->labels();

// Acurácia
$accuracy = new Accuracy();
$score = $accuracy->score($predictions, $labels);
echo "Acurácia: " . round($score * 100, 2) . "%\n\n";

// Matriz de Confusão
echo "Matriz de Confusão:\n";
$report = new ConfusionMatrix();
$matrix = $report->generate($predictions, $labels);

// Formatar saída da matriz
foreach ($matrix as $actual => $predicted) {
    echo "  Real '$actual': ";
    $parts = [];
    foreach ($predicted as $pred => $count) {
        $parts[] = "previu '$pred' = $count";
    }
    echo implode(', ', $parts) . "\n";
}
echo "\n";

// --- Retreinar com todos os dados para o modelo final ---
echo "Retreinando com 100% dos dados para modelo de producao...\n";

$estimator = new RandomForest(new ClassificationTree(10), 100);
$estimator->train($dataset);

// Persistir
echo "Salvando modelo em $modelFile...\n";

if (!is_dir(dirname($modelFile))) {
    mkdir(dirname($modelFile), 0777, true);
}

$persister = new Filesystem(
    path: $modelFile,
    history: false
);

$serializer = new RBX(6);
$encoding = $serializer->serialize($estimator);
$persister->save($encoding);

echo "\nModelo RubixML salvo com sucesso!\n";
echo "==============================================\n";
