**Lead Scraping**

Planejamento, Arquitetura e Projeto de Solução

Módulo add-on para plataforma CRM

Versão 1.0 - Maio 2026

# **1\. Visão Geral**

Este documento descreve a arquitetura técnica e o plano de projeto para construção de uma solução de Lead Scraping integrada ao produto CRM existente. O objetivo é coletar, enriquecer, classificar e disponibilizar dados de leads a partir de múltiplas fontes públicas, criando uma base de dados proprietária que agrega valor ao CRM como serviço adicional.

**A solução é estruturada em cinco camadas funcionais:**

- Camada 1 - Fontes: Google Maps, Instagram, LinkedIn, CNPJ (Receita Federal), sites corporativos
- Camada 2 - Coleta: Apify Cloud com Actors especializados e proxies rotativos
- Camada 3 - Processamento: Normalização, enriquecimento, validação e scoring por IA
- Camada 4 - Base de dados: PostgreSQL com classificação, histórico e API REST
- Camada 5 - Serviço CRM: API de leads, filtros, importação e conformidade LGPD

# **2\. Fontes de Dados**

## **2.1 Google Maps**

Principal fonte para leads B2B e B2C locais. O Actor compass/crawler-google-places extrai: nome, endereço, telefone, site, e-mail (quando disponível via enrichment), horários, avaliações, categoria e coordenadas geográficas.

Campos disponíveis: placeId, title, address, phone, website, categoryName, totalScore, reviewsCount, location.lat/lng.

Custo estimado: ~\$2 por 1.000 resultados. Cobertura de e-mail: ~55-70% dos registros, maioritariamente endereços de papel (info@, contato@).

## **2.2 Instagram**

Fonte relevante para leads de negócios visuais, criadores, academias, clínicas e lojas. O Instagram Scraper / Local Lead Generation Agent extrai perfis por palavra-chave, localização ou hashtag: bios, e-mails públicos, seguidores, links externos e pontuação de engajamento.

Restrição: somente dados públicos. O Actor combina Google Search + Instagram Profile Scraper + Contact Details Scraper e inclui scoring por LLM (LangChain).

## **2.3 LinkedIn**

Fonte principal para leads B2B por cargo, empresa e setor. Actors disponíveis: LinkedIn Profile Scraper, Company Employees Scraper (HarvestAPI), Mass LinkedIn Profile Scraper. Extrai: cargos, experiências, empresas, e-mails pessoais/profissionais.

Atenção: LinkedIn bloqueia agressivamente scrapers. Recomenda-se uso de proxies residenciais, concorrência baixa (1-2 requisições simultâneas) e delays de 5-10 segundos entre requisições.

## **2.4 CNPJ / Receita Federal (Brasil)**

Fonte estratégica para o mercado brasileiro. O Actor Brazil CNPJ Lookup Scraper retorna: razão social, nome fantasia, endereço completo, telefone, e-mail, capital social, sócios (QSA), CNAEs, situação cadastral e data de abertura.

Permite filtros por estado e CNAE. Ideal para enriquecimento de leads já coletados via Google Maps, adicionando dados jurídicos e societários.

## **2.5 Sites Corporativos**

O Contact Details Scraper (vdrmota) é executado sobre a coluna de websites oriundos das fontes anteriores. Navega até /contato, /sobre, /equipe e extrai: e-mails nominados, telefones adicionais, perfis de LinkedIn e redes sociais. Cobertura típica: 55-70% dos sites acessíveis.

# **3\. Arquitetura Técnica**

## **3.1 Camada de Coleta - Apify Cloud**

Toda coleta é executada na nuvem Apify, que fornece: infraestrutura de proxies rotativos, scheduling de jobs, datasets exportáveis em CSV/JSON e API de integração. Não há dependência de infraestrutura própria para scraping.

| **Actor / Ferramenta**                   | **Fonte**       | **Dados principais**   | **Custo aprox.**  |
| ---------------------------------------- | --------------- | ---------------------- | ----------------- |
| compass/crawler-google-places            | Google Maps     | Nome, tel, site, CNAE  | \$2/1k resultados |
| Instagram Scraper / Local Lead Gen Agent | Instagram       | Bio, e-mail, score IA  | Pay-per-use       |
| HarvestAPI LinkedIn Employees            | LinkedIn        | Cargo, empresa, e-mail | \$1.5/1k leads    |
| parseforge/brazil-cnpj-scraper           | Receita Federal | CNPJ, QSA, CNAE, sit.  | Pay-per-use       |
| vdrmota/contact-info-scraper             | Sites web       | E-mails, redes sociais | \$0.15/run        |

## **3.2 Camada de Orquestração - n8n / Make.com**

O fluxo de processamento é gerenciado por n8n (self-hosted) ou Make.com (SaaS). O pipeline diário segue a sequência:

- 06:00 - Scheduler dispara os Actors no Apify via API
- Polling ou webhook ao término do run - busca o dataset
- Loop por lead: scraping do site via Contact Info Scraper
- Chamada à API do Claude / Ollama para enriquecimento e extração de campos estruturados (JSON)
- Chamada ao Claude para scoring ICP (1-10) com justificativa
- Validação de e-mail (NeverBounce / ZeroBounce)
- Deduplicação por (domain, email) antes do upsert
- Push ao banco de dados e, se score >= 7, notificação ao CRM

## **3.3 Camada de Dados - PostgreSQL + Redis**

O banco de dados central utiliza PostgreSQL para persistência dos leads e Redis para cache das consultas frequentes pela API. O schema contempla as seguintes entidades principais:

| **Entidade**         | **Campos-chave**                                                                    | **Observação**                             |
| -------------------- | ----------------------------------------------------------------------------------- | ------------------------------------------ |
| leads                | id, nome, domínio, e-mail, telefone, score, segmento, fonte, created_at, updated_at | Tabela mestra; domínio como chave de dedup |
| lead_sources         | lead_id, source_url, actor_id, scraped_at                                           | Auditoria e rastreabilidade da origem      |
| lead_enrichments     | lead_id, cnpj, razao_social, cnae, qsa, capital                                     | Dados CNPJ vinculados ao lead              |
| lead_classifications | lead_id, score_icp, tier, segmento, updated_at                                      | Reclassificação periódica por IA           |
| crm_exports          | lead_id, crm_id, exported_at, status                                                | Controle de push ao CRM                    |

## **3.4 Prompt de Enriquecimento - Claude API**

Exemplo de prompt estruturado para enriquecimento por IA:

Analise o conteúdo do site abaixo e retorne APENAS um JSON com os campos: { "employee_estimate": number, "industry": string, "revenue_tier": string, "tech_stack_signals": \[string\], "business_model": string, "key_decision_makers": \[string\] }. Conteúdo: \[SITE_CONTENT\]

Prompt de scoring ICP:

Pontue este lead de 1 a 10 contra o ICP definido. Retorne JSON: { "score": number, "tier": "A|B|C", "reasoning": string }. Lead: \[LEAD_JSON\]. ICP: \[ICP_DEFINITION\]

# **4\. Integração com o CRM**

A integração é exposta como um módulo add-on ao CRM, com três mecanismos de entrega:

## **4.1 API REST de Leads**

Endpoint GET /api/leads com filtros por: segmento, score mínimo, CNAE, estado, porte da empresa, fonte de origem e data de atualização. Suporte a paginação e exportação em CSV/JSON. Autenticação via API key por tenant.

## **4.2 Importação Ativa (Push)**

Webhook configurado no n8n: ao final de cada run, leads com score >= 7 são enviados automaticamente ao CRM via API, criando contatos e empresas com campos mapeados. Deduplicação no CRM por domínio de e-mail.

## **4.3 Catálogo de Leads (Pull)**

Interface no CRM permite que o usuário pesquise a base de leads enriquecida, aplique filtros avançados e importe manualmente os registros desejados para sua carteira de prospecção. Modelo de consumo: créditos por lead importado (monetização).

# **5\. Conformidade LGPD e Ética**

Somente dados públicos são coletados. O pipeline deve implementar os seguintes controles:

- Registro da fonte de origem (URL) em cada lead para comprovação de publicidade
- Mecanismo de opt-out: remoção permanente do lead mediante solicitação
- Retenção máxima definida por política (sugerido: 24 meses sem atualização)
- Dados sensíveis (CPF, dados pessoais de PF) não são coletados nem armazenados
- DIPA (Avaliação de Impacto) recomendada para volumes acima de 10.000 leads/mês
- Comunicações de outreach devem oferecer mecanismo claro de descadastramento
- LinkedIn: respeitar termos de serviço; evitar cookies de contas reais; usar Sales Navigator API para volumes enterprise

# **6\. Stack Tecnológica Recomendada**

| **Componente**   | **Tecnologia**            | **Justificativa**                                |
| ---------------- | ------------------------- | ------------------------------------------------ |
| Coleta           | Apify Cloud               | Actors prontos, proxies rotativos, pay-per-use   |
| Orquestração     | n8n (self-hosted)         | Open-source, flexível, integra Apify nativamente |
| IA / Scoring     | Claude API (Sonnet)       | Alta qualidade em extração estruturada e scoring |
| Banco de dados   | PostgreSQL 16             | Robusto, suporte a JSONB para dados variáveis    |
| Cache / API      | Redis + FastAPI (Python)  | Performance em consultas frequentes              |
| Validação e-mail | ZeroBounce / NeverBounce  | Reduz bounce rate nas campanhas                  |
| Infra cloud      | AWS / GCP ou VPS dedicada | n8n + Postgres + Redis podem rodar em 1 VPS      |

# **7\. Estimativa de Custos Operacionais**

| **Item**                      | **Volume/mês** | **Custo unitário** | **Total/mês** |
| ----------------------------- | -------------- | ------------------ | ------------- |
| Apify (plano Starter)         | -              | -                  | US\$ 29       |
| Google Maps Scraper           | 30.000 leads   | ~\$2/1k            | ~US\$ 60      |
| Contact Info Scraper          | 20.000 sites   | \$0.15/run         | ~US\$ 30      |
| Claude API (scoring + enrich) | 30.000 leads   | ~\$0.003/lead      | ~US\$ 90      |
| ZeroBounce (validação e-mail) | 15.000 e-mails | \$0.008/e-mail     | ~US\$ 120     |
| VPS (n8n + Postgres + Redis)  | -              | -                  | ~US\$ 40      |
| TOTAL ESTIMADO                | ~30k leads/mês | ~\$0.12/lead       | ~US\$ 369     |

Com Ollama self-hosted substituindo a Claude API, o custo de IA cai para ~\$0, reduzindo o custo por lead para US\$ 0,02-0,06.

# **8\. Fases do Projeto**

| **Fase**           | **Prazo**     | **Entregas**                                                                | **Responsável** |
| ------------------ | ------------- | --------------------------------------------------------------------------- | --------------- |
| 1 - PoC            | Semanas 1-2   | Pipeline Google Maps → DB com 1.000 leads; validar custo e qualidade        | Engenharia      |
| 2 - MVP            | Semanas 3-6   | Coleta de todas as fontes; scoring IA; API REST; painel de admin básico     | Engenharia      |
| 3 - Integração CRM | Semanas 7-9   | Módulo add-on no CRM; filtros; push automático; modelo de créditos          | Eng. + Produto  |
| 4 - Escala         | Semanas 10-12 | Otimização de custos; múltiplos segmentos; relatórios de qualidade de dados | Eng. + Dados    |

# **9\. Riscos e Mitigações**

| **Risco**                            | **Impacto** | **Mitigação**                                                                                     |
| ------------------------------------ | ----------- | ------------------------------------------------------------------------------------------------- |
| LinkedIn bloquear scraping em escala | Alto        | Proxies residenciais, concorrência baixa, Sales Navigator API para enterprise                     |
| Qualidade baixa de e-mails (info@)   | Médio       | Validação obrigatória; tratar Maps como descoberta de domínio, não de e-mail                      |
| Custo IA por lead elevado em volume  | Médio       | Ollama self-hosted para scoring em lote; Claude API apenas para enriquecimento de alta prioridade |
| Violação LGPD / termos de serviço    | Alto        | Coletar apenas dados públicos; registrar fonte; implementar opt-out; não armazenar dados de PF    |
| Mudança de layout / anti-scraping    | Baixo       | Apify mantém os Actors; monitorar alertas de run failure; manter Actor version fixada             |

# **10\. Próximos Passos**

- Definir ICP (perfil de cliente ideal) por segmento-alvo para configurar os filtros dos Actors e o prompt de scoring
- Criar conta Apify e executar teste piloto com 500 leads via Google Maps Scraper (custo ~\$1)
- Provisionar VPS com n8n + PostgreSQL + Redis para o pipeline de processamento
- Desenvolver schema do banco de dados e API REST inicial (FastAPI)
- Integrar Claude API para enriquecimento e validar qualidade do scoring em amostra de 200 leads
- Apresentar resultados da PoC ao time de produto para aprovação do roadmap