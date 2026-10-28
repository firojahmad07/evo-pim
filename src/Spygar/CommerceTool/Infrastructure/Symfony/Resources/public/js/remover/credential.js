'use strict';

define(['underscore', 'pim/remover/base', 'routing'], function (_, BaseRemover, Routing) {
  return _.extend({}, BaseRemover, {
    getUrl: function (code) {
      return Routing.generate('spygar_commercetool_credential_rest_remove', {identifier: code});
    },
  });
});
