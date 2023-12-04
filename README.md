1. [Requirements](#Requirements)
2. [Installation](#Installation)
3. [Usage](#Usage)
4. [Changelog](CHANGELOG.md)

## Requirements

- `php: ^7.4 | ^8`
- `laravel/nova: ^4`

## Installation

```
composer require lupennat/nova-expandable-many
```

## Usage

Register Trait `HasExpandableMany` globally on Resources.

> the Trait include an override of `indexFields` method, it call method `withoutListableFieldsNotExpandable` instead of `withoutListableFields` on FieldCollection; if you already register a custom ovveride of `indexFields` you can do it manually without using provided trait.

ExpandableMany Package automatically enable a new method `expandable` for all Many Relationship Fields:

- BelongsToMany
- HasMany
- HasManyThrough
- MorphedByMany
- MorphMany
- MorphToMany

The relation table (without any actions, lenses and without the toolbar) will be displayed as a collapsable row on the index page.

```php

use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class User extends Resource
{

   use Lupennat\ExpandableMany\HasExpandableMany;

    public function fields(Request $request)
    {
        return [
            HasMany::make('User Post', 'posts', Post::class)->expandable('Show Label', 'Hide Label');
        ];
    }
}
```

> By Default expandable use `Show` and `Hide` as labels.
