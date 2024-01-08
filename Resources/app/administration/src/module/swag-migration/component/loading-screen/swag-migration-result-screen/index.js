import template from './swag-migration-result-screen.html.twig';
import './swag-migration-result-screen.scss';

const { Component } = Shopware;

/**
 * @package services-settings
 */
Component.register('swag-migration-result-screen', {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    props: {
        runId: {
            type: String,
            required: true,
        },
    },
});
