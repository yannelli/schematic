<?php

namespace Yannelli\Schematic\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Yannelli\Schematic\Compiler;
use Yannelli\Schematic\Contracts\Renderable;
use Yannelli\Schematic\Contracts\SchemaGeneratable;

class Template extends Model implements Renderable, SchemaGeneratable
{
    protected $table = 'schematic_templates';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function sections(): HasMany
    {
        return $this->hasMany(
            config('schematic.models.section', Section::class),
            'template_id'
        )->orderBy('order');
    }

    public function enabledSections(): HasMany
    {
        return $this->sections()->where('is_enabled', true);
    }

    // ---------------------------------------------------------------
    // Section Helpers
    // ---------------------------------------------------------------

    /**
     * Get enabled sections as an iterable collection.
     */
    public function iterateSections(): Collection
    {
        return $this->enabledSections()->get();
    }

    /**
     * Iterate ALL sections (including disabled), with their enabled state.
     */
    public function iterateAllSections(): Collection
    {
        return $this->sections()->get();
    }

    /**
     * Find a section by slug.
     */
    public function section(string $slug): ?Section
    {
        return $this->sections()->where('slug', $slug)->first();
    }

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
    ): Section {
        $order ??= ($this->sections()->max('order') ?? -1) + 1;

        return $this->sections()->create([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'content' => $content,
            'fields' => $fields,
            'examples' => $examples,
            'order' => $order,
            'is_enabled' => $enabled,
        ]);
    }

    /**
     * Reorder sections by slug array.
     */
    public function reorderSections(array $slugs): static
    {
        foreach ($slugs as $index => $slug) {
            $this->sections()->where('slug', $slug)->update(['order' => $index]);
        }

        return $this;
    }

    // ---------------------------------------------------------------
    // Schema Generation
    // ---------------------------------------------------------------

    /**
     * Generate a combined JSON Schema for all enabled sections.
     */
    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->iterateSections() as $section) {
            $sectionSchema = $section->toJsonSchema();
            $properties[$section->slug] = $sectionSchema;

            // Each section is required by default
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

    /**
     * Generate a full JSON Schema document with $schema header.
     */
    public function toJsonSchemaDocument(): array
    {
        return [
            '$schema' => config('schematic.schema.draft'),
            'title' => $this->name,
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

    /**
     * Render all enabled sections with the given data.
     * Data should be keyed by section slug.
     */
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

    /**
     * Render using example data from each section.
     */
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
    // Static Finders
    // ---------------------------------------------------------------

    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }

    public static function findBySlugOrFail(string $slug): static
    {
        return static::where('slug', $slug)->firstOrFail();
    }
}
