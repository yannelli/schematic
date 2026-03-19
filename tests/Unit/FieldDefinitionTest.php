<?php

use Yannelli\Schematic\FieldDefinition;

// ---------------------------------------------------------------
// Construction
// ---------------------------------------------------------------

it('creates a field definition with defaults', function () {
    $field = new FieldDefinition(name: 'title');

    expect($field->name)->toBe('title')
        ->and($field->type)->toBe('string')
        ->and($field->required)->toBeTrue()
        ->and($field->nullable)->toBeFalse()
        ->and($field->description)->toBe('');
});

it('throws on invalid type', function () {
    new FieldDefinition(name: 'bad', type: 'invalid');
})->throws(InvalidArgumentException::class);

it('accepts all valid types', function (string $type) {
    $field = new FieldDefinition(name: 'test', type: $type);

    expect($field->type)->toBe($type);
})->with(['string', 'integer', 'number', 'boolean', 'array', 'object', 'enum']);

// ---------------------------------------------------------------
// fromArray
// ---------------------------------------------------------------

it('creates from array with minimal data', function () {
    $field = FieldDefinition::fromArray(['name' => 'age', 'type' => 'integer']);

    expect($field->name)->toBe('age')
        ->and($field->type)->toBe('integer')
        ->and($field->required)->toBeTrue();
});

it('creates from array with all options', function () {
    $field = FieldDefinition::fromArray([
        'name' => 'status',
        'type' => 'enum',
        'description' => 'Current status',
        'required' => false,
        'nullable' => true,
        'default' => 'active',
        'enum' => ['active', 'inactive'],
    ]);

    expect($field->name)->toBe('status')
        ->and($field->type)->toBe('enum')
        ->and($field->description)->toBe('Current status')
        ->and($field->required)->toBeFalse()
        ->and($field->nullable)->toBeTrue()
        ->and($field->default)->toBe('active')
        ->and($field->enum)->toBe(['active', 'inactive']);
});

// ---------------------------------------------------------------
// toJsonSchemaProperty
// ---------------------------------------------------------------

it('generates string schema', function () {
    $schema = (new FieldDefinition(name: 'title'))->toJsonSchemaProperty();

    expect($schema)->toBe(['type' => 'string']);
});

it('generates integer schema', function () {
    $schema = (new FieldDefinition(name: 'age', type: 'integer'))->toJsonSchemaProperty();

    expect($schema)->toBe(['type' => 'integer']);
});

it('generates number schema', function () {
    $schema = (new FieldDefinition(name: 'price', type: 'number'))->toJsonSchemaProperty();

    expect($schema)->toBe(['type' => 'number']);
});

it('generates boolean schema', function () {
    $schema = (new FieldDefinition(name: 'active', type: 'boolean'))->toJsonSchemaProperty();

    expect($schema)->toBe(['type' => 'boolean']);
});

it('generates enum schema', function () {
    $schema = (new FieldDefinition(
        name: 'color',
        type: 'enum',
        enum: ['red', 'green', 'blue'],
    ))->toJsonSchemaProperty();

    expect($schema)->toBe([
        'type' => 'string',
        'enum' => ['red', 'green', 'blue'],
    ]);
});

it('generates array schema with default items', function () {
    $schema = (new FieldDefinition(name: 'tags', type: 'array'))->toJsonSchemaProperty();

    expect($schema)->toBe([
        'type' => 'array',
        'items' => ['type' => 'string'],
    ]);
});

it('generates array schema with custom items', function () {
    $schema = (new FieldDefinition(
        name: 'scores',
        type: 'array',
        items: ['type' => 'integer'],
    ))->toJsonSchemaProperty();

    expect($schema)->toBe([
        'type' => 'array',
        'items' => ['type' => 'integer'],
    ]);
});

it('generates object schema', function () {
    $props = ['name' => ['type' => 'string']];
    $schema = (new FieldDefinition(
        name: 'address',
        type: 'object',
        properties: $props,
    ))->toJsonSchemaProperty();

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toBe($props);
});

it('adds nullable to schema', function () {
    $schema = (new FieldDefinition(
        name: 'note',
        nullable: true,
    ))->toJsonSchemaProperty();

    expect($schema['type'])->toBe(['string', 'null']);
});

it('includes description when present', function () {
    $schema = (new FieldDefinition(
        name: 'bio',
        description: 'A short biography',
    ))->toJsonSchemaProperty();

    expect($schema['description'])->toBe('A short biography');
});

it('includes default when present', function () {
    $schema = (new FieldDefinition(
        name: 'role',
        default: 'user',
    ))->toJsonSchemaProperty();

    expect($schema['default'])->toBe('user');
});

// ---------------------------------------------------------------
// jsonSerialize
// ---------------------------------------------------------------

it('serializes with only non-empty values', function () {
    $field = new FieldDefinition(name: 'simple');
    $serialized = $field->jsonSerialize();

    expect($serialized)->toHaveKey('name', 'simple')
        ->toHaveKey('type', 'string')
        ->toHaveKey('required', true)
        ->not->toHaveKey('description')
        ->not->toHaveKey('default')
        ->not->toHaveKey('enum');
});

it('serializes all populated values', function () {
    $field = new FieldDefinition(
        name: 'status',
        type: 'enum',
        description: 'The status',
        required: true,
        nullable: true,
        default: 'active',
        enum: ['active', 'inactive'],
    );

    $serialized = $field->jsonSerialize();

    expect($serialized)
        ->toHaveKey('name', 'status')
        ->toHaveKey('type', 'enum')
        ->toHaveKey('description', 'The status')
        ->toHaveKey('required', true)
        ->toHaveKey('nullable', true)
        ->toHaveKey('default', 'active')
        ->toHaveKey('enum', ['active', 'inactive']);
});
