<?php

// tools/gerar_sintetico.php
// Gera dados sintéticos para o "Motor de Crédito"
// Features: Renda, Dívida, Score, Tempo Emprego, Idade

mt_srand(42); // Determinismo

$total_registros = 10000;

$dir = dirname(
    path: __FILE__,
    levels: 2
) . '/data/';

if(!is_dir($dir)) {
    mkdir(
        $dir,
        recursive: true
    );
}

$arquivo_saida = $dir . 'dados_brutos.txt';

$handle = fopen($arquivo_saida, 'w');
// Header: renda;divida;score;tempo_emprego_meses;idade;is_fraud(fann_target);will_default(rubix_target)
// Mas vamos salvar cru para depois normalizar.
// Vamos salvar CSV: renda;divida;score;tempo_emprego_meses;idade;target_fann;target_rubix

/*
 * REGRAS DE NEGÓCIO (SINTÉTICAS):
 * 
 * 1. FRAUDE (FANN - Anomaly Detection):
 *    - Lógica XOR Disfarçada:
 *    - A: Renda Alta (> 25000)
 *    - B: Histórico Baixo (Tempo Emprego < 12 meses OR Idade < 22)
 *    - Se (A AND B) => FRAUDE (Target = 0 - Bloquear)
 *    - Caso contrário => OK (Target = 1 - Passar)
 * 
 * 2. RISCO (RubixML - Default Prediction):
 *    - Score base calculado por pesos
 *    - Se Score Final < Threshold => Default (Target = 'default')
 *    - Se Score Final >= Threshold => Bom Pagador (Target = 'ok')
 */

for ($i = 0; $i < $total_registros; $i++) {
    // 1. Gerar Features Aleatórias
    $renda = mt_rand(1500, 50000);
    $divida = mt_rand(0, $renda * 10); // Dívida até 10x a renda
    $score_ext = mt_rand(100, 1000);
    $idade = mt_rand(18, 70);
    
    // Correlação: Idade maior tende a ter mais tempo de emprego
    $max_emprego = ($idade - 18) * 12; 
    $tempo_emprego_meses = mt_rand(0, min($max_emprego, 480)); // Meses

    // 2. Definir Target FANN (0=Suspeito, 1=Normal)
    $is_rich = ($renda > 20000);
    $no_history = ($tempo_emprego_meses < 6 || $idade < 21);
    
    // Regra XOR de Fraude: Rico sem histórico é suspeito
    $fraud = ($is_rich && $no_history);
    
    // Adiciona 5% de ruído (fraudes que parecem normais e vice-versa)
    if (mt_rand(0, 100) < 5) {
        $fraud = !$fraud;
    }

    $target_fann = $fraud ? 0 : 1; 

    // 3. Definir Target RubixML (Inadimplência)
    // Formula de "Bom Pagador"
    // + Renda (pouco peso)
    // - Dívida (muito peso)
    // + Score (muito peso)
    // + Emprego (médio peso)
    
    // Normalizações mentais pra ponderar
    $n_renda = $renda / 50000;
    $n_divida = $divida / 200000; // assumindo max divida ~500k mas vamos por base
    $n_score = $score_ext / 1000;
    $n_emprego = $tempo_emprego_meses / 360;

    $credit_score = ($n_renda * 1) 
                  - ($n_divida * 2) 
                  + ($n_score * 2.5) 
                  + ($n_emprego * 1.5);
    
    // Ruído aleatório no score
    $credit_score += (mt_rand(-100, 100) / 1000);

    // Threshold empírico
    $target_rubix = ($credit_score > 1.0) ? 'bom' : 'ruim';

    // Se for fraude detectada pela lógica FANN, vamos forçar 'ruim' no Rubix também? 
    // Não necessariamente, são problemas ortogonais no nosso design, mas 
    // geralmente fraude não paga. Mas vamos deixar independentes para provar o valor de cada ferramenta.
    
    fwrite($handle, "$renda;$divida;$score_ext;$tempo_emprego_meses;$idade;$target_fann;$target_rubix\n");
}

fclose($handle);
echo "Gerados $total_registros registros em '{$arquivo_saida}'.\n";
