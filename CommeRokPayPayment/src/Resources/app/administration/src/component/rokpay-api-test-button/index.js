const { Component, Mixin } = Shopware;
import template from './rokpay-api-test-button.html.twig';

Component.register('rokpay-api-test-button', {
    template,

    props: ['label'],
    inject: ['rokPayApiTest'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            let $parent = this.$parent;

            while ($parent.actualConfigData === undefined) {
                $parent = $parent.$parent;
            }

            return $parent.actualConfigData.null;
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        check() {
            this.isLoading = true;
            this.rokPayApiTest.check(this.pluginConfig).then((res) => {
                if (res.success) {
                    console.log(res.success);
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title: this.$tc('rokpay-api-test-button.title'),
                        message: this.$tc('rokpay-api-test-button.success')
                    });
                } else {
                    console.log(res.success);

                    this.createNotificationError({
                        title: this.$tc('rokpay-api-test-button.title'),
                        message: this.$tc('rokpay-api-test-button.error')
                    });
                }

                this.isLoading = false;
            });
        }
    }
})
