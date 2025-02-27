import './component/sw-plugin-config';

Shopware.Module.register('sw-plugin-config', {
    type: 'plugin',
    name: 'sw-plugin-config',
    title: 'Multi Package Shipping',
    description: 'Konfiguriere das maximale Paketgewicht.',
    settingsItem: {
        group: 'plugins',
        to: 'sw.plugin.config',
        icon: 'default-action-settings'
    }
});
