<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | Credenciais usadas pela facade `OpenAI::` do pacote openai-php/laravel.
    | Obrigatório: OPENAI_API_KEY. Opcionais: organization, project, base_uri.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'project' => env('OPENAI_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Base URI (opcional)
    |--------------------------------------------------------------------------
    |
    | Útil quando se usa um proxy ou endpoint compatível com a API da OpenAI.
    |
    */

    'base_uri' => env('OPENAI_BASE_URI'),

    /*
    |--------------------------------------------------------------------------
    | Modelo Padrão do Sistema
    |--------------------------------------------------------------------------
    |
    | Chave custom usada pelos services internos (ex.: RequirementGenerator)
    | para saber qual modelo chamar. Não faz parte do contrato do pacote.
    |
    */

    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

];
