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

      return FetcherRegistry.getFetcher('commercetool-attribute-mapping')
        .fetch(route.params.identifier, {cached: false})
        .then(mapping => {
          if (!this.active) {
            return;
          }

          PageTitle.set({'mapping.code': mapping.code});

          return FormBuilder.build('pim-commercetool-attribute-mapping-edit-form').then(form => {
            this.on('pim:controller:can-leave', event => {
              form.trigger('pim_enrich:form:can-leave', event);
            });
            form.setData(mapping);
            form.setElement(this.$el).render();
            form.trigger('pim_enrich:form:entity:post_fetch', mapping);

            return form;
          });
        });
    },
  });
});
