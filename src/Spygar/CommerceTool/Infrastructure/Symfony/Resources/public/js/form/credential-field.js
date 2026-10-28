define(['jquery', 'underscore', 'oro/translator', 'pim/form', 'pim/template/form/creation/field'], function (
  $,
  _,
  __,
  BaseForm,
  template
) {
  'use strict';

  return BaseForm.extend({
    template: _.template(template),
    events: {
      'input input': 'updateModel',
      'change input': 'updateModel',
    },

    initialize: function (config) {
      this.config = config.config;
      this.identifier = this.config.identifier || 'code';

      BaseForm.prototype.initialize.apply(this, arguments);
    },

    updateModel: function (event) {
      this.getFormModel().set(this.identifier, event.target.value || '');
    },

    render: function () {
      const errors = this.getRoot().validationErrors || [];
      const value = this.getFormData()[this.identifier];

      this.$el.html(
        this.template({
          identifier: this.identifier,
          label: this.config.plainLabel ? this.config.label : __(this.config.label),
          requiredLabel: __('pim_common.required_label'),
          errors: errors.filter(error => {
            return this.identifier === error.path || this.identifier === error.attribute;
          }),
          value: null == value ? '' : value,
        })
      );

      if (this.config.secret) {
        this.$('input').attr('type', 'password').attr('autocomplete', 'new-password');
      } else {
        this.$('input').attr('autocomplete', 'off');
      }

      this.delegateEvents();

      return this;
    },
  });
});
