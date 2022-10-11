jQuery(document).ready(function () {
    wpSmsImportSubscriber.init();
});

let wpSmsImportSubscriber = {

    /**
     * initialize functions
     */
    init: function () {
        this.setFields()
        this.addEventListener()
    },

    /**
     * initialize JQ selectors
     */
    setFields: function () {
        this.importForm = jQuery('.js-wpSmsImportForm')
    },

    addEventListener: function () {

        jQuery('.js-wpSmsImportForm').on('submit', function (event) {

            // avoid to execute the actual submit of the form
            event.preventDefault()

            console.log('test')

            let importButton = jQuery('.js-wpSmsImportButton')
            let importForm = jQuery('.js-wpSmsImportForm')

            var dataTypeIndex = []

            jQuery('.import-column-type').forEach(function (item) {
                // console.log(item)
            })

            let requestBody = {
                name: '',
                mobile: '',
                groupID: ''
            }

            jQuery.ajax({
                url: wpSmsGlobalTemplateVar.importSubscriberCsv,
                method: 'GET',
                data: requestBody,

                // enabling loader
                beforeSend: function () {

                },

                // successful request
                success: function (request, data, xhr) {
                    console.log('success')
                },

                // failed request
                error: function (data, response, xhr) {
                    console.log('failed')
                }
            })

        }.bind(this))
    },

}