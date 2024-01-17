![](https://github.com/Lupennat/nova-expandable-many/blob/main/demo.gif)

1. [Requirements](#Requirements)
2. [Installation](#Installation)
3. [Usage](#Usage)
4. [Changelog](CHANGELOG.md)
5. [Credits](#Credits)

## Requirements

-   `php: ^7.4 | ^8`
-   `laravel/nova: ^4`

## Installation

```
composer require lupennat/nova-expandable-many
```

| NOVA    | PACKAGE |
| ------- | ------- |
| <4.29.5 | 1.x     |
| >4.29.6 | 2.x     |

## Usage

Register Trait `HasExpandableMany` globally on Resources.

> the Trait include an override of `indexFields` method, it call method `withoutListableFieldsNotExpandable` instead of `withoutListableFields` on FieldCollection; if you already register a custom ovveride of `indexFields` you can do it manually without using provided trait.

ExpandableMany Package automatically enable a new method `expandable` for all Many Relationship Fields:

-   BelongsToMany
-   HasMany
-   HasManyThrough
-   MorphedByMany
-   MorphMany
-   MorphToMany

The relation table (without any custom actions and lenses) will be displayed as a collapsable row on the index page.

```php

use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class User extends Resource
{

   use Lupennat\ExpandableMany\HasExpandableMany;

    public function fields(Request $request)
    {
        return [
            HasMany::make('User Post', 'posts', Post::class)->expandable();
        ];
    }
}
```

By Default expandable use `Show` and `Hide` as labels, you can change labels using meta

```php
    HasMany::make('User Post', 'posts', Post::class)->expandable()
        ->withMeta([
            'expandableShowLabel' => 'Custom Show',
            'expandableHideLabel' => 'Custom Hide',
        ])
```

By Default expandable do not store on browser history any status, you can change it using meta

```php
    HasMany::make('User Post', 'posts', Post::class)->expandable()
        ->withMeta([
            // 'expandableStoreStatus' => 'full', // will store status also for relationships
            'expandableStoreStatus' => 'accordion', // will store status only for accordion
            'expandableStoreStatus' => '', // will not store any status
        ])
```

Expandable can be skipped and an empty field is shown (by Default is false)

```php
    HasMany::make('User Post', 'posts', Post::class)->expandable()
        ->withMeta([
            'expandableSkip' => true,
            'expandableSkipLabel' => '', // default is '—'
        ])
```

Expandable can disable standard actions (By Default is enabled)

```php
    HasMany::make('User Post', 'posts', Post::class)->expandable()
        ->withMeta([
            'expandableUseStandardActions' => false, // disable create/edit/view/delete/restore
        ])
```

### Display Callback

Expandable Many will resolve a display callback foreach resource, you can use it to manipulate meta attributes dinamically.

```php
    HasMany::make('User Post', 'posts', Post::class)
        ->expandable(function(HasMany $field, $resource) {
            $resource->loadCount('posts');
            $field->withMeta([
                'expandableShowLabel' => 'Show ' . $resource->posts_count,
                'expandableSkip' => $resource->posts_count === 0
            ]);
        })
```

> By Default Expandable do not resolve relations, accessing a relationAttribute through the `$resource` will execute a query against database to load all related models.

### Lens

If you want to use Expandable Many inside a Lens, you need to register also the trait `HasExpandableManyLens` inside your Lens.

```php

use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;

class UserLens extends Lens
{
   use Lupennat\ExpandableMany\HasExpandableManyLens;

    public function fields(Request $request)
    {
        return [
            HasMany::make('User Post', 'posts', Post::class)->expandable();
        ];
    }
}
```

## Credits

This package is based on the original idea from [Nova Expandable Row](https://github.com/SPRIGS/nova-expandable-row)
