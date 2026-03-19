<?php

use Yannelli\Schematic\Ephemeral\EphemeralSection;

// ---------------------------------------------------------------
// Creation
// ---------------------------------------------------------------

it('creates an ephemeral section via constructor', function () {
    $section = new EphemeralSection(
        slug: 'intro',
        name: 'Introduction',
    );

    expect($section->slug)->toBe('intro')
        ->and($section->name)->toBe('Introduction')
        ->and($section->is_enabled)->toBeTrue()
        ->and($section->fields)->toBe([])
        ->and($section->examples)->toBe([]);
});

it('creates an ephemeral section via make', function () {
    $section = EphemeralSection::make(
        slug: 'body',
        name: 'Body',
        description: 'Main content',
        content: 'Hello {{ name }}',
        fields: [['name' => 'name', 'type' => 'string']],
        examples: ['name' => 'World'],
    );

    expect($section->slug)->toBe('body')
        ->and($section->description)->toBe('Main content')
        ->and($section->content)->toBe('Hello {{ name }}');
});

// ---------------------------------------------------------------
// Schema Generation
// ---------------------------------------------------------------

it('generates json schema from fields', function () {
    $section = EphemeralSection::make(
        slug: 'data',
        name: 'Data',
        description: 'Test section',
        fields: [
            ['name' => 'title', 'type' => 'string', 'description' => 'The title', 'required' => true],
            ['name' => 'count', 'type' => 'integer', 'description' => 'A count', 'required' => false],
        ],
    );

    $schema = $section->toJsonSchema();

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('title')
        ->and($schema['properties'])->toHaveKey('count')
        ->and($schema['properties']['title']['type'])->toBe('string')
        ->and($schema['properties']['count']['type'])->toBe('integer')
        ->and($schema['required'])->toBe(['title', 'count'])
        ->and($schema['description'])->toBe('Test section');
});

it('generates schema with enum fields', function () {
    $section = EphemeralSection::make(
        slug: 'status',
        name: 'Status',
        fields: [
            ['name' => 'level', 'type' => 'enum', 'enum' => ['low', 'medium', 'high']],
        ],
    );

    $schema = $section->toJsonSchema();

    expect($schema['properties']['level']['enum'])->toBe(['low', 'medium', 'high']);
});

it('generates schema with nullable fields', function () {
    $section = EphemeralSection::make(
        slug: 'optional',
        name: 'Optional',
        fields: [
            ['name' => 'notes', 'type' => 'string', 'nullable' => true, 'required' => false],
        ],
    );

    $schema = $section->toJsonSchema();

    expect($schema['properties']['notes']['type'])->toBe(['string', 'null'])
        ->and($schema['required'])->toBe(['notes']);
});

// ---------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------

it('renders content with data', function () {
    $section = EphemeralSection::make(
        slug: 'greeting',
        name: 'Greeting',
        content: 'Hello, {{ name }}!',
    );

    expect($section->render(['name' => 'Alice']))->toBe('Hello, Alice!');
});

it('renders empty string when disabled', function () {
    $section = EphemeralSection::make(
        slug: 'disabled',
        name: 'Disabled',
        content: 'Should not render',
        enabled: false,
    );

    expect($section->render())->toBe('');
});

it('previews using example data', function () {
    $section = EphemeralSection::make(
        slug: 'preview',
        name: 'Preview',
        content: 'Value: {{ val }}',
        examples: ['val' => '42'],
    );

    expect($section->preview())->toBe('Value: 42');
});

it('renders with conditionals', function () {
    $section = EphemeralSection::make(
        slug: 'cond',
        name: 'Conditional',
        content: '@if(show)Visible@endif',
    );

    expect($section->render(['show' => true]))->toBe('Visible')
        ->and($section->render(['show' => false]))->toBe('');
});

it('renders with loops', function () {
    $section = EphemeralSection::make(
        slug: 'loop',
        name: 'Loop',
        content: '@foreach(items as item)- {{ item }}@endforeach',
    );

    $result = $section->render(['items' => ['a', 'b', 'c']]);

    expect($result)->toContain('- a')
        ->and($result)->toContain('- b')
        ->and($result)->toContain('- c');
});

// ---------------------------------------------------------------
// Fluent Builders
// ---------------------------------------------------------------

it('adds a field fluently', function () {
    $section = EphemeralSection::make(slug: 'test', name: 'Test');

    $result = $section->addField('email', 'string', 'Email address');

    expect($result)->toBe($section)
        ->and($section->fields)->toHaveCount(1)
        ->and($section->fields[0]['name'])->toBe('email')
        ->and($section->fields[0]['type'])->toBe('string');
});

it('removes a field fluently', function () {
    $section = EphemeralSection::make(
        slug: 'test',
        name: 'Test',
        fields: [
            ['name' => 'keep', 'type' => 'string'],
            ['name' => 'remove', 'type' => 'string'],
        ],
    );

    $result = $section->removeField('remove');

    expect($result)->toBe($section)
        ->and($section->fields)->toHaveCount(1)
        ->and($section->fields[0]['name'])->toBe('keep');
});

it('enables and disables fluently', function () {
    $section = EphemeralSection::make(slug: 'toggle', name: 'Toggle');

    expect($section->disable())->toBe($section)
        ->and($section->is_enabled)->toBeFalse();

    expect($section->enable())->toBe($section)
        ->and($section->is_enabled)->toBeTrue();
});

it('sets examples fluently', function () {
    $section = EphemeralSection::make(slug: 'ex', name: 'Ex');

    $result = $section->setExamples(['key' => 'value']);

    expect($result)->toBe($section)
        ->and($section->examples)->toBe(['key' => 'value']);
});

it('returns field definitions as FieldDefinition objects', function () {
    $section = EphemeralSection::make(
        slug: 'defs',
        name: 'Defs',
        fields: [
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'count', 'type' => 'integer'],
        ],
    );

    $definitions = $section->fieldDefinitions();

    expect($definitions)->toHaveCount(2)
        ->and($definitions[0])->toBeInstanceOf(\Yannelli\Schematic\FieldDefinition::class)
        ->and($definitions[0]->name)->toBe('title')
        ->and($definitions[1]->type)->toBe('integer');
});
