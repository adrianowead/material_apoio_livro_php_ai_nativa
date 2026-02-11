# IA Nativa para quem domina PHP

Este repositório faz parte do material de apoio do livro **"IA Nativa para quem domina PHP"**, disponível nas versões impressa e digital:

- **ISBN (Livro Impresso):** 978-65-01-93759-5
- **ISBN (Livro Digital):**  978-65-01-93710-6

> *"Enquanto o mercado corre atrás da próxima API de inteligência artificial, este livro mostra um caminho diferente: construir IA que roda na sua infraestrutura, sem depender de terceiros."*

O objetivo deste projeto é construir uma pipeline completa de análise de crédito — detecção de fraude, cálculo de risco e decisão multicritério — implementada inteiramente em PHP, integrando:

- **FANN (Fast Artificial Neural Network):** Redes neurais rápidas para bloqueio de segurança.
- **RubixML:** Machine Learning robusto para score de crédito.
- **AHPd (Analytic Hierarchy Process - Data-Driven):** Tomada de decisão multicritério para ofertas.
- **MCP & Ollama:** Camada de humanização com LLMs locais.

## Estrutura do Repositório

O projeto simula um ambiente de produção containerizado:

```text
.
├── app/                        # Aplicação principal (Motor de Crédito)
├── data/                       # Datasets brutos e processados
├── models/                     # Modelos treinados (Neural Network, Random Forest)
├── src/                        # Código-fonte das bibliotecas e classes auxiliares
├── tools/                      # Ferramentas de ETL, Geração de Dados e Treinamento
├── Dockerfile                  # Definição da imagem PHP + FANN + RubixML
├── docker-compose.yml          # Orquestração dos serviços (App + Ollama)
└── customizar-ollama.sh        # Script de inicialização da "Lina" (Agente de IA)
```

## Conteúdo do Projeto

O código cobre desde a engenharia de dados até a inferência em tempo real:

| Componente | Tecnologia | Função |
|---|---|---|
| **Segurança** | FANN (Extensão C) | Detectar anomalias e fraudes em < 1ms |
| **Risco** | RubixML (PHP) | Calcular probabilidade de inadimplência (Random Forest/MLP) |
| **Decisão** | AHPd (PHP) | Rankear a melhor oferta de crédito (Decisão Multicritério) |
| **Humanização** | Ollama + MCP | Explicar a decisão técnica em linguagem natural |

## Como Executar

O ambiente é totalmente dockerizado. Para iniciar a stack completa, incluindo o Ollama (LLM):

```bash
# Inicia todos os serviços (Pode levar alguns minutos na primeira vez para baixar os modelos)
docker-compose --profile ollama up -d
```

Se preferir rodar apenas o motor PHP (sem a camada de LLM):

```bash
docker-compose up -d
```

### Acessando o Ambiente

```bash
docker exec -it php_ai bash
```

Dentro do container, você pode rodar os scripts de exemplo localizados em `app/` ou usar as ferramentas em `tools/`.

A sequência de execução é a seguinte:

```bash
# Considerando que iremos testar sem o LLM (mais rápido)
docker-compose up -d

# Acessa o container
docker exec -it php_ai bash

# gerar dados sintéticos
php tools/gerar_sintetico.php

# normalizar dados
php tools/normalizar.php

# instalar as dependências do RubixML
cd src/rubixml/ && composer install && cd /livro

# treinar modelos
php src/fann/treinar.php
php src/rubixml/treinar.php

# testar a inferência dos modelos
php src/fann/inferir.php
php src/rubixml/inferir.php

# usar dados "reais" para análise de crédito em lote
php app/cli.php
```

## Usando LLM

É basicamente a mesma coisa que nas etapas acima, na verdade as etapas anteriores são obrigatórias para preparar os modelos determinísticos que serão consumidos pelo MCP.

> A inicialização do container **ollama_server** já executa o script **customizar-ollama.sh** que configura o Ollama para que ele use o modelo base **llama3.1**.
> Então não há necessidade de executar outros comandos para preparação do LLM.

```bash
# Inicia todos os serviços (Pode levar alguns minutos na primeira vez para baixar os modelos)
# o '-d' coloca em segundo plano, então acompanhe os logs do container ollama_server para ver o progresso
# o modelo base pesa aproximadamente 5GB para baixar
docker-compose --profile ollama up -d

# Acessa o container do php
docker exec -it php_ai bash

# agora basta inicializar o chat e começar a fazer solicitações ao LLM
# este docker não configura GPU, então as mensagens de resposta do LLM podem levar alguns segundos
# caso deseje incrementar e tenha uma GPU disponível, você pode preparar o ollama na sua máquina e fornecer o acesso do link ao docker
# ou seguir a documentação oficial do ollama para adicionar suporte a GPU diretamente no docker, sem usar sua máquina como host do modelo
php src/chat.php
```

## Adquira o Livro

O livro completo, com a fundamentação teórica e exemplos práticos, **já está disponível para compra em formato digital e físico (capa comum)**. A obra é escrita em **Português do Brasil**.

### Versão Digital
[![Amazon Brasil](https://img.shields.io/badge/Amazon_Brasil-eBook-FF9900?style=for-the-badge&logo=amazon)](https://www.amazon.com.br/dp/B0GMS6Z7YD)

### Versão Física — Capa Comum
[![Amazon Brasil](https://img.shields.io/badge/Amazon_Brasil-Capa_Comum-FF9900?style=for-the-badge&logo=amazon)](https://www.amazon.com.br/dp/6501937590)

[![Amazon Internacional](https://img.shields.io/badge/Amazon_Internacional-Capa_Comum-FF9900?style=for-the-badge&logo=amazon)](https://www.amazon.com/dp/B0GMXNKSD8)

[![Clube dos Autores](https://img.shields.io/badge/Clube_dos_Autores-Capa_Comum-1E88E5?style=for-the-badge&logo=bookstack)](https://clubedeautores.com.br/livro/ia-nativa-para-quem-domina-php)

