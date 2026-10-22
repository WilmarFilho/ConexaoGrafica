<?php

/*
 * Só o que o Hub muda em relação ao padrão do Livewire (o resto vem do pacote).
 * Upload temporário: o PDF de um livro chega a 200 MB (padrão do Livewire é 12 MB).
 * O limite do PHP (upload_max_filesize / post_max_size) é ajustado no servidor.
 */
return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:204800'], // KB
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
