<?php
/**
 * CPF obrigatório e válido, um por conta: no checkout (em blocos), no
 * cadastro do site e no do app. Desestimula conta "laranja" para repassar
 * e-book, e o CPF vai, completo, na marca d'água do PDF do leitor.
 *
 * O plugin "Extra Checkout Fields for Brazil" só atua no checkout clássico;
 * no checkout em blocos o campo vem daqui (API de campos adicionais do
 * WooCommerce) e é copiado para billing_cpf / _billing_cpf, onde a Pagar.me,
 * o Hub e o resto da loja já procuram.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_CPF_CAMPO = 'conexao/cpf';

/** Só os 11 dígitos. */
function conexao_cpf_digitos(string $cpf): string
{
    return (string) preg_replace('/\D/', '', $cpf);
}

/** 123.456.789-09 */
function conexao_cpf_formatado(string $cpf): string
{
    $d = conexao_cpf_digitos($cpf);

    return strlen($d) === 11 ? substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2) : $cpf;
}

/** Dígitos verificadores; sequências repetidas (111.111.111-11) não valem. */
function conexao_cpf_valido(string $cpf): bool
{
    $d = conexao_cpf_digitos($cpf);

    if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;

        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $d[$i] * (($t + 1) - $i);
        }

        $digito = ((10 * $soma) % 11) % 10;

        if ((int) $d[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

/** Outra conta já usa este CPF? (gravado com ou sem pontuação) */
function conexao_cpf_em_uso(string $cpf, int $exceto_usuario = 0): bool
{
    global $wpdb;

    $d = conexao_cpf_digitos($cpf);

    if ($d === '') {
        return false;
    }

    $dono = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_cpf' AND meta_value IN (%s, %s) AND user_id <> %d LIMIT 1",
        $d,
        conexao_cpf_formatado($d),
        $exceto_usuario
    ));

    return $dono > 0;
}

/** Mensagem do problema com o CPF, ou '' quando está tudo certo. */
function conexao_cpf_problema(string $cpf, int $usuario = 0): string
{
    if (conexao_cpf_digitos($cpf) === '') {
        return 'Informe o seu CPF.';
    }

    if (! conexao_cpf_valido($cpf)) {
        return 'Este CPF não é válido. Confira os números.';
    }

    if (conexao_cpf_em_uso($cpf, $usuario)) {
        return 'Este CPF já tem uma conta na Conexão Editora. Entre com ela ou use "Esqueci minha senha".';
    }

    return '';
}

/** Grava no lugar onde a loja inteira (Pagar.me, Hub, marca d'água) procura. */
function conexao_cpf_guarda_no_usuario(int $usuario, string $cpf): void
{
    if ($usuario > 0 && conexao_cpf_valido($cpf)) {
        update_user_meta($usuario, 'billing_cpf', conexao_cpf_formatado($cpf));
    }
}

// ---------------------------------------------------------------- checkout em blocos

add_action('woocommerce_init', function (): void {
    if (! function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    woocommerce_register_additional_checkout_field([
        'id' => CONEXAO_CPF_CAMPO,
        'label' => 'CPF',
        'location' => 'contact',
        'type' => 'text',
        'required' => true,
        'attributes' => [
            'autocomplete' => 'off',
            'inputmode' => 'numeric',
            'maxLength' => 14,
            'pattern' => '[0-9.\\-]{11,14}',
            'title' => 'Somente os números do CPF',
        ],
        'sanitize_callback' => static fn ($valor) => conexao_cpf_formatado(conexao_cpf_digitos((string) $valor)),
        'validate_callback' => static function ($valor) {
            $problema = conexao_cpf_problema((string) $valor, get_current_user_id());

            return $problema === '' ? true : new WP_Error('conexao_cpf', $problema);
        },
    ]);
});

// quem já tem CPF na conta (inclusive os vindos da Pubcon) encontra o campo preenchido
add_filter('woocommerce_get_default_value_for_'.CONEXAO_CPF_CAMPO, function ($valor, $grupo, $objeto) {
    $usuario = get_current_user_id();

    return $valor ?: ($usuario ? (string) get_user_meta($usuario, 'billing_cpf', true) : $valor);
}, 10, 3);

// o CPF digitado vai para o pedido (_billing_cpf) e para a conta (billing_cpf)
add_action('woocommerce_store_api_checkout_order_processed', function ($pedido): void {
    $cpf = (string) $pedido->get_meta('_wc_other/'.CONEXAO_CPF_CAMPO);

    if ($cpf === '' && class_exists('\Automattic\WooCommerce\Blocks\Package')) {
        try {
            $campos = \Automattic\WooCommerce\Blocks\Package::container()->get(\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
            $cpf = (string) $campos->get_field_from_object(CONEXAO_CPF_CAMPO, $pedido, 'other');
        } catch (\Throwable $erro) {
            $cpf = '';
        }
    }

    if (! conexao_cpf_valido($cpf)) {
        return;
    }

    $pedido->update_meta_data('_billing_cpf', conexao_cpf_formatado($cpf));
    $pedido->save();
    conexao_cpf_guarda_no_usuario((int) $pedido->get_customer_id(), $cpf);
});

// ---------------------------------------------------------------- cadastro do site

add_filter('woocommerce_process_registration_errors', function ($erros) {
    $cpf = isset($_POST['conexao_cpf']) ? sanitize_text_field(wp_unslash($_POST['conexao_cpf'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $problema = conexao_cpf_problema($cpf);

    if ($problema !== '') {
        $erros->add('conexao_cpf', $problema);
    }

    return $erros;
}, 20);

add_action('woocommerce_created_customer', function (int $cliente_id): void {
    $cpf = isset($_POST['conexao_cpf']) ? sanitize_text_field(wp_unslash($_POST['conexao_cpf'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    conexao_cpf_guarda_no_usuario($cliente_id, $cpf);
});

// "Detalhes da conta": o CPF aparece e pode ser corrigido (com as mesmas regras)
add_action('woocommerce_edit_account_form_start', function (): void {
    $cpf = (string) get_user_meta(get_current_user_id(), 'billing_cpf', true);
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="conexao_cpf">CPF&nbsp;<span class="required" aria-hidden="true">*</span></label>
        <input type="text" class="woocommerce-Input input-text" name="conexao_cpf" id="conexao_cpf" inputmode="numeric" maxlength="14" value="<?php echo esc_attr($cpf); ?>" required>
    </p>
    <?php
});

add_action('woocommerce_save_account_details_errors', function ($erros, $usuario): void {
    $cpf = isset($_POST['conexao_cpf']) ? sanitize_text_field(wp_unslash($_POST['conexao_cpf'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $problema = conexao_cpf_problema($cpf, (int) $usuario->ID);

    if ($problema !== '') {
        $erros->add('conexao_cpf', $problema);
    }
}, 10, 2);

add_action('woocommerce_save_account_details', function (int $usuario): void {
    $cpf = isset($_POST['conexao_cpf']) ? sanitize_text_field(wp_unslash($_POST['conexao_cpf'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    conexao_cpf_guarda_no_usuario($usuario, $cpf);
});
