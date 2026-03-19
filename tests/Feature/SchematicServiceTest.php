<?php

use Yannelli\Schematic\Compiler;
use Yannelli\Schematic\Models\Template;
use Yannelli\Schematic\Schematic;

beforeEach(function () {
    $this->schematic = app(Schematic::class);
});

// ---------------------------------------------------------------
// Template CRUD
// ---------------------------------------------------------------

it('creates a template via service', function () {
    $template = $this->schematic->create(
        slug: 'service-test',
        name: 'Service Test',
        description: 'Created via Schematic service',
    );

    expect($template)->toBeInstanceOf(Template::class)
        ->and($template->slug)->toBe('service-test');
});

it('finds a template by slug', function () {
    $this->schematic->create(slug: 'findable', name: 'Findable');

    $found = $this->schematic->find('findable');

    expect($found)->not->toBeNull()
        ->and($found->slug)->toBe('findable');
});

it('returns null for missing template', function () {
    expect($this->schematic->find('ghost'))->toBeNull();
});

it('throws on findOrFail with missing template', function () {
    $this->schematic->findOrFail('ghost');
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

// ---------------------------------------------------------------
// Macros
// ---------------------------------------------------------------

it('registers macros through schematic service', function () {
    $this->schematic->macro('bold', fn (string $text) => "**{$text}**");

    expect($this->schematic->hasMacro('bold'))->toBeTrue();
});

it('proxies macros to compiler', function () {
    $this->schematic->macro('wrap', fn (string $t) => "[{$t}]");

    $compiler = app(Compiler::class);

    expect($compiler->hasMacro('wrap'))->toBeTrue();
});

// ---------------------------------------------------------------
// Schema Shortcuts
// ---------------------------------------------------------------

it('generates schema by slug', function () {
    $template = $this->schematic->create(slug: 'schema-svc', name: 'Schema');
    $template->addSection(slug: 'data', name: 'Data', fields: [
        ['name' => 'value', 'type' => 'string'],
    ]);

    $schema = $this->schematic->schema('schema-svc');

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('data');
});

it('generates section schema by slugs', function () {
    $template = $this->schematic->create(slug: 'sec-svc', name: 'Sec');
    $template->addSection(slug: 'part', name: 'Part', fields: [
        ['name' => 'key', 'type' => 'string'],
    ]);

    $schema = $this->schematic->sectionSchema('sec-svc', 'part');

    expect($schema['properties'])->toHaveKey('key');
});

it('generates full schema document', function () {
    $template = $this->schematic->create(slug: 'doc-svc', name: 'Document');
    $template->addSection(slug: 's', name: 'S', fields: [
        ['name' => 'f', 'type' => 'string'],
    ]);

    $doc = $this->schematic->schemaDocument('doc-svc');

    expect($doc)->toHaveKey('$schema')
        ->and($doc['title'])->toBe('Document');
});

// ---------------------------------------------------------------
// Render Shortcuts
// ---------------------------------------------------------------

it('renders template by slug', function () {
    $template = $this->schematic->create(slug: 'render-svc', name: 'Render');
    $template->addSection(
        slug: 'body',
        name: 'Body',
        content: 'Value: {{ val }}',
    );

    $result = $this->schematic->render('render-svc', [
        'body' => ['val' => '42'],
    ]);

    expect($result)->toBe('Value: 42');
});

it('previews template by slug', function () {
    $template = $this->schematic->create(slug: 'preview-svc', name: 'Preview');
    $template->addSection(
        slug: 'section',
        name: 'Section',
        content: 'Example: {{ demo }}',
        examples: ['demo' => 'data'],
    );

    $result = $this->schematic->preview('preview-svc');

    expect($result)->toBe('Example: data');
});

// ---------------------------------------------------------------
// Compiler Access
// ---------------------------------------------------------------

it('exposes the compiler instance', function () {
    expect($this->schematic->compiler())->toBeInstanceOf(Compiler::class);
});
