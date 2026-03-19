<?php

namespace Yannelli\Schematic;

use InvalidArgumentException;
use JsonSerializable;

class FieldDefinition implements JsonSerializable
{
    protected const VALID_TYPES = [
        'string', 'integer', 'number', 'boolean',
        'array', 'object', 'enum',
    ];

    public function __construct(
        public readonly string $name,
        public readonly string $type = 'string',
        public readonly string $description = '',
        public readonly bool $required = true,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
        public readonly ?array $enum = null,
        public readonly ?array $items = null, // For array types
        public readonly ?array $properties = null, // For object types
    ) {
        if (! in_array($this->type, self::VALID_TYPES)) {
            throw new InvalidArgumentException(
                "Invalid field type '{$this->type}'. Valid types: " . implode(', ', self::VALID_TYPES)
            );
        }
    }

    /**
     * Create a FieldDefinition from an array (e.g., from JSON column).
     */
    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            type: $data['type'] ?? 'string',
            description: $data['description'] ?? '',
            required: $data['required'] ?? true,
            nullable: $data['nullable'] ?? false,
            default: $data['default'] ?? null,
            enum: $data['enum'] ?? null,
            items: $data['items'] ?? null,
            properties: $data['properties'] ?? null,
        );
    }

    /**
     * Convert to JSON Schema property definition.
     */
    public function toJsonSchemaProperty(): array
    {
        $schema = match ($this->type) {
            'string' => ['type' => 'string'],
            'integer' => ['type' => 'integer'],
            'number' => ['type' => 'number'],
            'boolean' => ['type' => 'boolean'],
            'enum' => [
                'type' => 'string',
                'enum' => $this->enum ?? [],
            ],
            'array' => [
                'type' => 'array',
                'items' => $this->items ?? ['type' => 'string'],
            ],
            'object' => [
                'type' => 'object',
                'properties' => $this->properties ?? (object) [],
            ],
            default => ['type' => 'string'],
        };

        if ($this->nullable) {
            $schema['type'] = is_array($schema['type'] ?? null)
                ? [...$schema['type'], 'null']
                : [$schema['type'], 'null'];
        }

        if ($this->description !== '') {
            $schema['description'] = $this->description;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'required' => $this->required,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'enum' => $this->enum,
            'items' => $this->items,
            'properties' => $this->properties,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
