<?php

use Yannelli\Schematic\Models\Section;
use Yannelli\Schematic\Models\Template;

beforeEach(function () {
    $this->template = Template::create(['slug' => 'test', 'name' => 'Test']);
});

// ---------------------------------------------------------------
// Basics
// ---------------------------------------------------------------

it('belongs to a template', function () {
    $section = $this->template->addSection(slug: 'child', name: 'Child');

    expect($section->template->id)->toBe($this->template->id);
});

it('casts fields as array', function () {
    $section = $this->template->addSection(
        slug: 'fields',
        name: 'Fields',
        fields: [['name' => 'title', 'type' => 'string']],
    );

    $section->refresh();

    expect($section->fields)->toBeArray()
        ->and($section->fields[0]['name'])->toBe('title');
});

it('casts is_enabled as boolean', function () {
    $section = $this->template->addSection(slug: 'bool', name: 'Bool');

    expect($section->is_enabled)->toBeBool()->toBeTrue();
});

// ---------------------------------------------------------------
// Enable / Disable
// ---------------------------------------------------------------

it('disables a section', function () {
    $section = $this->template->addSection(slug: 'toggle', name: 'Toggle');

    $section->disable();

    expect($section->is_enabled)->toBeFalse();
});

it('re-enables a section', function () {
    $section = $this->template->addSection(slug: 'toggle2', name: 'Toggle2', enabled: false);

    $section->enable();

    expect($section->is_enabled)->toBeTrue();
});

// ---------------------------------------------------------------
// Field Management
// ---------------------------------------------------------------

it('adds a field to a section', function () {
    $section = $this->template->addSection(slug: 'add-field', name: 'AF');

    $section->addField(
        name: 'email',
        type: 'string',
        description: 'Email address',
    );

    $section->refresh();

    expect($section->fields)->toHaveCount(1)
        ->and($section->fields[0]['name'])->toBe('email');
});

it('adds multiple fields sequentially', function () {
    $section = $this->template->addSection(slug: 'multi-field', name: 'MF');

    $section->addField(name: 'first', type: 'string');
    $section->addField(name: 'second', type: 'integer');

    $section->refresh();

    expect($section->fields)->toHaveCount(2);
});

it('removes a field by name', function () {
    $section = $this->template->addSection(slug: 'remove-field', name: 'RF', fields: [
        ['name' => 'keep', 'type' => 'string', 'required' => true],
        ['name' => 'remove', 'type' => 'string', 'required' => true],
    ]);

    $section->removeField('remove');
    $section->refresh();

    expect($section->fields)->toHaveCount(1)
        ->and($section->fields[0]['name'])->toBe('keep');
});

// ---------------------------------------------------------------
// Field Definitions Accessor
// ---------------------------------------------------------------

it('parses fields into FieldDefinition objects', function () {
    $section = $this->template->addSection(slug: 'defs', name: 'Defs', fields: [
        ['name' => 'title', 'type' => 'string', 'description' => 'The title'],
        ['name' => 'count', 'type' => 'integer'],
    ]);

    $definitions = $section->field_definitions;

    expect($definitions)->toHaveCount(2)
        ->and($definitions[0]->name)->toBe('title')
        ->and($definitions[1]->type)->toBe('integer');
});

// ---------------------------------------------------------------
// Schema Generation
// ---------------------------------------------------------------

it('generates JSON schema from fields', function () {
    $section = $this->template->addSection(slug: 'schema', name: 'Schema', fields: [
        ['name' => 'title', 'type' => 'string', 'description' => 'The title', 'required' => true],
        ['name' => 'optional', 'type' => 'string', 'required' => false],
    ]);

    $schema = $section->toJsonSchema();

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('title')
        ->and($schema['properties'])->toHaveKey('optional')
        ->and($schema['required'])->toBe(['title', 'optional'])
        ->and($schema['additionalProperties'])->toBeFalse();
});

it('includes section description in schema', function () {
    $section = $this->template->addSection(
        slug: 'desc',
        name: 'Desc',
        description: 'A described section',
        fields: [['name' => 'f', 'type' => 'string']],
    );

    $schema = $section->toJsonSchema();

    expect($schema['description'])->toBe('A described section');
});

// ---------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------

it('renders section content with data', function () {
    $section = $this->template->addSection(
        slug: 'render',
        name: 'Render',
        content: 'Hello {{ name }}!',
    );

    expect($section->render(['name' => 'World']))->toBe('Hello World!');
});

it('returns empty string when disabled', function () {
    $section = $this->template->addSection(
        slug: 'disabled',
        name: 'Disabled',
        content: 'Should not appear',
        enabled: false,
    );

    expect($section->render(['any' => 'data']))->toBe('');
});

it('renders with empty content gracefully', function () {
    $section = $this->template->addSection(slug: 'empty', name: 'Empty');

    expect($section->render())->toBe('');
});

it('previews using example data', function () {
    $section = $this->template->addSection(
        slug: 'preview',
        name: 'Preview',
        content: '{{ greeting }}, {{ name }}!',
        examples: ['greeting' => 'Hi', 'name' => 'Demo'],
    );

    expect($section->preview())->toBe('Hi, Demo!');
});

it('sets examples', function () {
    $section = $this->template->addSection(slug: 'ex', name: 'Ex');

    $section->setExamples(['key' => 'value']);
    $section->refresh();

    expect($section->examples)->toBe(['key' => 'value']);
});
