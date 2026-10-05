<?php
/**
 * Aparência › Personalizar › Contato e redes sociais: telefones, e-mail,
 * endereço, links das redes e a chave do Google Maps do mapa da página de contato.
 * Os valores padrão são os mesmos que os modelos usam quando nada foi salvo.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('customize_register', function (WP_Customize_Manager $personalizar): void {
    $personalizar->add_section('conexao_contato', [
        'title' => 'Contato e redes sociais',
        'priority' => 30,
    ]);

    $texto = 'sanitize_text_field';
    $campos = [
        'conexao_whatsapp' => ['WhatsApp (só números, com DDI e DDD)', '5562998228022', static fn ($v) => preg_replace('/\D+/', '', (string) $v), 'text', ''],
        'conexao_telefones' => ['Telefones (separados por /)', '62 99822-8022 / 62 3229.6147', $texto, 'text', ''],
        'conexao_email' => ['E-mail de contato', 'contato@conexaoeditora.com.br', 'sanitize_email', 'email', ''],
        'conexao_horario' => ['Horário de atendimento', 'Seg. a sex: 8h às 18h', $texto, 'text', ''],
        'conexao_endereco' => ['Endereço', 'Rua 227 A, 20 — Setor Leste Universitário, Goiânia - GO', $texto, 'text', 'É por ele que o mapa encontra a editora.'],
        'conexao_instagram' => ['Instagram (link do perfil)', '', 'esc_url_raw', 'url', ''],
        'conexao_facebook' => ['Facebook (link da página)', '', 'esc_url_raw', 'url', ''],
        'conexao_youtube' => ['YouTube (link do canal)', '', 'esc_url_raw', 'url', ''],
        'conexao_maps_chave' => ['Chave do Google Maps', '', $texto, 'text', 'Chave da Maps JavaScript API (com a Geocoding API ativa), restrita ao domínio do site. Com ela, o contato mostra o mapa azul; sem ela, o mapa comum do Google.'],
        'conexao_mapa_coordenadas' => ['Coordenadas do pino (opcional)', '', static fn ($v) => preg_match('/^\s*-?\d{1,2}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?\s*$/', (string) $v) ? preg_replace('/\s+/', '', (string) $v) : '', 'text', 'Latitude,longitude, por exemplo -16.6721,-49.2419. Em branco, o mapa localiza pelo endereço.'],
    ];

    foreach ($campos as $chave => [$rotulo, $padrao, $limpa, $tipo, $ajuda]) {
        $personalizar->add_setting($chave, ['default' => $padrao, 'sanitize_callback' => $limpa]);
        $personalizar->add_control($chave, [
            'label' => $rotulo,
            'section' => 'conexao_contato',
            'type' => $tipo,
            'description' => $ajuda,
        ]);
    }
});
