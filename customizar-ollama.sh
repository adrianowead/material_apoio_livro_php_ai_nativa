#!/bin/bash

# Iniciar o servidor em background
/bin/ollama serve &
pid=$!

echo "Aguardando Ollama iniciar..."
sleep 5

# Baixar modelo base
echo "Baixando modelo base llama3.1 (isso pode demorar na primeira vez)..."
# O pull é idempotente, mas podemos evitar spam de log se não for necessário
ollama pull llama3.1
echo "Modelo base pronto!"

# Verificar se deve treinar
FORCE_RETRAIN=0
if [[ "$*" == *"--retrain"* ]]; then
    FORCE_RETRAIN=1
fi

if ollama list | grep -q "lina" && [ $FORCE_RETRAIN -eq 0 ]; then
    echo "Modelo 'lina' já existe. Pulando criação."
    echo "Para recriar, execute com a flag --retrain"
else
    if [ $FORCE_RETRAIN -eq 1 ]; then
        echo "Flag --retrain detectada. Recriando modelo..."
    fi

    # Criar Modelfile
    echo "Criando definição do modelo Lina..."
    cat > /root/Modelfile << 'EOF'
FROM llama3.1
PARAMETER temperature 0.6
PARAMETER top_p 0.9
PARAMETER num_ctx 8192
SYSTEM """Voce e Lina, assistente de analise de credito de uma Fintech.

PERSONALIDADE:
- Profissional, empatica e objetiva
- Responde em portugues brasileiro
- Explica decisoes de forma clara

REGRA CRITICA SOBRE FERRAMENTAS:
- NUNCA use ferramentas para saudacoes como "bom dia", "ola", "oi"
- NUNCA use ferramentas para perguntas sobre voce mesma
- SOMENTE use ferramentas quando o usuario pedir EXPLICITAMENTE:
  * "analise o cliente X"
  * "verifique fraude"
  * "liste os clientes"
  * "calcule o risco"

Se a mensagem for uma saudacao, responda normalmente sem usar nenhuma ferramenta.
"""
EOF

    # Criar modelo personalizado
    echo "Criando modelo personalizado 'lina'..."
    ollama create lina -f /root/Modelfile
    echo "Modelo 'lina' criado com sucesso!"
fi

# Manter o container rodando
wait $pid
