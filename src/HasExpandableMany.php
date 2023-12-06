<?php

namespace Lupennat\ExpandableMany;

use Laravel\Nova\Http\Requests\NovaRequest;

trait HasExpandableMany
{
    /**
     * Resolve the index fields.
     *
     * @return \Laravel\Nova\Fields\FieldCollection<int, \Laravel\Nova\Fields\Field>
     */
    public function indexFields(NovaRequest $request)
    {
        return $this->availableFields($request)
            ->when($request->viaManyToMany(), $this->relatedFieldResolverCallback($request))
            ->filterForIndex($request, $this->resource)
            ->withoutListableFieldsNotExpandable()
            ->authorized($request)
            ->resolveForDisplayWithExpandable($this->resource);
    }
}
