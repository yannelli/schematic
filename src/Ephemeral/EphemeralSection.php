<?php

namespace Yannelli\Schematic\Ephemeral;

use Yannelli\Schematic\Compiler;
use Yannelli\Schematic\Contracts\Renderable;
use Yannelli\Schematic\Contracts\SchemaGeneratable;
use Yannelli\Schematic\FieldDefinition;

class EphemeralSection implements Renderable, SchemaGeneratable
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description = null,
        public ?string $content = null,
        public array $fields = [],
        public array $examples = [],
        public int $order = 0,
        public bool $is_enabled = true,
    ) {}

    /**
     * Named constructor for fluent usage.
     */
    public static function make(
        string $slug,
        string $name,
        ?string $description = null,
        ?string $content = null,
        array $fields = [],
        array $examples = [],
        int $order = 0,
        bool $enabled = true,
    ): static {
        return new static($slug, $name, $description, $content, $fields, $examples, $order, $enabled);
    }

    // ---------------------------------------------------------------
    // Schema Generation
    // ---------------------------------------------------------------

    /**
     * Parse the fields array into FieldDefinition objects.
     *
     * @return \Illuminate\Support\Collection<int, FieldDefinition>
     */
    public function fieldDefinitions(): \Illuminate\Support\Collection
    {
        return collect($this->fields)->map(
            fn (array $field) => FieldDefinition::fromArray($field)
        );
    }

    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->fieldDefinitions() as $field) {
            $properties[$field->name] = $field->toJsonSchemaProperty();

            if ($field->required) {
                $required[] = $field->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

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
        return $this->render($this->examples);
    }

    // ---------------------------------------------------------------
    // Fluent Builders
    // ---------------------------------------------------------------

    public function enable(): static
    {
        $this->is_enabled = true;

        return $this;
    }

    public function disable(): static
    {
        $this->is_enabled = false;

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
        $this->fields[] = (new FieldDefinition(
            name: $name,
            type: $type,
            description: $description,
            required: $required,
            nullable: $nullable,
            default: $default,
            enum: $enum,
        ))->jsonSerialize();

        return $this;
    }

    public function removeField(string $name): static
    {
        $this->fields = collect($this->fields)->reject(
            fn (array $f) => $f['name'] === $name
        )->values()->all();

        return $this;
    }

    public function setExamples(array $examples): static
    {
        $this->examples = $examples;

        return $this;
    }
}
