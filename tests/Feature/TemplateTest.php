<?php

use Yannelli\Schematic\Models\Section;
use Yannelli\Schematic\Models\Template;

// ---------------------------------------------------------------
// Creation & Lookup
// ---------------------------------------------------------------

it('creates a template', function () {
    $template = Template::create([
        'slug' => 'test-template',
        'name' => 'Test Template',
        'description' => 'A test template',
    ]);

    expect($template)->toBeInstanceOf(Template::class)
        ->and($template->slug)->toBe('test-template')
        ->and($template->name)->toBe('Test Template');
});

it('finds template by slug', function () {
    Template::create(['slug' => 'lookup', 'name' => 'Lookup']);

    $found = Template::findBySlug('lookup');

    expect($found)->not->toBeNull()
        ->and($found->slug)->toBe('lookup');
});

it('returns null for missing slug', function () {
    expect(Template::findBySlug('nonexistent'))->toBeNull();
});

it('throws on findBySlugOrFail with missing slug', function () {
    Template::findBySlugOrFail('nonexistent');
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('stores metadata as array', function () {
    $template = Template::create([
        'slug' => 'meta',
        'name' => 'Meta',
        'metadata' => ['version' => 2, 'author' => 'Ryan'],
    ]);

    $template->refresh();

    expect($template->metadata)->toBe(['version' => 2, 'author' => 'Ryan']);
});

// ---------------------------------------------------------------
// Sections
// ---------------------------------------------------------------

it('adds sections to a template', function () {
    $template = Template::create(['slug' => 'with-sections', 'name' => 'Sections']);

    $section = $template->addSection(
        slug: 'intro',
        name: 'Introduction',
        content: 'Hello {{ name }}',
        fields: [['name' => 'name', 'type' => 'string']],
    );

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->slug)->toBe('intro')
        ->and($template->sections)->toHaveCount(1);
});

it('auto-increments section order', function () {
    $template = Template::create(['slug' => 'ordered', 'name' => 'Ordered']);

    $s1 = $template->addSection(slug: 'first', name: 'First');
    $s2 = $template->addSection(slug: 'second', name: 'Second');
    $s3 = $template->addSection(slug: 'third', name: 'Third');

    expect($s1->order)->toBe(0)
        ->and($s2->order)->toBe(1)
        ->and($s3->order)->toBe(2);
});

it('reorders sections by slug array', function () {
    $template = Template::create(['slug' => 'reorder', 'name' => 'Reorder']);
    $template->addSection(slug: 'a', name: 'A');
    $template->addSection(slug: 'b', name: 'B');
    $template->addSection(slug: 'c', name: 'C');

    $template->reorderSections(['c', 'a', 'b']);

    $slugs = $template->sections()->get()->pluck('slug')->all();

    expect($slugs)->toBe(['c', 'a', 'b']);
});

it('finds a section by slug', function () {
    $template = Template::create(['slug' => 'find-section', 'name' => 'Find']);
    $template->addSection(slug: 'target', name: 'Target');

    $section = $template->section('target');

    expect($section)->not->toBeNull()
        ->and($section->slug)->toBe('target');
});

it('returns null for missing section', function () {
    $template = Template::create(['slug' => 'no-section', 'name' => 'Empty']);

    expect($template->section('nonexistent'))->toBeNull();
});

it('only returns enabled sections via iterateSections', function () {
    $template = Template::create(['slug' => 'enabled', 'name' => 'Enabled']);
    $template->addSection(slug: 'visible', name: 'Visible', enabled: true);
    $template->addSection(slug: 'hidden', name: 'Hidden', enabled: false);

    $sections = $template->iterateSections();

    expect($sections)->toHaveCount(1)
        ->and($sections->first()->slug)->toBe('visible');
});

it('returns all sections via iterateAllSections', function () {
    $template = Template::create(['slug' => 'all', 'name' => 'All']);
    $template->addSection(slug: 'a', name: 'A', enabled: true);
    $template->addSection(slug: 'b', name: 'B', enabled: false);

    expect($template->iterateAllSections())->toHaveCount(2);
});

// ---------------------------------------------------------------
// Schema Generation
// ---------------------------------------------------------------

it('generates JSON schema from sections', function () {
    $template = Template::create([
        'slug' => 'schema-test',
        'name' => 'Schema Test',
        'description' => 'For testing',
    ]);

    $template->addSection(
        slug: 'demographics',
        name: 'Demographics',
        fields: [
            ['name' => 'name', 'type' => 'string', 'description' => 'Patient name'],
            ['name' => 'age', 'type' => 'integer', 'required' => true],
        ],
    );

    $schema = $template->toJsonSchema();

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('demographics')
        ->and($schema['required'])->toContain('demographics')
        ->and($schema['description'])->toBe('For testing')
        ->and($schema['additionalProperties'])->toBeFalse();
});

it('generates full JSON Schema document', function () {
    $template = Template::create(['slug' => 'doc', 'name' => 'Document']);
    $template->addSection(slug: 's1', name: 'S1', fields: [
        ['name' => 'field1', 'type' => 'string'],
    ]);

    $doc = $template->toJsonSchemaDocument();

    expect($doc['$schema'])->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($doc['title'])->toBe('document')
        ->and($doc['type'])->toBe('object');
});

it('generates section schema by slug', function () {
    $template = Template::create(['slug' => 'sec-schema', 'name' => 'Sec']);
    $template->addSection(slug: 'details', name: 'Details', fields: [
        ['name' => 'note', 'type' => 'string'],
    ]);

    $schema = $template->sectionSchema('details');

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKey('note');
});

it('returns null for missing section schema', function () {
    $template = Template::create(['slug' => 'no-sec', 'name' => 'No Sec']);

    expect($template->sectionSchema('missing'))->toBeNull();
});

it('excludes disabled sections from schema', function () {
    $template = Template::create(['slug' => 'disabled-schema', 'name' => 'DS']);
    $template->addSection(slug: 'included', name: 'I', fields: [
        ['name' => 'f1', 'type' => 'string'],
    ]);
    $template->addSection(slug: 'excluded', name: 'E', fields: [
        ['name' => 'f2', 'type' => 'string'],
    ], enabled: false);

    $schema = $template->toJsonSchema();

    expect($schema['properties'])->toHaveKey('included')
        ->and($schema['properties'])->not->toHaveKey('excluded');
});

// ---------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------

it('renders template with data', function () {
    $template = Template::create(['slug' => 'render', 'name' => 'Render']);
    $template->addSection(
        slug: 'greeting',
        name: 'Greeting',
        content: 'Hello {{ name }}, you are {{ age }} years old.',
    );

    $result = $template->render([
        'greeting' => ['name' => 'Ryan', 'age' => 30],
    ]);

    expect($result)->toBe('Hello Ryan, you are 30 years old.');
});

it('renders multiple sections', function () {
    $template = Template::create(['slug' => 'multi', 'name' => 'Multi']);
    $template->addSection(slug: 'header', name: 'Header', content: '# {{ title }}');
    $template->addSection(slug: 'body', name: 'Body', content: '{{ content }}');

    $result = $template->render([
        'header' => ['title' => 'My Doc'],
        'body' => ['content' => 'Hello world'],
    ]);

    expect($result)->toContain('# My Doc')
        ->toContain('Hello world');
});

it('skips disabled sections during render', function () {
    $template = Template::create(['slug' => 'skip', 'name' => 'Skip']);
    $template->addSection(slug: 'visible', name: 'V', content: 'Visible');
    $template->addSection(slug: 'hidden', name: 'H', content: 'Hidden', enabled: false);

    $result = $template->render();

    expect($result)->toContain('Visible')
        ->not->toContain('Hidden');
});

it('previews using section example data', function () {
    $template = Template::create(['slug' => 'preview', 'name' => 'Preview']);
    $template->addSection(
        slug: 'greeting',
        name: 'Greeting',
        content: 'Hello {{ name }}!',
        examples: ['name' => 'Example User'],
    );

    $result = $template->preview();

    expect($result)->toBe('Hello Example User!');
});

// ---------------------------------------------------------------
// Cascade Delete
// ---------------------------------------------------------------

it('deletes sections when template is deleted', function () {
    // Enable foreign key constraints for SQLite
    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');

    $template = Template::create(['slug' => 'cascade', 'name' => 'Cascade']);
    $template->addSection(slug: 's1', name: 'S1');
    $template->addSection(slug: 's2', name: 'S2');

    $templateId = $template->id;
    $template->delete();

    expect(Section::where('template_id', $templateId)->count())->toBe(0);
});
