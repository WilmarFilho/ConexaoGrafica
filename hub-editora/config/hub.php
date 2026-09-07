<?php

/**
 * Credenciais e endpoints das integrações. Tudo vem do .env — nada de
 * segredo em código nem no banco.
 */
return [

    'woocommerce' => [
        'url' => env('WOO_URL'),                       // https://novo.pubcon.com.br
        'key' => env('WOO_CONSUMER_KEY'),
        'secret' => env('WOO_CONSUMER_SECRET'),
        'webhook_secret' => env('WOO_WEBHOOK_SECRET'), // assinatura dos webhooks
        // Quantos dias para trás a varredura periódica olha (rede de segurança do webhook).
        'lookback_days' => (int) env('WOO_LOOKBACK_DAYS', 3),
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

    'bling' => [
        'client_id' => env('BLING_CLIENT_ID'),
        'client_secret' => env('BLING_CLIENT_SECRET'),
        'base_url' => env('BLING_BASE_URL', 'https://api.bling.com.br/Api/v3'),
    ],

];
