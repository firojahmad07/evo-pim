'use strict';

define(['pim/form/common/delete', 'spygar/commercetool/remover/attribute-mapping'], function (DeleteForm, Remover) {
  return DeleteForm.extend({
    remover: Remover,
  });
});
