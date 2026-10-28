'use strict';

define([
  'oro/mediator',
  'pim/controller/front',
  'pim/form-builder',
  'pim/fetcher-registry',
  'pim/page-title',
], function (mediator, BaseController, FormBuilder, FetcherRegistry, PageTitle) {
  return BaseController.extend({
    renderForm: function () {
      mediator.trigger('pim_menu:highlight:tab', {extension: 'pim-menu-connect'});

      return FetcherRegistry.getFetcher('commercetool-settings')
        .fetch('default', {cached: false})
        .then(settings => {
          if (!this.active) {
            return;
          }

          PageTitle.set({});

          return FormBuilder.build('pim-commercetool-settings-edit-form').then(form => {
            this.on('pim:controller:can-leave', event => {
              form.trigger('pim_enrich:form:can-leave', event);
            });
            form.setData(settings);
            form.setElement(this.$el).render();
            form.trigger('pim_enrich:form:entity:post_fetch', settings);

            return form;
          });
        });
    },
  });
});
