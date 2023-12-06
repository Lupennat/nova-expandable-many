<?php

namespace Lupennat\ExpandableMany;

use Laravel\Nova\Http\Requests\NovaRequest;

trait HasExpandableManyLens
{
    /**
     * Resolve the given fields to their values.
     *
     * @return \Laravel\Nova\Fields\FieldCollection<int, \Laravel\Nova\Fields\Field>
     */
    public function resolveFields(NovaRequest $request)
    {
        return $this->availableFields($request)
            ->filterForIndex($request, $this->resource)
            ->withoutListableFieldsNotExpandable()
            ->authorized($request)
            ->resolveForDisplayWithExpandable($this->resource);
    }
}
