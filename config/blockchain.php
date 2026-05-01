<?php

return [

    'operator_private_key' => env('OPERATOR_PRIVATE_KEY', ''),

    'admin_private_key' => env('ADMIN_PRIVATE_KEY', ''),

    'alchemy_api_key' => env('ALCHEMY_API_KEY', ''),

    // B11: KeyVault driver — env (default), aws_kms, or vault.
    'key_vault_driver' => env('BLOCKCHAIN_KEY_VAULT_DRIVER', 'env'),

];
