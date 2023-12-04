<?php

namespace Lupennat\ExpandableMany;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasManyThrough;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class ExpandableManyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Nova::serving(function (ServingNova $event) {
            Nova::script('expandable-many', __DIR__ . '/../dist/js/expandable-many.js');
        });

        FieldCollection::macro('withoutListableFieldsNotExpandable', function (NovaRequest $request, $resource) {
            return $this->reject(function ($field) {
                return $field instanceof ListableField && (!property_exists($field, 'expandableHook') || !$field->expandableHook);
            });
        });

        BelongsToMany::macro('expandable', function (string $showLabel = 'Show', string $hideLabel = 'Hide') {
            $this->component = 'morph-to-many-expandable-field';
            $this->expandableHook = true;
            $this->withMeta(['expandableShowLabel' => $showLabel, 'expandableHideLabel' => $hideLabel]);

            return $this;
        });

        HasMany::macro('expandable', function (string $showLabel = 'Show', string $hideLabel = 'Hide') {
            $this->component = 'morph-to-many-expandable-field';
            $this->expandableHook = true;
            $this->withMeta(['expandableShowLabel' => $showLabel, 'expandableHideLabel' => $hideLabel]);

            return $this;
        });

        HasManyThrough::macro('expandable', function (string $showLabel = 'Show', string $hideLabel = 'Hide') {
            $this->component = 'morph-to-many-expandable-field';
            $this->expandableHook = true;
            $this->withMeta(['expandableShowLabel' => $showLabel, 'expandableHideLabel' => $hideLabel]);

            return $this;
        });

        MorphToMany::macro('expandable', function (string $showLabel = 'Show', string $hideLabel = 'Hide') {
            $this->component = 'morph-to-many-expandable-field';
            $this->expandableHook = true;
            $this->withMeta(['expandableShowLabel' => $showLabel, 'expandableHideLabel' => $hideLabel]);

            return $this;
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
