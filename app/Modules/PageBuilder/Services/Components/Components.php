<?php

namespace App\Modules\PageBuilder\Services\Components;

use App\Modules\PageBuilder\Services\BuilderSanitizer;
use Illuminate\Validation\Rule;

/**
 * The built-in, allowlisted components of the visual site builder.
 *
 * Each component is registered by the PageBuilder service provider with a
 * type id, an editor category, metadata for its traits, validation rules and a
 * Blade view. Rich/HTML props are re-sanitized at render time by the registry
 * pipeline callers (publish + public render) through BuilderSanitizer.
 */
final class Components
{
    /**
     * Instantiate every built-in component.
     *
     * @return array<int, Component>
     */
    public static function all(BuilderSanitizer $sanitizer): array
    {
        return [
            new GenericComponent(
                type: 'section',
                name: __('pagebuilder.block_section'),
                group: 'layout',
                view: 'pagebuilder::components.section',
            ),
            new GenericComponent(
                type: 'container',
                name: __('pagebuilder.block_container'),
                group: 'layout',
                view: 'pagebuilder::components.container',
            ),
            new GenericComponent(
                type: 'row',
                name: __('pagebuilder.block_row'),
                group: 'layout',
                view: 'pagebuilder::components.row',
            ),
            new GenericComponent(
                type: 'column',
                name: __('pagebuilder.block_column'),
                group: 'layout',
                view: 'pagebuilder::components.column',
            ),
            new GenericComponent(
                type: 'spacer',
                name: __('pagebuilder.block_spacer'),
                group: 'layout',
                view: 'pagebuilder::components.spacer',
                props: [
                    'height' => ['label' => __('pagebuilder.prop_height'), 'type' => 'text'],
                ],
                rules: ['height' => ['nullable', 'string', 'max:20']],
                defaults: ['height' => '3rem'],
            ),
            new GenericComponent(
                type: 'divider',
                name: __('pagebuilder.block_divider'),
                group: 'layout',
                view: 'pagebuilder::components.divider',
            ),
            new GenericComponent(
                type: 'heading',
                name: __('pagebuilder.block_heading'),
                group: 'content',
                view: 'pagebuilder::components.heading',
                props: [
                    'content' => ['label' => __('pagebuilder.prop_content'), 'type' => 'textarea'],
                    'level' => ['label' => __('pagebuilder.prop_level'), 'type' => 'select', 'options' => ['1', '2', '3', '4', '5', '6']],
                    'align' => ['label' => __('pagebuilder.prop_align'), 'type' => 'select', 'options' => ['', 'left', 'center', 'right']],
                ],
                rules: [
                    'content' => ['nullable', 'string', 'max:2000'],
                    'level' => ['nullable', 'in:1,2,3,4,5,6'],
                    'align' => ['nullable', 'in:,left,center,right'],
                ],
                defaults: ['content' => '', 'level' => '2', 'align' => ''],
            ),
            new GenericComponent(
                type: 'text',
                name: __('pagebuilder.block_text'),
                group: 'content',
                view: 'pagebuilder::components.text',
                props: [
                    'content' => ['label' => __('pagebuilder.prop_content'), 'type' => 'textarea'],
                ],
                rules: ['content' => ['nullable', 'string', 'max:10000']],
                defaults: ['content' => ''],
            ),
            new GenericComponent(
                type: 'image',
                name: __('pagebuilder.block_image'),
                group: 'content',
                view: 'pagebuilder::components.image',
                props: [
                    'src' => ['label' => __('pagebuilder.prop_source'), 'type' => 'text'],
                    'alt' => ['label' => __('pagebuilder.prop_alt'), 'type' => 'text'],
                    'width' => ['label' => __('pagebuilder.prop_width'), 'type' => 'text'],
                ],
                rules: [
                    'src' => ['nullable', 'string', 'max:1000'],
                    'alt' => ['nullable', 'string', 'max:255'],
                    'width' => ['nullable', 'string', 'max:20'],
                ],
            ),
            new GenericComponent(
                type: 'button',
                name: __('pagebuilder.block_button'),
                group: 'content',
                view: 'pagebuilder::components.button',
                props: [
                    'text' => ['label' => __('pagebuilder.prop_text'), 'type' => 'text'],
                    'href' => ['label' => __('pagebuilder.prop_href'), 'type' => 'text'],
                    'variant' => ['label' => __('pagebuilder.prop_variant'), 'type' => 'select', 'options' => ['primary', 'secondary', 'light', 'dark', 'outline-primary', 'outline-secondary']],
                    'size' => ['label' => __('pagebuilder.prop_size'), 'type' => 'select', 'options' => ['', 'sm', 'lg']],
                ],
                rules: [
                    'text' => ['nullable', 'string', 'max:255'],
                    'href' => ['nullable', 'string', 'max:1000'],
                    'variant' => ['nullable', 'in:primary,secondary,light,dark,outline-primary,outline-secondary'],
                    'size' => ['nullable', 'in:,sm,lg'],
                ],
                defaults: ['text' => '', 'href' => '#', 'variant' => 'primary', 'size' => ''],
            ),
            new GenericComponent(
                type: 'card',
                name: __('pagebuilder.block_card'),
                group: 'components',
                view: 'pagebuilder::components.card',
            ),
            new GenericComponent(
                type: 'list',
                name: __('pagebuilder.block_list'),
                group: 'components',
                view: 'pagebuilder::components.list',
                props: [
                    'items' => ['label' => __('pagebuilder.prop_items'), 'type' => 'textarea'],
                    'variant' => ['label' => __('pagebuilder.prop_variant'), 'type' => 'select', 'options' => ['list-group', 'unstyled']],
                ],
                rules: [
                    'items' => ['nullable', 'string', 'max:5000'],
                    'variant' => ['nullable', 'in:list-group,unstyled'],
                ],
                defaults: ['items' => '', 'variant' => 'list-group'],
            ),
            new GenericComponent(
                type: 'alert',
                name: __('pagebuilder.block_alert'),
                group: 'components',
                view: 'pagebuilder::components.alert',
                props: [
                    'content' => ['label' => __('pagebuilder.prop_content'), 'type' => 'textarea'],
                    'variant' => ['label' => __('pagebuilder.prop_variant'), 'type' => 'select', 'options' => ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark']],
                ],
                rules: [
                    'content' => ['nullable', 'string', 'max:5000'],
                    'variant' => ['nullable', 'in:primary,secondary,success,danger,warning,info,light,dark'],
                ],
                defaults: ['content' => '', 'variant' => 'primary'],
            ),
            new GenericComponent(
                type: 'badge',
                name: __('pagebuilder.block_badge'),
                group: 'components',
                view: 'pagebuilder::components.badge',
                props: [
                    'content' => ['label' => __('pagebuilder.prop_content'), 'type' => 'text'],
                    'variant' => ['label' => __('pagebuilder.prop_variant'), 'type' => 'select', 'options' => ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark']],
                ],
                rules: [
                    'content' => ['nullable', 'string', 'max:500'],
                    'variant' => ['nullable', 'in:primary,secondary,success,danger,warning,info,light,dark'],
                ],
                defaults: ['content' => '', 'variant' => 'primary'],
            ),
            new GenericComponent(
                type: 'video',
                name: __('pagebuilder.block_video'),
                group: 'content',
                view: 'pagebuilder::components.video',
                props: [
                    'src' => ['label' => __('pagebuilder.prop_video_source'), 'type' => 'text'],
                    'poster' => ['label' => __('pagebuilder.prop_poster'), 'type' => 'text'],
                    'aspect' => ['label' => __('pagebuilder.prop_aspect'), 'type' => 'select', 'options' => ['16x9', '21x9', '4x3', '1x1']],
                ],
                rules: [
                    'src' => ['nullable', 'string', 'max:1000'],
                    'poster' => ['nullable', 'string', 'max:1000'],
                    'aspect' => ['nullable', 'in:16x9,21x9,4x3,1x1'],
                ],
                defaults: ['src' => '', 'poster' => '', 'aspect' => '16x9'],
            ),
            new GenericComponent(
                type: 'youtube',
                name: __('pagebuilder.block_youtube'),
                group: 'content',
                view: 'pagebuilder::components.youtube',
                props: [
                    'id' => ['label' => __('pagebuilder.prop_youtube_id'), 'type' => 'text'],
                    'title' => ['label' => __('pagebuilder.prop_title'), 'type' => 'text'],
                    'aspect' => ['label' => __('pagebuilder.prop_aspect'), 'type' => 'select', 'options' => ['16x9', '21x9', '4x3', '1x1']],
                ],
                rules: [
                    'id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/'],
                    'title' => ['nullable', 'string', 'max:255'],
                    'aspect' => ['nullable', 'in:16x9,21x9,4x3,1x1'],
                ],
                defaults: ['id' => '', 'title' => 'Vídeo', 'aspect' => '16x9'],
            ),
            new GenericComponent(
                type: 'blockquote',
                name: __('pagebuilder.block_blockquote'),
                group: 'content',
                view: 'pagebuilder::components.blockquote',
                props: [
                    'content' => ['label' => __('pagebuilder.prop_content'), 'type' => 'textarea'],
                    'cite' => ['label' => __('pagebuilder.prop_cite'), 'type' => 'text'],
                ],
                rules: [
                    'content' => ['nullable', 'string', 'max:5000'],
                    'cite' => ['nullable', 'string', 'max:255'],
                ],
                defaults: ['content' => '', 'cite' => ''],
            ),
            new GenericComponent(
                type: 'icon',
                name: __('pagebuilder.block_icon'),
                group: 'components',
                view: 'pagebuilder::components.icon',
                props: [
                    'icon' => ['label' => __('pagebuilder.prop_icon'), 'type' => 'select', 'options' => array_keys(config('pagebuilder.icons', []))],
                    'size' => ['label' => __('pagebuilder.prop_size'), 'type' => 'select', 'options' => ['sm', 'md', 'lg']],
                    'variant' => ['label' => __('pagebuilder.prop_variant'), 'type' => 'select', 'options' => ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'light']],
                ],
                rules: [
                    'icon' => ['nullable', Rule::in(array_keys(config('pagebuilder.icons', [])))],
                    'size' => ['nullable', 'in:sm,md,lg'],
                    'variant' => ['nullable', 'in:primary,secondary,success,danger,warning,info,dark,light'],
                ],
                defaults: ['icon' => 'star', 'size' => 'md', 'variant' => 'primary'],
            ),
            new GenericComponent(
                type: 'html',
                name: __('pagebuilder.block_html'),
                group: 'components',
                view: 'pagebuilder::components.html',
                props: [
                    'code' => ['label' => __('pagebuilder.prop_code'), 'type' => 'textarea'],
                ],
                rules: ['code' => ['nullable', 'string', 'max:50000']],
                defaults: ['code' => ''],
            ),
        ];
    }
}
