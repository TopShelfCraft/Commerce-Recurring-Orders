if (typeof Craft.RecurringOrders === typeof undefined) {
    Craft.RecurringOrders = {};
}

Craft.RecurringOrders.OrdersWidgetSettings = Garnish.Base.extend({

    init: function(id, settings) {

        this.$container = $('#' + id);
        this.$menuBtn = $('.menubtn', this.$container);
        this.$statusInput = $('.recurrenceStatusInput', this.$container);

        this.menuBtn = new Garnish.MenuBtn(this.$menuBtn, {
            onOptionSelect: $.proxy(this, 'onSelectStatus')
        });

        var status = this.$statusInput.val();

        var $currentStatus = $('[data-recurrence-status="' + status + '"]', this.menuBtn.menu.$container);

        $currentStatus.trigger('click');

    },

    onSelectStatus: function(status) {

        this.deselectStatus();

        var $status = $(status);
        $status.addClass('sel');

        this.selectedStatus = $status;

        this.$statusInput.val($status.data('recurrence-status'));
        console.log($status.data('recurrence-status'));
        console.log(this.$statusInput[0]);

        // clone selected status item to menu menu
        var $label = $('.statusLabel', $status);
        this.$menuBtn.empty();
        $label.clone().appendTo(this.$menuBtn);

    },

    deselectStatus: function() {

        if (this.selectedStatus) {
            this.selectedStatus.removeClass('sel');
        }

    }

});

