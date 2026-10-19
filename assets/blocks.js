/* LoopDues blocks: editor side only. The front end is rendered by PHP (includes/content.php). No build step. */
(function (wp, config) {
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var be = wp.blockEditor;
    var c = wp.components;
    var tiers = (config && config.tiers) || [];

    function notice(text) {
        return el('div', { style: { padding: '16px', border: '1px dashed #999', borderRadius: '6px', background: '#fafafa' } }, text);
    }

    function tierPicker(props, help) {
        if (!tiers.length) {
            return el('p', null, __('Connect the site in Settings → LoopDues to choose tiers.', 'loopdues'));
        }
        var chosen = props.attributes.tiers || [];
        return el('div', null,
            el('p', null, help),
            tiers.map(function (t) {
                return el(c.CheckboxControl, {
                    key: t.id,
                    label: t.name,
                    checked: chosen.indexOf(t.id) !== -1,
                    onChange: function (on) {
                        var next = chosen.filter(function (id) { return id !== t.id; });
                        if (on) next.push(t.id);
                        props.setAttributes({ tiers: next });
                    },
                });
            })
        );
    }

    function tierNames(ids) {
        return tiers.filter(function (t) { return ids.indexOf(t.id) !== -1; }).map(function (t) { return t.name; }).join(', ');
    }

    wp.blocks.registerBlockType('loopdues/join', {
        title: __('LoopDues join form', 'loopdues'),
        description: __('Your LoopDues membership signup form.', 'loopdues'),
        icon: 'id-alt',
        category: 'widgets',
        attributes: {
            tiers: { type: 'array', default: [] },
            fullWidth: { type: 'boolean', default: false },
            hideLogo: { type: 'boolean', default: false },
            inheritFont: { type: 'boolean', default: false },
            frameless: { type: 'boolean', default: false },
            hideSignin: { type: 'boolean', default: false },
        },
        edit: function (props) {
            var a = props.attributes;
            function toggle(key, label) {
                return el(c.ToggleControl, { label: label, checked: !!a[key], onChange: function (v) { var o = {}; o[key] = v; props.setAttributes(o); } });
            }
            return el('div', be.useBlockProps(),
                el(be.InspectorControls, null,
                    el(c.PanelBody, { title: __('Form options', 'loopdues') },
                        toggle('fullWidth', __('Full width', 'loopdues')),
                        toggle('hideLogo', __('Hide logo', 'loopdues')),
                        toggle('inheritFont', __('Use the website font', 'loopdues')),
                        toggle('frameless', __('No frame', 'loopdues')),
                        toggle('hideSignin', __('Hide "already a member? sign in" link', 'loopdues'))
                    ),
                    el(c.PanelBody, { title: __('Tiers', 'loopdues'), initialOpen: false },
                        tierPicker(props, __('Show only these tiers (none ticked = all public tiers).', 'loopdues'))
                    )
                ),
                notice(config.connected
                    ? __('LoopDues join form', 'loopdues') + (a.tiers.length ? ' — ' + tierNames(a.tiers) : '') + '. ' + __('It appears on the published page.', 'loopdues')
                    : __('LoopDues join form: paste the connection key in Settings → LoopDues first.', 'loopdues'))
            );
        },
        save: function () { return null; },
    });

    wp.blocks.registerBlockType('loopdues/login', {
        title: __('LoopDues member login', 'loopdues'),
        description: __('"Member login" for visitors; "My account" and "Log out" for signed-in members.', 'loopdues'),
        icon: 'admin-users',
        category: 'widgets',
        attributes: { label: { type: 'string', default: '' } },
        edit: function (props) {
            return el('div', be.useBlockProps(),
                el(be.InspectorControls, null,
                    el(c.PanelBody, { title: __('Button', 'loopdues') },
                        el(c.TextControl, { label: __('Label', 'loopdues'), placeholder: __('Member login', 'loopdues'), value: props.attributes.label, onChange: function (v) { props.setAttributes({ label: v }); } })
                    )
                ),
                el('span', { className: 'wp-element-button' }, props.attributes.label || __('Member login', 'loopdues'))
            );
        },
        save: function () { return null; },
    });

    wp.blocks.registerBlockType('loopdues/members-only', {
        title: __('LoopDues members only', 'loopdues'),
        description: __('Blocks inside are shown only to current members.', 'loopdues'),
        icon: 'lock',
        category: 'design',
        attributes: { tiers: { type: 'array', default: [] } },
        edit: function (props) {
            var ids = props.attributes.tiers || [];
            return el('div', be.useBlockProps({ style: { outline: '1px dashed #999', padding: '12px' } }),
                el(be.InspectorControls, null,
                    el(c.PanelBody, { title: __('Who can see this', 'loopdues') },
                        tierPicker(props, __('Only members in these tiers (none ticked = any current member).', 'loopdues'))
                    )
                ),
                el('p', { style: { fontSize: '12px', margin: '0 0 8px', opacity: 0.7 } },
                    '🔒 ' + (ids.length ? __('Members in: ', 'loopdues') + tierNames(ids) : __('Members only', 'loopdues'))),
                el(be.InnerBlocks)
            );
        },
        save: function () { return el(be.InnerBlocks.Content); },
    });
})(window.wp, window.LoopDuesBlocks || {});
