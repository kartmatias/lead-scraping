**Estratégia para um cliente consumidor Apify em PHP** (hospedagens compartilhadas inclusive):

A **Apify não tem um SDK oficial em PHP**, mas tem uma excelente documentação e tutorial oficial exatamente para isso. A abordagem padrão (e mais robusta) é usar **Guzzle** (o cliente HTTP mais popular no PHP).

### 1. Estratégia Principal (Recomendada)

**Use GuzzleHttp\Client** + autenticação via Bearer Token.

#### Instalação (Composer)
```bash
composer require guzzlehttp/guzzle
```

#### Cliente base (reutilizável)
```php
<?php
require 'vendor/autoload.php';

class ApifyClient {
    private $client;
    private $token;

    public function __construct(string $token) {
        $this->token = $token;
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.apify.com/v2/',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,           // ajuste conforme necessário
            'http_errors' => false,    // para tratar erros manualmente
        ]);
    }

    public function request(string $method, string $uri, array $options = []) {
        $response = $this->client->request($method, $uri, $options);
        $body = $response->getBody()->getContents();
        
        if (in_array($response->getStatusCode(), [200, 201])) {
            return json_decode($body, true);
        }
        
        throw new Exception("Apify API Error: " . $response->getStatusCode() . " - " . $body);
    }
}
```

### 2. Exemplos de uso comuns

**Rodar um Actor/Task:**
```php
$apify = new ApifyClient('sua_api_token_aqui');

$response = $apify->request('POST', 'acts/compass~crawler-google-places/runs', [
    'json' => [  // input do Actor
        'searchStringsArray' => ['Restaurantes em Hialeah, FL'],
        'maxItems' => 50,
    ],
    'query' => [
        'waitForFinish' => 300,   // espera até 5 minutos (para runs rápidas)
    ]
]);

$runId = $response['data']['id'];
echo "Run iniciado: " . $runId;
```

**Buscar resultados (Dataset):**
```php
$items = $apify->request('GET', "actor-runs/{$runId}/dataset/items", [
    'query' => [
        'limit' => 100,
        // 'offset' => 100  // paginação
    ]
]);

// $items já é o array de leads direto
```

**Outros endpoints úteis:**
- `tasks/{taskId}/runs` → rodar Tasks salvas (melhor para produção)
- Webhooks (recomendado para jobs longos)
- `datasets/{datasetId}/items`
- Proxy Apify (se precisar rodar scrapers do seu lado)

### 3. Boas Práticas para Hospedagem Compartilhada

- **Não use `waitForFinish` alto** em jobs pesados → use **Webhooks**.
- Crie uma tabela no banco (MySQL) para registrar `runId`, status, datasetId etc.
- Use **queues** ou **cron jobs** para polling leve (verificar status a cada 30-60s).
- Armazene o token em variável de ambiente (`.env` + `vlucas/phpdotenv`).
- Trate rate limits (Apify é generoso, mas existe).
- Para escala: use filas (Laravel Horizon, Symfony Messenger, ou simples beanstalkd/Redis).

### 4. Alternativas / Complementos

| Opção | Quando usar | Prós |
|-------|-------------|------|
| **Guzzle puro** (oficial) | Qualquer projeto | Leve, flexível, tutorial oficial |
| **megaads/apify-client-php** | Laravel | Abstração simples |
| **ipedis/php-apify-sdk** | Comunidade | Mais completo (não oficial) |
| **Webhooks + API** | Jobs longos | Melhor performance |

**Link oficial do tutorial (muito bom):**  
[https://docs.apify.com/academy/php/use-apify-from-php](https://docs.apify.com/academy/php/use-apify-from-php)

**Repositório de exemplos:**  
[https://github.com/apify/apify-php-tutorial](https://github.com/apify/apify-php-tutorial)

--

## Definição do projeto:

**Estruturar de forma clara, escalável e fácil de manter.**

### 1. Escolha do Framework
**Recomendação: Laravel**.

**Motivos**:
- Fila nativa (`queues`) + Horizon (para escala futura) é muito superior a uma fila via tabela MySQL pura.
- Scheduler (para orquestrador), Jobs, Events, Notifications, Eloquent (ótimo para Leads e Requests).
- Middlewares, Validation, API Resources, Sanctum/Passport para autenticação.
- Manutenção e evolução muito mais rápidas (migrations, seeders, tests).

### 2. Actors Recomendados na Apify (2026)

| Tipo | Actor ID (recomendado) | Uso Principal | Input Principal | Preço aproximado |
|------|------------------------|---------------|-----------------|------------------|
| **Google Maps** | `compass/crawler-google-places` (ou alias `apify/google-maps-scraper`) | Leads locais por cidade + segmento | `searchStringsArray: ["Restaurantes em Natal RN"]`, `maxItems` | ~$2.10 / 1.000 places |
| **Instagram** | `apify/instagram-scraper` ou `apify/instagram-profile-scraper` | Perfis por hashtag/localização/keyword | URLs ou buscas | ~$1.50 / 1.000 results |
| **LinkedIn** | `dev_fusion/linkedin-profile-scraper` (com email) ou `supreme_coder/linkedin-profile-scraper` (no cookies) | Perfis de decisores ou empresas | Profile URLs ou search | $3–10 / 1.000 |
| **CNPJ** | `parseforge/brazil-cnpj-scraper` (melhor avaliação) ou `chimerical_quicklime/brazil-cnpj-scraper` | Enriquecimento por CNPJ | Lista de CNPJs | ~$2–6 / 1.000 |

**Dica**: Crie **Tasks** (versões salvas dos Actors com inputs default) no console da Apify para facilitar chamadas.

### 3. Arquitetura do Projeto (Padrões Simples e Evolutíveis)

#### Estrutura de Pastas (Laravel)
```
app/
  ├── Models/
  │     ├── ScrapeRequest.php
  │     ├── Lead.php
  │     └── LeadEnrichment.php (opcional)
  ├── Jobs/
  │     ├── ProcessScrapeRequest.php     (orquestrador)
  │     └── RunApifyActor.php           (genérico por tipo)
  ├── Services/
  │     └── ApifyService.php            (cliente Guzzle + lógica)
  ├── Enrichers/                        (enriquecimento posterior)
  │     ├── GoogleMapsEnricher.php
  │     └── CnpjEnricher.php
  ├── Repositories/                     (opcional, para queries complexas)
  ├── Console/Commands/                 (orquestrador via scheduler ou manual)
  └── Http/
        ├── Controllers/
        │     └── ScrapeRequestController.php
        └── Resources/
              └── LeadResource.php
```

#### Banco de Dados (Principais Tabelas)

**scrape_requests**
- `id`, `type` (maps, instagram, linkedin, cnpj), `filters` (JSON: cidade, uf, segmento, keywords etc.), `status` (pending, running, completed, failed), `apify_run_id`, `dataset_id`, `error_message`, `created_at`, `updated_at`

**leads**
- `id`, `source_type`, `source_id` (ex: place_id do Google), `name`, `fantasy_name`, `cnpj`, `email`, `phone`, `address`, `city`, `uf`, `website`, `instagram`, `linkedin`, `raw_data` (JSON), `status`, `enriched_at`

**lead_sources** ou relação muitos-para-muitos se precisar rastrear origens múltiplas.

### 4. Fluxo Detalhado

1. **Endpoint** (`POST /api/scrape-requests`)
   - Validação (Form Request)
   - Cria `ScrapeRequest` com status `pending`
   - Dispara Job `ProcessScrapeRequest` ou retorna ID

2. **Orquestrador** (`ProcessScrapeRequest` Job)
   - Lê requests pendentes (ou por ID)
   - Chama `ApifyService->runActor($request)`
   - Atualiza status para `running`
   - (Opcional: dispatch Webhook da Apify para callback automático)

3. **ApifyService** (classe central)
   - Métodos: `runActor(string $actorId, array $input)`, `getDatasetItems($datasetId)`, `getRunStatus($runId)`
   - Usa Guzzle com Bearer Token (`.env: APIFY_TOKEN`)

4. **Callback / Polling**
   - **Melhor opção**: Configure **Webhook** no run da Apify apontando para seu endpoint `/webhooks/apify`.
   - Alternativa: Job de polling a cada 30-60s (Laravel Scheduler).

5. **Persistência dos Leads**
   - Ao receber dados do Dataset → loop e upsert no modelo `Lead` (usando `source_id` + `source_type` como unique).
   - Enriquecimento posterior (outro Job): ex: se veio do Maps → buscar CNPJ → atualizar campos.

### 5. Implementação do Cliente Apify (Resumo)

Use a classe que passei anteriormente e expanda:

```php
class ApifyService
{
    public function runActor(string $actorId, array $input): array
    {
        // POST /acts/{actorId}/runs
        // Retorna runId e datasetId
    }

    public function getDatasetItems(string $datasetId, int $limit = 1000): array
    {
        // GET /datasets/{datasetId}/items
    }

    // + webhook handler
}
```

### 6. Boas Práticas para Manutenção e Evolução

- **Config-driven**: Crie um arquivo `config/apify.php` com mapeamento `type => actor_id + default_inputs`.
- **Events**: `ScrapeRequestCreated`, `LeadsImported` → listeners para notificações ou enriquecimento.
- **Queue**: Use Redis (melhor) ou database. Defina retries e timeouts.
- **Logging & Monitoring**: Log completo de cada run + Sentry ou Flare.
- **Rate Limits & Custos**: Controle de consumo por usuário/empresa.
- **LGPD**: Consentimento onde aplicável, armazenamento mínimo, direito ao esquecimento.
- **Testes**: Feature tests no endpoint + Unit tests no ApifyService (mock Guzzle).