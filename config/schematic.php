<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for all Schematic database tables.
    |
    */
    'table_prefix' => 'schematic_',

    /*
    |--------------------------------------------------------------------------
    | JSON Schema Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings used when generating JSON Schema output.
    |
    */
    'schema' => [
        'draft' => 'https://json-schema.org/draft/2020-12/schema',
        'strict' => true, // Enforce additionalProperties: false
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Models
    |--------------------------------------------------------------------------
    |
    | Override these if you extend the base models.
    |
    */
    'models' => [
        'template' => \Yannelli\Schematic\Models\Template::class,
        'section' => \Yannelli\Schematic\Models\Section::class,
    ],

];
