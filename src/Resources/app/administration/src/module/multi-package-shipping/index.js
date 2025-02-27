import './page/multi-package-shipping-index';

Shopware.Module.register('multi-package-shipping', {
    type: 'plugin',
    name: 'MultiPackageShipping',
    title: 'multi-package-shipping.general.title',
    description: 'multi-package-shipping.general.description',
    color: '#ff3d58',
    icon: 'default-action-settings',

    routes: {
        index: {
            component: 'multi-package-shipping-index',
            path: 'index'
        }
    },

    settingsItem: {
        group: 'plugins',
        to: 'multi-package-shipping.index',
        icon: 'default-action-settings'
    }
});
