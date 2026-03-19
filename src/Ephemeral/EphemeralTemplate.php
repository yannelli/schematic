<?php

namespace Yannelli\Schematic\Ephemeral;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yannelli\Schematic\Compiler;
use Yannelli\Schematic\Contracts\Renderable;
use Yannelli\Schematic\Contracts\SchemaGeneratable;

class EphemeralTemplate implements Renderable, SchemaGeneratable
{
    /**
     * @var EphemeralSection[]
     */
    protected array $sections = [];

    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description = null,
        public array $metadata = [],
    ) {}

    /**
     * Named constructor for fluent usage.
     */
    public static function make(
        string $slug,
        string $name,
        ?string $description = null,
        array $metadata = [],
    ): static {
        return new static($slug, $name, $description, $metadata);
    }

    // ---------------------------------------------------------------
    // Section Helpers
    // ---------------------------------------------------------------

    /**
     * Create a new section on this template.
     */
    public function addSection(
        string $slug,
        string $name,
        ?string $description = null,
        ?string $content = null,
        array $fields = [],
        array $examples = [],
        ?int $order = null,
        bool $enabled = true,
    ): EphemeralSection {
        $order ??= $this->nextOrder();

        $section = new EphemeralSection(
            slug: $slug,
            name: $name,
            description: $description,
            content: $content,
            fields: $fields,
            examples: $examples,
            order: $order,
            is_enabled: $enabled,
        );

        $this->sections[] = $section;

        return $section;
    }

    /**
     * Find a section by slug.
     */
    public function section(string $slug): ?EphemeralSection
    {
        foreach ($this->sections as $section) {
            if ($section->slug === $slug) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Get enabled sections sorted by order.
     */
    public function iterateSections(): Collection
    {
        return collect($this->sections)
            ->filter(fn (EphemeralSection $s) => $s->is_enabled)
            ->sortBy('order')
            ->values();
    }

    /**
     * Get all sections (including disabled) sorted by order.
     */
    public function iterateAllSections(): Collection
    {
        return collect($this->sections)
            ->sortBy('order')
            ->values();
    }

    /**
     * Reorder sections by slug array.
     */
    public function reorderSections(array $slugs): static
    {
        foreach ($slugs as $index => $slug) {
            $section = $this->section($slug);

            if ($section) {
                $section->order = $index;
            }
        }

        return $this;
    }

    // ---------------------------------------------------------------
    // Schema Generation
    // ---------------------------------------------------------------

    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->iterateSections() as $section) {
            $sectionSchema = $section->toJsonSchema();
            $properties[$section->slug] = $sectionSchema;
            $required[] = $section->slug;
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

    public function toJsonSchemaDocument(): array
    {
        return [
            '$schema' => config('schematic.schema.draft'),
            'title' => Str::snake($this->name),
            ...$this->toJsonSchema(),
        ];
    }

    /**
     * Generate JSON Schema for a single section by slug.
     */
    public function sectionSchema(string $slug): ?array
    {
        return $this->section($slug)?->toJsonSchema();
    }

    // ---------------------------------------------------------------
    // Rendering
    // ---------------------------------------------------------------

    public function render(array $data = []): string
    {
        $compiler = app(Compiler::class);
        $output = [];

        foreach ($this->iterateSections() as $section) {
            $sectionData = $data[$section->slug] ?? $data;
            $rendered = $section->render($sectionData);

            if ($rendered !== '') {
                $output[] = $rendered;
            }
        }

        return implode("\n\n", $output);
    }

    public function preview(): string
    {
        $output = [];

        foreach ($this->iterateSections() as $section) {
            $rendered = $section->preview();

            if ($rendered !== '') {
                $output[] = $rendered;
            }
        }

        return implode("\n\n", $output);
    }

    // ---------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------

    protected function nextOrder(): int
    {
        if (empty($this->sections)) {
            return 0;
        }

        return max(array_map(fn (EphemeralSection $s) => $s->order, $this->sections)) + 1;
    }
}
