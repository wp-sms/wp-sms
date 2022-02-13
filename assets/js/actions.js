jQuery(document).ready(function () {
    actionPreview.init();
});

const { __, _x, _n, sprintf } = wp.i18n;

let actionPreview = {

    allActions: WPSmsTwoWayActions,

    init: function () {
        this.setFields()
        this.updatePreview()
        this.addEventListener()
    },

    setFields: function () {
        this.actionSelect = jQuery('#wpsms-tw-command-action-select')
        this.commandNameInput = jQuery('#wpsms-tw-command-name')
        this.commandBubble = jQuery('.sms-preview-js .command')
        this.responseBubble = jQuery('.sms-preview-js .response')
    },

    addEventListener: function () {
        this.actionSelect.change(function () {
            this.updatePreview()
        }.bind(this));
        this.commandNameInput.on('input', function () {
            this.updatePreview()
        }.bind(this));
    },

    setAction: function () {

        let actionReference = this.actionSelect.val().split('/')
        let actionClassName = actionReference[0]
        let actionName = actionReference[1]
        let action = this.allActions[actionClassName]['actions'][actionName]

        this.action = action
    },

    setCommandName: function () {
        this.commandName = this.commandNameInput.val().trim().replaceAll(' ', '-')
    },

    updatePreview: function () {
        this.setAction()
        this.setCommandName()
        this.updateBubbles()
    },

    updateBubbles: function () {
        if (!this.commandName) {
            this.commandBubble.text(__('Command name must be filled'))
            return
        }
        let commandText = this.commandName
        for (const paramKey in this.action.params) {
            commandText += ' ' + '<span class="command-arg">' + this.action.params[paramKey]['example'] + '</span>'
        }
        this.commandBubble.html(commandText)

    }
}