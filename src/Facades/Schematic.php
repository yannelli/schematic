<?php

namespace Yannelli\Schematic\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Yannelli\Schematic\Models\Template create(string $slug, string $name, ?string $description = null, array $metadata = [])
 * @method static \Yannelli\Schematic\Models\Template|null find(string $slug)
 * @method static \Yannelli\Schematic\Models\Template findOrFail(string $slug)
 * @method static \Yannelli\Schematic\Ephemeral\EphemeralTemplate ephemeral(string $slug, string $name, ?string $description = null, array $metadata = [])
 * @method static static macro(string $name, \Closure $handler)
 * @method static bool hasMacro(string $name)
 * @method static array schema(string $slug)
 * @method static array|null sectionSchema(string $templateSlug, string $sectionSlug)
 * @method static array schemaDocument(string $slug)
 * @method static string render(string $slug, array $data = [])
 * @method static string preview(string $slug)
 * @method static \Yannelli\Schematic\Compiler compiler()
 *
 * @see \Yannelli\Schematic\Schematic
 */
class Schematic extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Yannelli\Schematic\Schematic::class;
    }
}
