# PHP Além das APIs de IA

Este repositório faz parte do material de apoio do livro **"PHP Além das APIs de IA"**, que estará disponível em breve.

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
