<?php

namespace Lupennat\ExpandableMany;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Contracts\Resolvable;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
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
            Nova::script('expandable-many-3.3', __DIR__ . '/../dist/js/expandable-many.js');
        });

        FieldCollection::macro('withoutListableFieldsNotExpandable', function () {
            return $this->reject(function ($field) {
                return $field instanceof ListableField && !(property_exists($field, 'expandableHook') && $field->expandableHook);
            });
        });

        FieldCollection::macro('resolveForDisplayWithExpandable', function ($resource) {
            return $this->each(function ($field) use ($resource) {
                if (($field instanceof ListableField && !(property_exists($field, 'expandableHook') && $field->expandableHook)) || !$field instanceof Resolvable) {
                    return;
                }

                if ($field->pivot) {
                    $field->resolveForDisplay($resource->{$field->pivotAccessor} ?? new Pivot());
                } else {
                    $field->resolveForDisplay($resource);
                }
            });
        });

        Field::macro('expandable', function (callable $displayCallback = null) {
            // prevent loading relation when resolveForDisplay is called
            $this->value = new Collection([]);

            if (!array_key_exists('expandableUseStandardActions', $this->meta)) {
                $this->withMeta(['expandableUseStandardActions' => true]);
            }

            if (!array_key_exists('expandableShowLabel', $this->meta)) {
                $this->withMeta(['expandableShowLabel' => 'Show']);
            }

            if (!array_key_exists('expandableShowHtml', $this->meta)) {
                $this->withMeta(['expandableShowHtml' => '']);
            }

            if (!array_key_exists('expandableHideLabel', $this->meta)) {
                $this->withMeta(['expandableHideLabel' => 'Hide']);
            }
            
            if (!array_key_exists('expandableHideHtml', $this->meta)) {
                $this->withMeta(['expandableHideHtml' => '']);
            }

            if (!array_key_exists('expandableStoreStatus', $this->meta)) {
                $this->withMeta(['expandableStoreStatus' => '']);
            }

            if (!array_key_exists('expandableSkip', $this->meta)) {
                $this->withMeta(['expandableSkip' => false]);
            }

            if (!array_key_exists('expandableSkipLabel', $this->meta)) {
                $this->withMeta(['expandableSkipLabel' => '—']);
            }

            if (is_callable($displayCallback)) {
                $this->displayUsing(function ($value, $resource) use ($displayCallback) {
                    call_user_func($displayCallback, $this, $resource);

                    return null;
                });
            }

            if ($this instanceof BelongsToMany) {
                $this->expandableHook = true;
                $this->component = 'belongs-to-many-expandable-field';
                if (app(NovaRequest::class)->isResourceIndexRequest()) {
                    $this->required(false);
                }

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
                if (app(NovaRequest::class)->isResourceIndexRequest()) {
                    $this->required(false);
                }

                return $this;
            }

            if ($this instanceof HasMany) {
                $this->expandableHook = true;
                $this->component = 'has-many-expandable-field';

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
