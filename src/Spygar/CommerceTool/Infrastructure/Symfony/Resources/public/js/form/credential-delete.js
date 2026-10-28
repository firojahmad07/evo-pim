'use strict';

define(['pim/form/common/delete', 'spygar/commercetool/remover/credential'], function (DeleteForm, Remover) {
  return DeleteForm.extend({
    remover: Remover,
  });
});
