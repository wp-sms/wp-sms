jQuery(function (jQuery) {

    var $uploadButton = jQuery('.wpsms-upload-button')
    var $removeButton = jQuery('.wpsms-remove-button')
    var $imageElement = jQuery('.wpsms-mms-image')

    // on upload button click
    $uploadButton.on('click', function (e) {
        e.preventDefault();

        var button = jQuery(this),
            wpsms_uploader = wp.media({
                title: 'Insert image',
                library: {
                    type: ['image']
                },
                button: {
                    text: 'Use this image'
                },
                multiple: false
            }).on('select', function () {
                var attachment = wpsms_uploader.state().get('selection').first().toJSON();

                button.html('<img width="300" src="' + attachment.url + '">');
                $imageElement.val(attachment.url)
                $removeButton.show()

            }).open();
    })

    // on remove button click
    $removeButton.on('click', function (e) {
        e.preventDefault();

        jQuery(this).hide()
        $imageElement.val('')
        $uploadButton.html('Upload image')
    });

});