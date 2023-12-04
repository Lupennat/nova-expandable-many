let mix = require('laravel-mix');

require('./nova.mix');

mix.setPublicPath('dist')
    .js('resources/js/expandable-many.js', 'js')
    .vue({ version: 3 })
    .nova('lupennat/expandable-many');
