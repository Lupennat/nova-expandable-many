<?php

namespace Lupennat\ExpandableMany;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasManyThrough;
use Laravel\Nova\Fields\MorphToMany;
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

        FieldCollection::macro('withoutListableFieldsNotExpandable', function () {
            return $this->reject(function ($field) {
                return $field instanceof ListableField && (!property_exists($field, 'expandableHook') || !$field->expandableHook);
            });
        });

        Field::macro('expandable', function () {
            if (!array_key_exists('expandableShowLabel', $this->meta)) {
                $this->withMeta(['expandableShowLabel' => 'Show']);
            }

            if (!array_key_exists('expandableHideLabel', $this->meta)) {
                $this->withMeta(['expandableHideLabel' => 'Hide']);
            }

            if (!array_key_exists('expandableStoreStatus', $this->meta)) {
                $this->withMeta(['expandableStoreStatus' => '']);
            }

            if ($this instanceof BelongsToMany) {
                $this->expandableHook = true;

                $this->component = 'belongs-to-many-expandable-field';

                return $this;
            }

            if ($this instanceof HasMany) {
                $this->expandableHook = true;
                $this->component = 'has-many-expandable-field';

                return $this;
            }

            if ($this instanceof HasManyThrough) {
                $this->expandableHook = true;
                $this->component = 'has-many-through-expandable-field';

                return $this;
            }

            if ($this instanceof MorphToMany) {
                $this->expandableHook = true;
                $this->component = 'morph-to-many-expandable-field';

                return $this;
            }

            throw new \Exception('Field ' . get_class($this) . ' does not support expandable()');
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
