let originalUpdateQueryStringComponent = null;

function walk(vnode, cb) {
    if (originalUpdateQueryStringComponent !== null) {
        return originalUpdateQueryStringComponent;
    }

    if (!vnode) {
        return;
    }

    if (vnode.component) {
        const proxy = vnode.component.proxy;
        if (proxy) {
            cb(vnode.component.proxy);
        }
        walk(vnode.component.subTree, cb);
    } else if (vnode.shapeFlag & 16) {
        const vnodes = vnode.children;
        for (let i = 0; i < vnodes.length; i++) {
            walk(vnodes[i], cb);
        }
    }
}

function setOriginalUpdateQueryStringComponent(root) {
    walk(root.$.subTree, component => {
        if (typeof component === 'object' && typeof component.updateQueryString === 'function') {
            originalUpdateQueryStringComponent = component;
        }
    });
}

export default {
    methods: {
        /**
         * Update the given query string values.
         */
        updateQueryString(value) {
            if (typeof originalUpdateQueryStringComponent !== 'function') {
                setOriginalUpdateQueryStringComponent(this.$root);
            }

            originalUpdateQueryStringComponent && originalUpdateQueryStringComponent.updateQueryString(value);
        },
    },
};
