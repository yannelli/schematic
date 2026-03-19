# Laravel Schematic

Database-driven templating language with JSON Schema generation for LLM structured outputs (OpenAI, Anthropic, etc.).

## Installation

```bash
composer require yannelli/schematic
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag=schematic-migrations
php artisan migrate
```

Optionally publish config:

```bash
php artisan vendor:publish --tag=schematic-config
```

## Quick Start

```php
use Yannelli\Schematic\Facades\Schematic;

// Create a template
$template = Schematic::create(
    slug: 'psychiatric-evaluation',
    name: 'Psychiatric Evaluation Note',
    description: 'Standard psychiatric evaluation template for initial patient encounters',
);

// Add sections with fields
$template->addSection(
    slug: 'chief-complaint',
    name: 'Chief Complaint',
    description: 'The primary reason the patient is seeking treatment',
    content: 'Chief Complaint: {{ complaint }}',
    fields: [
        [
            'name' => 'complaint',
            'type' => 'string',
            'description' => 'The patient\'s primary complaint in their own words',
            'required' => true,
            'nullable' => false,
        ],
    ],
    examples: [
        'complaint' => 'Patient reports increasing anxiety over the past 3 months',
    ],
);

$template->addSection(
    slug: 'mental-status-exam',
    name: 'Mental Status Exam',
    description: 'Structured mental status examination findings',
    content: <<<'TPL'
## Mental Status Exam
- Appearance: {{ appearance }}
- Mood: {{ mood }}
- Affect: {{ affect }}
- Thought Process: {{ thought_process }}
@if(suicidal_ideation)
- **Suicidal Ideation: {{ suicidal_ideation }}**
@endif
TPL,
    fields: [
        ['name' => 'appearance', 'type' => 'string', 'description' => 'General appearance and grooming'],
        ['name' => 'mood', 'type' => 'string', 'description' => 'Patient\'s self-reported mood'],
        ['name' => 'affect', 'type' => 'enum', 'description' => 'Observed affect', 'enum' => ['flat', 'blunted', 'constricted', 'full', 'labile']],
        ['name' => 'thought_process', 'type' => 'string', 'description' => 'Organization and flow of thoughts'],
        ['name' => 'suicidal_ideation', 'type' => 'string', 'description' => 'Details of suicidal ideation if present', 'required' => false, 'nullable' => true],
    ],
    examples: [
        'appearance' => 'Well-groomed, appropriately dressed, good hygiene',
        'mood' => 'Anxious',
        'affect' => 'constricted',
        'thought_process' => 'Linear and goal-directed',
        'suicidal_ideation' => null,
    ],
);

$template->addSection(
    slug: 'diagnoses',
    name: 'Diagnoses',
    content: <<<'TPL'
## Diagnoses
@foreach(diagnoses as dx)
- {{ dx.code }}: {{ dx.description }}
@endforeach
TPL,
    fields: [
        [
            'name' => 'diagnoses',
            'type' => 'array',
            'description' => 'List of ICD-10 diagnoses',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'ICD-10 code'],
                    'description' => ['type' => 'string', 'description' => 'Diagnosis description'],
                ],
                'required' => ['code', 'description'],
            ],
        ],
    ],
    examples: [
        'diagnoses' => [
            ['code' => 'F41.1', 'description' => 'Generalized anxiety disorder'],
            ['code' => 'F32.1', 'description' => 'Major depressive disorder, single episode, moderate'],
        ],
    ],
);
```

## JSON Schema Generation

Generate schemas for LLM structured output:

```php
// Full template schema
$schema = $template->toJsonSchema();

// Full document with $schema header
$doc = $template->toJsonSchemaDocument();

// Single section schema
$mseSchema = $template->sectionSchema('mental-status-exam');

// Via facade shortcuts
$schema = Schematic::schema('psychiatric-evaluation');
$sectionSchema = Schematic::sectionSchema('psychiatric-evaluation', 'chief-complaint');
```

Example output for the `mental-status-exam` section:

```json
{
  "type": "object",
  "properties": {
    "appearance": {
      "type": "string",
      "description": "General appearance and grooming"
    },
    "mood": {
      "type": "string",
      "description": "Patient's self-reported mood"
    },
    "affect": {
      "type": "string",
      "enum": ["flat", "blunted", "constricted", "full", "labile"],
      "description": "Observed affect"
    },
    "thought_process": {
      "type": "string",
      "description": "Organization and flow of thoughts"
    },
    "suicidal_ideation": {
      "type": ["string", "null"],
      "description": "Details of suicidal ideation if present"
    }
  },
  "required": ["appearance", "mood", "affect", "thought_process"],
  "description": "Structured mental status examination findings",
  "additionalProperties": false
}
```

### Using with Anthropic

```php
use Anthropic\Anthropic;

$schema = Schematic::schema('psychiatric-evaluation');

$response = Anthropic::messages()->create([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 4096,
    'messages' => [
        ['role' => 'user', 'content' => $transcriptText],
    ],
    'tools' => [
        [
            'name' => 'generate_note',
            'description' => 'Generate a structured psychiatric evaluation note',
            'input_schema' => $schema,
        ],
    ],
    'tool_choice' => ['type' => 'tool', 'name' => 'generate_note'],
]);
```

### Using with OpenAI

```php
use OpenAI\Laravel\Facades\OpenAI;

$schema = Schematic::schemaDocument('psychiatric-evaluation');

$response = OpenAI::chat()->create([
    'model' => 'gpt-4o',
    'messages' => [
        ['role' => 'user', 'content' => $transcriptText],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'psychiatric_evaluation',
            'strict' => true,
            'schema' => $schema,
        ],
    ],
]);
```

## Section Management

### Enable / Disable Sections

```php
$template->section('diagnoses')->disable();

// Only enabled sections are included in render/schema
$schema = $template->toJsonSchema(); // Won't include 'diagnoses'
$output = $template->render($data);  // Won't render 'diagnoses'

$template->section('diagnoses')->enable();
```

### Iterate Sections

```php
// Only enabled sections, ordered
foreach ($template->iterateSections() as $section) {
    echo "{$section->name}: " . ($section->is_enabled ? 'ON' : 'OFF') . "\n";
    echo json_encode($section->toJsonSchema(), JSON_PRETTY_PRINT) . "\n\n";
}

// All sections, including disabled
foreach ($template->iterateAllSections() as $section) {
    // ...
}
```

### Reorder Sections

```php
$template->reorderSections([
    'chief-complaint',
    'diagnoses',        // moved up
    'mental-status-exam', // moved down
]);
```

### Add / Remove Fields

```php
$section = $template->section('chief-complaint');

$section->addField(
    name: 'onset',
    type: 'string',
    description: 'When symptoms first appeared',
    required: false,
);

$section->removeField('onset');
```

## Custom Macros

Register macros to extend the template language:

```php
// In a service provider boot() method
use Yannelli\Schematic\Facades\Schematic;

Schematic::macro('component', fn (string $name) => view("components.{$name}")->render());
Schematic::macro('timestamp', fn () => now()->toDateTimeString());
Schematic::macro('badge', fn (string $label, string $color) => "<span class=\"badge badge-{$color}\">{$label}</span>");
```

Use in templates:

```
@component("vital-signs")
Generated at: @timestamp()
Status: @badge("Active", "green")
```

## Previewing with Examples

```php
// Set example data on a section
$template->section('chief-complaint')->setExamples([
    'complaint' => 'Patient reports difficulty sleeping for the past 2 weeks',
]);

// Preview a single section
echo $template->section('chief-complaint')->preview();

// Preview the entire template
echo $template->preview();

// Via facade
echo Schematic::preview('psychiatric-evaluation');
```

## Rendering

```php
$data = [
    'chief-complaint' => [
        'complaint' => 'Increasing anxiety and panic attacks',
    ],
    'mental-status-exam' => [
        'appearance' => 'Casually dressed, fidgeting',
        'mood' => 'Anxious',
        'affect' => 'constricted',
        'thought_process' => 'Circumstantial at times',
    ],
    'diagnoses' => [
        'diagnoses' => [
            ['code' => 'F41.0', 'description' => 'Panic disorder'],
        ],
    ],
];

echo $template->render($data);
// Or: Schematic::render('psychiatric-evaluation', $data);
```

## Template Syntax Reference

| Syntax | Description |
|---|---|
| `{{ variable }}` | Variable substitution |
| `{{ nested.key }}` | Dot-notation access |
| `@if(var) ... @endif` | Conditional block |
| `@if(var) ... @else ... @endif` | Conditional with else |
| `@foreach(items as item) ... @endforeach` | Loop over arrays |
| `@macroName("arg1", "arg2")` | Custom macro call |

## Extending Models

If you need to extend the base models, update the config:

```php
// config/schematic.php
'models' => [
    'template' => App\Models\CustomTemplate::class,
    'section' => App\Models\CustomSection::class,
],
```

Your custom models should extend the base classes:

```php
use Yannelli\Schematic\Models\Template;

class CustomTemplate extends Template
{
    // Add your custom logic
}
```

## License

MIT
