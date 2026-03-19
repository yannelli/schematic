<?php

use Yannelli\Schematic\Ephemeral\EphemeralSection;
use Yannelli\Schematic\Ephemeral\EphemeralTemplate;
use Yannelli\Schematic\Schematic;

// ---------------------------------------------------------------
// Creation
// ---------------------------------------------------------------

it('creates an ephemeral template via constructor', function () {
    $template = new EphemeralTemplate(
        slug: 'quick-note',
        name: 'Quick Note',
        description: 'A quick note template',
    );

    expect($template->slug)->toBe('quick-note')
        ->and($template->name)->toBe('Quick Note')
        ->and($template->description)->toBe('A quick note template');
});

it('creates an ephemeral template via make', function () {
    $template = EphemeralTemplate::make(
        slug: 'test',
        name: 'Test',
        metadata: ['version' => '1.0'],
    );

    expect($template->slug)->toBe('test')
        ->and($template->metadata)->toBe(['version' => '1.0']);
});

it('creates an ephemeral template via schematic service', function () {
    $schematic = app(Schematic::class);

    $template = $schematic->ephemeral('service-ephemeral', 'Service Ephemeral');

    expect($template)->toBeInstanceOf(EphemeralTemplate::class)
        ->and($template->slug)->toBe('service-ephemeral');
});

// ---------------------------------------------------------------
// Section Management
// ---------------------------------------------------------------

it('adds sections and returns the section', function () {
    $template = EphemeralTemplate::make('t', 'T');

    $section = $template->addSection(
        slug: 'intro',
        name: 'Introduction',
        content: 'Hello {{ name }}',
        fields: [['name' => 'name', 'type' => 'string']],
    );

    expect($section)->toBeInstanceOf(EphemeralSection::class)
        ->and($section->slug)->toBe('intro');
});

it('finds a section by slug', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'a', name: 'A');
    $template->addSection(slug: 'b', name: 'B');

    expect($template->section('b'))->not->toBeNull()
        ->and($template->section('b')->name)->toBe('B')
        ->and($template->section('missing'))->toBeNull();
});

it('auto-increments section order', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $s1 = $template->addSection(slug: 'a', name: 'A');
    $s2 = $template->addSection(slug: 'b', name: 'B');
    $s3 = $template->addSection(slug: 'c', name: 'C');

    expect($s1->order)->toBe(0)
        ->and($s2->order)->toBe(1)
        ->and($s3->order)->toBe(2);
});

it('iterates only enabled sections', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'a', name: 'A');
    $template->addSection(slug: 'b', name: 'B', enabled: false);
    $template->addSection(slug: 'c', name: 'C');

    $slugs = $template->iterateSections()->pluck('slug')->all();

    expect($slugs)->toBe(['a', 'c']);
});

it('iterates all sections including disabled', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'a', name: 'A');
    $template->addSection(slug: 'b', name: 'B', enabled: false);

    expect($template->iterateAllSections())->toHaveCount(2);
});

it('reorders sections', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'a', name: 'A');
    $template->addSection(slug: 'b', name: 'B');
    $template->addSection(slug: 'c', name: 'C');

    $template->reorderSections(['c', 'a', 'b']);

    $slugs = $template->iterateSections()->pluck('slug')->all();

    expect($slugs)->toBe(['c', 'a', 'b']);
});

// ---------------------------------------------------------------
// Schema Generation
// ---------------------------------------------------------------

it('generates json schema for all enabled sections', function () {
    $template = EphemeralTemplate::make('t', 'T', 'Description');
    $template->addSection(slug: 'info', name: 'Info', fields: [
        ['name' => 'title', 'type' => 'string'],
    ]);
    $template->addSection(slug: 'hidden', name: 'Hidden', enabled: false, fields: [
        ['name' => 'secret', 'type' => 'string'],
    ]);

    $schema = $template->toJsonSchema();

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('info')
        ->and($schema['properties'])->not->toHaveKey('hidden')
        ->and($schema['required'])->toBe(['info'])
        ->and($schema['description'])->toBe('Description');
});

it('generates full schema document', function () {
    $template = EphemeralTemplate::make('doc', 'Document');
    $template->addSection(slug: 's', name: 'S', fields: [
        ['name' => 'f', 'type' => 'string'],
    ]);

    $doc = $template->toJsonSchemaDocument();

    expect($doc)->toHaveKey('$schema')
        ->and($doc['title'])->toBe('Document')
        ->and($doc['type'])->toBe('object');
});

it('generates section schema by slug', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'part', name: 'Part', fields: [
        ['name' => 'key', 'type' => 'string'],
    ]);

    $schema = $template->sectionSchema('part');

    expect($schema['properties'])->toHaveKey('key');
});

it('returns null for missing section schema', function () {
    $template = EphemeralTemplate::make('t', 'T');

    expect($template->sectionSchema('missing'))->toBeNull();
});

// ---------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------

it('renders all enabled sections with data', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(
        slug: 'greeting',
        name: 'Greeting',
        content: 'Hello, {{ name }}!',
    );
    $template->addSection(
        slug: 'footer',
        name: 'Footer',
        content: 'Goodbye, {{ name }}!',
    );

    $result = $template->render([
        'greeting' => ['name' => 'Alice'],
        'footer' => ['name' => 'Bob'],
    ]);

    expect($result)->toBe("Hello, Alice!\n\nGoodbye, Bob!");
});

it('skips disabled sections when rendering', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'visible', name: 'Visible', content: 'Yes');
    $template->addSection(slug: 'hidden', name: 'Hidden', content: 'No', enabled: false);

    $result = $template->render();

    expect($result)->toBe('Yes');
});

it('falls back to flat data when section key missing', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(slug: 'body', name: 'Body', content: 'Val: {{ val }}');

    $result = $template->render(['val' => '42']);

    expect($result)->toBe('Val: 42');
});

it('previews using example data', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $template->addSection(
        slug: 'demo',
        name: 'Demo',
        content: 'Example: {{ value }}',
        examples: ['value' => 'test'],
    );

    expect($template->preview())->toBe('Example: test');
});

// ---------------------------------------------------------------
// Disabled sections after creation
// ---------------------------------------------------------------

it('supports enable and disable on sections after creation', function () {
    $template = EphemeralTemplate::make('t', 'T');
    $section = $template->addSection(slug: 'toggle', name: 'Toggle', content: 'Content');

    expect($template->render())->toBe('Content');

    $section->disable();

    expect($template->render())->toBe('');

    $section->enable();

    expect($template->render())->toBe('Content');
});

// ---------------------------------------------------------------
// No Database Queries
// ---------------------------------------------------------------

it('executes no database queries', function () {
    \Illuminate\Support\Facades\DB::enableQueryLog();

    $template = EphemeralTemplate::make('no-db', 'No DB', 'Test');
    $template->addSection(
        slug: 'body',
        name: 'Body',
        content: 'Hello {{ name }}',
        fields: [['name' => 'name', 'type' => 'string']],
        examples: ['name' => 'World'],
    );

    $template->toJsonSchema();
    $template->toJsonSchemaDocument();
    $template->sectionSchema('body');
    $template->render(['body' => ['name' => 'Test']]);
    $template->preview();

    $queries = \Illuminate\Support\Facades\DB::getQueryLog();

    expect($queries)->toBeEmpty();
});
