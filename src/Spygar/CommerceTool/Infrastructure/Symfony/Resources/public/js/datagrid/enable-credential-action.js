define(['underscore', 'oro/datagrid/ajax-action'], function (_, AjaxAction) {
  'use strict';

  return AjaxAction.extend({
    noHref: true,

    createLauncher: function (options) {
      options = options || {};
      options.label = this.credentialIsActive() ? 'Disable' : 'Enable';
      options.attributes = _.extend({}, options.attributes, {'aria-label': options.label});

      return AjaxAction.prototype.createLauncher.call(this, options);
    },

    getMethod: function () {
      return 'POST';
    },

    credentialIsActive: function () {
      const value = this.model.get('active');

      return true === value || 1 === value || '1' === value || 'true' === value;
    },
  });
});
