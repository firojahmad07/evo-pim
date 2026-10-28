'use strict';

define(['jquery', 'underscore', 'routing', 'pim/job/common/edit/field/select'], function ($, _, Routing, SelectField) {
  return SelectField.extend({
    configure: function () {
      let url = '/rest/commercetool/credentials/options';
      try {
        url = Routing.generate('spygar_commercetool_credential_rest_options');
      } catch (error) {
        url = '/rest/commercetool/credentials/options';
      }

      const request = $.getJSON(url).then(
        function (credentials) {
          return Array.isArray(credentials) ? credentials : [];
        },
        function () {
          return [];
        }
      );

      return $.when(request, SelectField.prototype.configure.apply(this, arguments)).then(
        function (credentials) {
          const options = {'': 'spygar_commercetool.job.credential_empty'};
          const rows = Array.isArray(credentials) ? credentials : [];
          rows.forEach(function (row) {
            if (!row || !row.code) {
              return;
            }
            const active = true === row.active || 1 === row.active || '1' === row.active;
            const name = row.label || row.code;
            options[row.code] = active ? name : name + ' (disabled)';
          });
          const current = this.getValue();
          if (current && !options[current]) {
            options[current] = current;
          }
          this.config.options = options;
        }.bind(this)
      );
    },

    render: function () {
      SelectField.prototype.render.apply(this, arguments);
      this.syncEmptyLabel();
    },

    getValue: function () {
      const value = SelectField.prototype.getValue.apply(this, arguments);

      return null == value ? '' : value;
    },

    updateState: function () {
      const selected = this.$('select').val() || null;
      const current = SelectField.prototype.getValue.apply(this, arguments) || null;
      if (selected === current) {
        this.syncEmptyLabel();

        return;
      }

      SelectField.prototype.updateState.apply(this, arguments);
      this.syncEmptyLabel();
    },

    syncEmptyLabel: function () {
      if (this.$('select').val()) {
        return;
      }

      this.$('.select2-chosen').text(this.$('select option[value=""]').text());
      this.$('.select2-search-choice-close').hide();
    },
  });
});
