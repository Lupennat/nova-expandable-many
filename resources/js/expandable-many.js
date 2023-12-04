import BelongsToManyExpandableIndexField from './fields/BelongsToManyExpandableIndexField';
import BelongsToManyDetailField from '@/fields/Detail/BelongsToManyField';
import HasManyExpandableIndexField from './fields/HasManyExpandableIndexField';
import HasManyDetailField from '@/fields/Detail/HasManyField';
import HasManyThroughExpandableIndexField from './fields/HasManyThroughExpandableIndexField';
import HasManyThroughDetailField from '@/fields/Detail/HasManyThroughField';
import MorphToManyExpandableIndexField from './fields/MorphToManyExpandableIndexField';
import MorphToManyDetailField from '@/fields/Detail/MorphToManyField';

Nova.booting((app, store) => {
    app.component('index-belongs-to-many-expandable-field', BelongsToManyExpandableIndexField);
    app.component('detail-belongs-to-many-expandable-field', BelongsToManyDetailField);
    app.component('index-has-many-expandable-field', HasManyExpandableIndexField);
    app.component('detail-has-many-expandable-field', HasManyDetailField);
    app.component('index-has-many-through-expandable-field', HasManyThroughExpandableIndexField);
    app.component('detail-has-many-through-expandable-field', HasManyThroughDetailField);
    app.component('index-morph-to-many-expandable-field', MorphToManyExpandableIndexField);
    app.component('detail-morph-to-many-expandable-field', MorphToManyDetailField);
});
