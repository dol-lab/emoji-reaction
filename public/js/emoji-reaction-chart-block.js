/**
 * Gutenberg Block for Emoji Reaction Chart
 *
 * @since 0.4.0
 */

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl } = wp.components;
    const { __ } = wp.i18n;

    registerBlockType('emoji-reaction/chart', {
        title: __('Emoji Reaction Chart', 'emoji-reaction'),
        description: __('Display a chart of emoji reactions for the current post.', 'emoji-reaction'),
        icon: 'chart-pie',
        category: 'widgets',
        keywords: [
            __('emoji', 'emoji-reaction'),
            __('reaction', 'emoji-reaction'),
            __('chart', 'emoji-reaction'),
            __('analytics', 'emoji-reaction')
        ],
        supports: {
            align: true,
            alignWide: true
        },
        attributes: {
            type: {
                type: 'string',
                default: 'donut'
            },
            post_id: {
                type: 'number',
                default: 0
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { type } = attributes;

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, {
                        title: __('Chart Settings', 'emoji-reaction'),
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: __('Chart Type', 'emoji-reaction'),
                            value: type,
                            options: [
                                { label: __('Bar Chart', 'emoji-reaction'), value: 'bar' },
                                { label: __('Donut Chart', 'emoji-reaction'), value: 'donut' }
                            ],
                            onChange: function(value) {
                                setAttributes({ type: value });
                            }
                        })
                    )
                ),

                el('div', {
                    key: 'preview',
                    className: 'emoji-reaction-chart-block-preview',
                    style: {
                        border: '2px dashed #ccc',
                        padding: '20px',
                        textAlign: 'center',
                        backgroundColor: '#f9f9f9',
                        borderRadius: '4px'
                    }
                },
                    el('div', {
                        style: {
                            fontSize: '48px',
                            marginBottom: '10px'
                        }
                    }, '📊'),
                    el('h4', {
                        style: {
                            margin: '0 0 10px',
                            color: '#666'
                        }
                    }, __('Emoji Reaction Chart', 'emoji-reaction')),
                    el('p', {
                        style: {
                            margin: '0',
                            color: '#999',
                            fontSize: '14px'
                        }
                    },
                        __('Chart Type: ', 'emoji-reaction') +
                        (type === 'bar' ? __('Bar Chart', 'emoji-reaction') : __('Donut Chart', 'emoji-reaction'))
                    ),
                    el('p', {
                        style: {
                            margin: '5px 0 0',
                            color: '#999',
                            fontSize: '12px',
                            fontStyle: 'italic'
                        }
                    }, __('Chart will display on the frontend based on current post reactions.', 'emoji-reaction'))
                )
            ];
        },

        save: function() {
            // Return null since this is a dynamic block rendered server-side
            return null;
        }
    });
})();
