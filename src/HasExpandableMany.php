<?php

namespace Lupennat\ExpandableMany;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\FieldCollection;

trait HasExpandableMany
{
    /**
     * Resolve the index fields.
     *
     * @return \Laravel\Nova\Fields\FieldCollection<int, \Laravel\Nova\Fields\Field>
     */
    public function indexFields(NovaRequest $request): FieldCollection
    {
        return $this->availableFields($request)
            ->when($request->viaManyToMany(), $this->relatedFieldResolverCallback($request))
            ->filterForIndex($request, $this->resource)
            ->withoutListableFieldsNotExpandable()
            ->authorized($request)
            ->resolveForDisplayWithExpandable($this->resource);
    }
}
