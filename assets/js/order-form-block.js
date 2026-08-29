(function (blocks, element, components, blockEditor, i18n) {
    'use strict';

    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;

    blocks.registerBlockType('adoology/order-form', {
        apiVersion: 2,
        title: i18n.__('Adoology Order Form', 'adoology-connector'),
        icon: 'cart',
        category: 'widgets',
        attributes: {
            productId: { type: 'integer', default: 0 },
            title: { type: 'string', default: '' }
        },
        edit: function (props) {
            return el('div', { className: props.className },
                el(InspectorControls, {},
                    el(PanelBody, { title: i18n.__('Order form', 'adoology-connector'), initialOpen: true },
                        el(TextControl, {
                            label: i18n.__('Product ID', 'adoology-connector'),
                            type: 'number',
                            value: props.attributes.productId || '',
                            onChange: function (value) { props.setAttributes({ productId: parseInt(value || 0, 10) }); }
                        }),
                        el(TextControl, {
                            label: i18n.__('Title', 'adoology-connector'),
                            value: props.attributes.title,
                            onChange: function (value) { props.setAttributes({ title: value }); }
                        })
                    )
                ),
                el('div', { style: { padding: '24px', border: '1px solid #ccd0d4', borderRadius: '12px' } },
                    el('strong', {}, props.attributes.title || i18n.__('Adoology Order Form', 'adoology-connector')),
                    el('p', {}, props.attributes.productId ? i18n.sprintf(i18n.__('Product ID: %d', 'adoology-connector'), props.attributes.productId) : i18n.__('Choose a product ID in block settings.', 'adoology-connector'))
                )
            );
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
