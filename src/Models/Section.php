<?php

namespace Yannelli\Schematic\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Yannelli\Schematic\Compiler;
use Yannelli\Schematic\Contracts\Renderable;
use Yannelli\Schematic\Contracts\SchemaGeneratable;
use Yannelli\Schematic\FieldDefinition;

class Section extends Model implements Renderable, SchemaGeneratable
{
    protected $table = 'schematic_sections';

    protected $guarded = [];

    protected $casts = [
        'fields' => 'array',
        'examples' => 'array',
        'is_enabled' => 'boolean',
        'order' => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            config('schematic.models.template', Template::class),
            'template_id'
        );
    }

    // ---------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------

    /**
     * Parse the fields JSON into FieldDefinition objects.
     */
    protected function fieldDefinitions(): Attribute
    {
        return Attribute::get(function () {
            $raw = $this->fields ?? [];

            return collect($raw)->map(
                fn (array $field) => FieldDefinition::fromArray($field)
            );
        });
    }

    // ---------------------------------------------------------------
    // Schema Generation
    // ---------------------------------------------------------------

    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->field_definitions as $field) {
            $properties[$field->name] = $field->toJsonSchemaProperty();
            $required[] = $field->name;
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        if (config('schematic.schema.strict', true)) {
            $schema['additionalProperties'] = false;
        }

        return $schema;
    }

    // ---------------------------------------------------------------
    // Rendering
    // ---------------------------------------------------------------

    public function render(array $data = []): string
    {
        if (! $this->is_enabled) {
            return '';
        }

        return app(Compiler::class)->compile($this->content ?? '', $data);
    }

    public function preview(): string
    {
        return $this->render($this->examples ?? []);
    }

    // ---------------------------------------------------------------
    // Fluent Builders
    // ---------------------------------------------------------------

    public function enable(): static
    {
        $this->update(['is_enabled' => true]);

        return $this;
    }

    public function disable(): static
    {
        $this->update(['is_enabled' => false]);

        return $this;
    }

    public function addField(
        string $name,
        string $type = 'string',
        string $description = '',
        bool $required = true,
        bool $nullable = false,
        mixed $default = null,
        ?array $enum = null,
    ): static {
        $fields = $this->fields ?? [];

        $fields[] = (new FieldDefinition(
            name: $name,
            type: $type,
            description: $description,
            required: $required,
            nullable: $nullable,
            default: $default,
            enum: $enum,
        ))->jsonSerialize();

        $this->update(['fields' => $fields]);

        return $this;
    }

    public function removeField(string $name): static
    {
        $fields = collect($this->fields ?? [])->reject(
            fn (array $f) => $f['name'] === $name
        )->values()->all();

        $this->update(['fields' => $fields]);

        return $this;
    }

    public function setExamples(array $examples): static
    {
        $this->update(['examples' => $examples]);

        return $this;
    }
}
