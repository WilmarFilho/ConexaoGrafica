<?php

/**
 * Credenciais e endpoints das integrações. Tudo vem do .env — nada de
 * segredo em código nem no banco.
 */
return [

    'woocommerce' => [
        'url' => env('WOO_URL'),                       // https://conexaoeditora.com.br
        'key' => env('WOO_CONSUMER_KEY'),
        'secret' => env('WOO_CONSUMER_SECRET'),
        'webhook_secret' => env('WOO_WEBHOOK_SECRET'), // assinatura dos webhooks
        // Quantos dias para trás a varredura periódica olha (rede de segurança do webhook).
        'lookback_days' => (int) env('WOO_LOOKBACK_DAYS', 3),
        // false congela o catálogo: pedidos continuam chegando, mas títulos,
        // formatos e medidas não são atualizados pela loja (usado enquanto a
        // loja ativa não é a do catálogo que está no hub).
        'sync_products' => filter_var(env('WOO_SYNC_PRODUCTS', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'pagarme' => [
        'secret_key' => env('PAGARME_SECRET_KEY'),     // sk_... criada no Dash, própria do hub
        'base_url' => env('PAGARME_BASE_URL', 'https://api.pagar.me/core/v5'),
        'webhook_secret' => env('PAGARME_WEBHOOK_SECRET'),
    ],

    'melhor_envio' => [
        'token' => env('ME_TOKEN'),                     // token do app criado no painel do ME
        'sandbox' => (bool) env('ME_SANDBOX', true),
        'base_url' => env('ME_BASE_URL') ?: ((bool) env('ME_SANDBOX', true)
            ? 'https://sandbox.melhorenvio.com.br/api/v2'
            : 'https://melhorenvio.com.br/api/v2'),
        'user_agent' => env('ME_USER_AGENT', 'Hub Editora (contato@editora)'),

        // Remetente (aparece na etiqueta). CEP de origem = ME_FROM_ZIP.
        'from' => [
            'name' => env('ME_FROM_NAME', 'Editora'),
            'phone' => env('ME_FROM_PHONE'),
            'email' => env('ME_FROM_EMAIL'),
            'document' => env('ME_FROM_DOCUMENT'),          // CPF (11 dígitos)
            'company_document' => env('ME_FROM_CNPJ'),      // CNPJ (14 dígitos), se pessoa jurídica
            'address' => env('ME_FROM_ADDRESS'),
            'number' => env('ME_FROM_NUMBER'),
            'complement' => env('ME_FROM_COMPLEMENT'),
            'district' => env('ME_FROM_DISTRICT'),
            'city' => env('ME_FROM_CITY'),
            'state' => env('ME_FROM_STATE'),
            'postal_code' => env('ME_FROM_ZIP'),
        ],

        // Pacote padrão quando o produto não tem peso/dimensões cadastrados
        // (um livro médio). Em cm e gramas; o ME recebe em kg.
        'default_package' => [
            'weight_grams' => (int) env('ME_DEFAULT_WEIGHT_G', 350),
            'width_cm' => (int) env('ME_DEFAULT_WIDTH_CM', 16),
            'height_cm' => (int) env('ME_DEFAULT_HEIGHT_CM', 23),
            'depth_cm' => (int) env('ME_DEFAULT_DEPTH_CM', 3),
        ],

        // Serviços considerados na cotação (ids do ME). Vazio = todos.
        // 1 PAC, 2 SEDEX, 3 Jadlog .Package, 4 Jadlog .Com, 17 Mini Envios (Correios).
        'services' => array_filter(array_map('trim', explode(',', (string) env('ME_SERVICES', '1,2,3,17')))),

        // Sem NF-e por padrão: vai como "não comercial" (declaração de conteúdo).
        'non_commercial' => (bool) env('ME_NON_COMMERCIAL', true),
    ],

    // Retenção de dados pessoais de compradores da Amazon. A Política de
    // Proteção de Dados da Amazon exige apagar em até 30 dias após o envio;
    // por isso o valor efetivo nunca passa de 30 (o hub aplica min()). Usamos 15
    // porque os backups guardam 14 dias: nenhuma cópia passa de 30 dias.
    'retention' => [
        'amazon_pii_days' => min(30, max(1, (int) env('AMAZON_PII_RETENTION_DAYS', 15))),
    ],

    // Backup diário cifrado fora do servidor (hub:backup). A chave fica só no
    // .env e numa cópia guardada pelo responsável (sem ela não há restauração).
    'backup' => [
        'key' => env('BACKUP_ENCRYPTION_KEY'),           // base64:… (32 bytes), gerada com hub:backup --gerar-chave
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],

    // Varredura mensal de vulnerabilidades (hub:security-scan).
    'security' => [
        'composer_bin' => env('COMPOSER_BIN', rtrim((string) getenv('HOME'), '/').'/bin/composer'),
    ],

    // Conexão DOI: publicação dos livros no site (plugin conexao-doi do WordPress).
    // O Hub chama o WP com HMAC-SHA256 (contrato, seção 4); o WP nunca chama o Hub.
    // App do leitor: o site (plugin conexao-app) consulta pedidos por e-mail, assinado.
    'app' => [
        'secret' => env('HUB_APP_SECRET'), // 64 caracteres, o mesmo CONEXAO_APP_HUB_SECRET do wp-config.php
    ],

    'wordpress' => [
        'url' => env('CONEXAO_DOI_WP_URL'),        // https://conexaoeditora.com.br
        'secret' => env('CONEXAO_DOI_WP_SECRET'),  // 64 caracteres, o mesmo CONEXAO_DOI_SECRET do wp-config.php
    ],

    // Conexão DOI: fichas científicas em PDF (frente E). Com auto_publish, o
    // PublishBook encadeia a geração e o envio das fichas depois do PUT.
    'documents' => [
        'auto_publish' => filter_var(env('HUB_FICHAS_AUTOMATICAS', true), FILTER_VALIDATE_BOOLEAN),
    ],

    // Amazon Seller Central (SP-API, autorização própria via Login with Amazon).
    'amazon' => [
        'client_id' => env('AMAZON_LWA_CLIENT_ID'),
        'client_secret' => env('AMAZON_LWA_CLIENT_SECRET'),
        'refresh_token' => env('AMAZON_REFRESH_TOKEN'),
        'marketplace_id' => env('AMAZON_MARKETPLACE_ID', 'A2Q3Y263D00KWC'), // Brasil
        'endpoint' => env('AMAZON_SP_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com'),
        'token_url' => 'https://api.amazon.com/auth/o2/token',
    ],

    // Importação de livros em PDF com IA (Conexão DOI, frente C). Chave só no .env;
    // sem ela o comando hub:importar-pdf avisa "não configurado" e não faz nada.
    'ai' => [
        // Provedor da IA: openai (Responses API) ou anthropic (Messages API). Trocar é só
        // mudar HUB_AI_PROVIDER: a chave e o modelo de cada um ficam em variáveis próprias.
        'provider' => env('HUB_AI_PROVIDER', 'openai'),
        'key' => env('HUB_AI_PROVIDER', 'openai') === 'anthropic' ? env('ANTHROPIC_API_KEY') : env('OPENAI_API_KEY'),
        // OpenAI: gpt-6-astra é o mais capaz; gpt-6.1-sol custa 1/5 com desempenho próximo.
        // Anthropic: claude-opus-5-5 (padrão); claude-sonnet-5-5 custa metade.
        'model' => env('HUB_AI_PROVIDER', 'openai') === 'anthropic'
            ? env('HUB_AI_ANTHROPIC_MODEL', 'claude-opus-5-5')
            : env('HUB_AI_MODEL', 'gpt-6-astra'),
        // Profundidade de raciocínio (low|medium|high|xhigh|max).
        'effort' => env('HUB_AI_EFFORT', 'medium'),
        // Quantas páginas iniciais (capa, ficha, autores, sumário) vão como PDF ao modelo.
        'front_matter_pages' => (int) env('HUB_AI_FRONT_MATTER_PAGES', 30),
        // Texto de capítulo acima disso é dividido em partes e consolidado depois.
        'max_chapter_tokens' => (int) env('HUB_AI_MAX_CHAPTER_TOKENS', 60000),
        // Limite de saída por chamada; inclui os tokens de raciocínio, por isso folgado.
        'max_output_tokens' => (int) env('HUB_AI_MAX_OUTPUT_TOKENS', 32000),
        // Ghostscript recorta as páginas iniciais do PDF; sem ele, PDFs até
        // 'whole_pdf_max_pages' páginas vão inteiros e os maiores vão como texto.
        'gs_bin' => env('HUB_GS_BIN', 'gs'),
        'whole_pdf_max_pages' => (int) env('HUB_AI_WHOLE_PDF_MAX_PAGES', 100),
        // Preço por milhão de tokens (US$, tabela da OpenAI em 22/10/2026, contexto ≤ 272K),
        // para o custo estimado gravado em imports.usage.
        'prices' => [
            'gpt-6-astra' => ['input' => 10.00, 'cached_input' => 1.00, 'output' => 50.00],
            'gpt-6.1-sol' => ['input' => 2.00, 'cached_input' => 0.10, 'output' => 10.00],
            'gpt-6-luna' => ['input' => 0.10, 'cached_input' => 0.01, 'output' => 0.50],
            'gpt-5.5' => ['input' => 5.00, 'cached_input' => 0.50, 'output' => 30.00],
            // Anthropic (US$ por milhão; cached_input = leitura de cache)
            'claude-opus-5-5' => ['input' => 4.00, 'cached_input' => 0.20, 'output' => 20.00],
            'claude-sonnet-5-5' => ['input' => 2.00, 'cached_input' => 0.20, 'output' => 10.00],
            'claude-haiku-5-5' => ['input' => 0.10, 'cached_input' => 0.01, 'output' => 0.50],
        ],
    ],

    // Conexão DOI: depósito de metadados na Crossref (App\Crossref). Em `test`
    // o envio vai para test.crossref.org (homologação); `production` registra de verdade.
    'crossref' => [
        'env' => env('CROSSREF_ENV', 'test'),                   // test | production
        // O ambiente de teste (sandbox) tem senha própria: CROSSREF_TEST_* valem em `test`
        // e, se faltarem, caem nas de produção. Em `production` só valem CROSSREF_LOGIN/PASSWORD.
        'login' => env('CROSSREF_ENV', 'test') === 'test'
            ? env('CROSSREF_TEST_LOGIN', env('CROSSREF_LOGIN'))
            : env('CROSSREF_LOGIN'),                             // formato e-mail/role (ex.: usuario@editora/bcfg)
        'password' => env('CROSSREF_ENV', 'test') === 'test'
            ? env('CROSSREF_TEST_PASSWORD', env('CROSSREF_PASSWORD'))
            : env('CROSSREF_PASSWORD'),
        'prefix' => env('CROSSREF_PREFIX', '10.00000'),          // 10.00000 é só exemplo; o prefixo real da editora vai no .env (10.68564)
        'depositor_name' => env('CROSSREF_DEPOSITOR_NAME', 'Conexão Editora'),
        'depositor_email' => env('CROSSREF_DEPOSITOR_EMAIL'),  // recebe o e-mail de resultado da Crossref
        'registrant' => env('CROSSREF_REGISTRANT', 'Conexão Editora'),
        // Base das páginas públicas, usada só quando o payload não traz `landing_url`
        // (o WordPress devolve as URLs definitivas ao publicar; são elas que valem).
        'landing_base' => env('CROSSREF_LANDING_BASE', 'https://conexaoeditora.com.br'),
    ],

];
