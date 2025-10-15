<?php

declare(strict_types=1);

return [
    'fake_sleep' => true,
    'prevent_stray_requests' => true,
    'force_https' => true,
    'aggressive_prefetching' => true,
    'immutable_dates' => true,
    'unguard_model' => false,
    'strict_model' => true,
    'automatic_eager_load_relationships' => true,
    'prohibit_destructive_commands' => true,
    'set_default_passwords' => true,
    'default_string_length' => 255,
    'default_morph_key_type' => 'int',

    // 'model' => [
    //     'unguard' => false,
    //     'strict' => true,
    //     'automatic_eager_load_relationships' => true,
    // ],

    // 'table' => [
    //     'default_string_length' => 255,
    //     'default_morph_key_type' => 'int',
    // ],

    // 'http' => [
    //     'fake_sleep' => true,
    //     'force_https' => true,
    //     'prevent_stray_requests' => true,
    //     'aggressive_prefetching' => true,
    // ],

    // 'general' => [
    //     'prohibit_destructive_commands' => true,
    //     'set_default_passwords' => true,
    // ],
];
