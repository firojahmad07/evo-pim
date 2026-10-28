'use strict';

define([
  'oro/mediator',
  'pim/controller/front',
  'pim/form-builder',
  'pim/fetcher-registry',
  'pim/page-title',
], function (mediator, BaseController, FormBuilder, FetcherRegistry, PageTitle) {
  return BaseController.extend({
    renderForm: function (route) {
      mediator.trigger('pim_menu:highlight:tab', {extension: 'pim-menu-connect'});

      return FetcherRegistry.getFetcher('commercetool-credential')
        .fetch(route.params.identifier, {cached: false})
        .then(credential => {
          if (!this.active) {
            return;
          }

          PageTitle.set({'credential.label': credential.label || credential.code});

          return FormBuilder.build('pim-commercetool-credential-edit-form').then(form => {
            this.on('pim:controller:can-leave', event => {
              form.trigger('pim_enrich:form:can-leave', event);
            });
            form.setData(credential);
            form.setElement(this.$el).render();
            form.trigger('pim_enrich:form:entity:post_fetch', credential);

            return form;
          });
        });
    },
  });
});
