<?php

namespace App\Editorial;

use DomainException;

/**
 * Regra editorial violada (transição inválida, campo imutável, checklist
 * pendente, perfil sem permissão). A mensagem é mostrada ao usuário.
 */
class EditorialException extends DomainException {}
