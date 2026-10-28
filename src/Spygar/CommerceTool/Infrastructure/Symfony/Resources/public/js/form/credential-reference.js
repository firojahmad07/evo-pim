define(['jquery', 'underscore', 'pim/form'], function ($, _, BaseForm) {
  'use strict';

  const escape = value => _.escape(null == value ? '' : String(value));

  const codes = payload => {
    const list = Array.isArray(payload) ? payload : Object.values(payload || {});

    return _.uniq(
      list
        .map(item => {
          if (typeof item === 'string') {
            return item;
          }
          if (item && typeof item.code === 'string') {
            return item.code;
          }

          return '';
        })
        .filter(Boolean)
    ).sort();
  };

  const mappingValue = (mapping, code) => {
    if (!mapping || typeof mapping !== 'object' || Array.isArray(mapping)) {
      return '';
    }

    return typeof mapping[code] === 'string' ? mapping[code] : '';
  };

  const messageFrom = body => {
    if (body && typeof body.message === 'string' && body.message) {
      return body.message;
    }
    if (Array.isArray(body) && body[0] && body[0].message) {
      return body[0].message;
    }
    if (body && body.values && body.values[0] && body.values[0].message) {
      return body.values[0].message;
    }

    return 'CommerceTool could not be reached.';
  };

  return BaseForm.extend({
    events: {
      'change select': 'onChange',
      'click .ct-refresh': 'onRefresh',
    },

    configure: function () {
      this.akeneo = null;
      this.loading = null;
      this.refreshing = false;
      this.errorMessage = null;
      this.listenTo(this.getRoot(), 'pim_enrich:form:entity:post_fetch', this.render);

      return BaseForm.prototype.configure.apply(this, arguments);
    },

    render: function () {
      if (!this.akeneo) {
        this.$el.html('<div class="ct-reference"><p>Loading Akeneo locales, currencies, and channels.</p></div>');
        if (!this.loading) {
          this.loading = this.loadAkeneo();
        }

        return this;
      }

      this.draw();

      return this;
    },

    loadAkeneo: function () {
      const load = url =>
        fetch(url, {
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
          },
        }).then(response => {
          if (!response.ok) {
            throw new Error('failed');
          }

          return response.json();
        });

      return Promise.all([
        load('/configuration/locale/rest?activated=true'),
        load('/configuration/currency/rest'),
        load('/configuration/channel/rest'),
      ])
        .then(([locales, currencies, channels]) => {
          this.akeneo = {
            locales: codes(locales),
            currencies: codes(currencies),
            channels: codes(channels),
          };
        })
        .catch(() => {
          this.akeneo = {locales: [], currencies: [], channels: []};
          this.errorMessage = 'Akeneo locales, currencies, and channels could not be loaded.';
        })
        .then(() => this.draw());
    },

    onChange: function (event) {
      const select = event.currentTarget;
      const kind = select.getAttribute('data-kind');
      const code = select.getAttribute('data-code');
      if (!kind || !code) {
        return;
      }

      const data = this.getFormData();
      const current = data[kind];
      const mapping =
        current && typeof current === 'object' && !Array.isArray(current) ? $.extend({}, current) : {};
      if (select.value) {
        mapping[code] = select.value;
      } else {
        delete mapping[code];
      }
      data[kind] = mapping;
      this.setData(data);
    },

    onRefresh: function (event) {
      event.preventDefault();
      const code = this.getFormData().code;
      if (!code || this.refreshing) {
        return;
      }

      this.refreshing = true;
      this.errorMessage = null;
      this.draw();

      fetch('/rest/commercetool/credentials/' + encodeURIComponent(code) + '/refresh', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then(response =>
          response
            .json()
            .catch(() => ({}))
            .then(body => ({ok: response.ok, body: body}))
        )
        .then(result => {
          this.refreshing = false;
          if (!result.ok) {
            this.errorMessage = messageFrom(result.body);
            this.draw();

            return;
          }

          const data = $.extend(true, {}, this.getFormData());
          data.catalog = result.body.catalog || {locales: [], currencies: [], channels: []};
          data.locale_mapping = result.body.locale_mapping || {};
          data.currency_mapping = result.body.currency_mapping || {};
          data.channel_mapping = result.body.channel_mapping || {};
          this.errorMessage = null;
          this.setData(data);
          this.draw();
        })
        .catch(() => {
          this.refreshing = false;
          this.errorMessage = 'CommerceTool could not be reached.';
          this.draw();
        });
    },

    draw: function () {
      const data = this.getFormData();
      const catalog = data.catalog || {};
      const locales = Array.isArray(catalog.locales) ? catalog.locales : [];
      const currencies = Array.isArray(catalog.currencies) ? catalog.currencies : [];
      const channels = Array.isArray(catalog.channels) ? catalog.channels : [];
      const akeneo = this.akeneo || {locales: [], currencies: [], channels: []};
      const loaded = locales.length + currencies.length + channels.length;
      const summary =
        loaded > 0
          ? locales.length +
            ' locales, ' +
            currencies.length +
            ' currencies, and ' +
            channels.length +
            ' channels loaded from CommerceTool.'
          : 'No locales, currencies, or channels have been loaded from CommerceTool yet.';

      this.$el.html(
        '<div class="ct-reference">' +
          '<style>' +
          '.ct-reference .ct-table {width:100%; border-collapse:collapse; margin:0 0 16px;}' +
          '.ct-reference .ct-table th {text-align:left; font-size:12px; text-transform:uppercase; color:#67768a; padding:8px 12px 8px 0;}' +
          '.ct-reference .ct-table td {padding:8px 16px 8px 0; vertical-align:middle;}' +
          '.ct-reference .ct-code {color:#67768a;}' +
          '.ct-reference select {min-width:240px; max-width:100%;}' +
          '.ct-reference .ct-error {color:#d23d3d; margin:8px 0;}' +
          '.ct-reference h3 {font-size:16px; margin:18px 0 8px;}' +
          '</style>' +
          '<p>These values come from the CommerceTool project. Choose the Akeneo locale, currency, or channel each one should use. Save the credential after changing the connection, then load the values again.</p>' +
          '<p>' +
          escape(summary) +
          (catalog.fetched_at ? ' Loaded ' + escape(catalog.fetched_at) + '.' : '') +
          '</p>' +
          (this.errorMessage ? '<p class="ct-error">' + escape(this.errorMessage) + '</p>' : '') +
          '<button type="button" class="AknButton ct-refresh"' +
          (this.refreshing ? ' disabled' : '') +
          '>' +
          (this.refreshing ? 'Loading from CommerceTool' : 'Refresh from CommerceTool') +
          '</button>' +
          this.group('Locales', 'locale_mapping', locales.map(code => ({code: code, label: code})), akeneo.locales, data.locale_mapping) +
          this.group('Currencies', 'currency_mapping', currencies.map(code => ({code: code, label: code})), akeneo.currencies, data.currency_mapping) +
          this.group(
            'Channels',
            'channel_mapping',
            channels.map(channel => ({
              code: channel.key || channel.id || '',
              label: channel.label && channel.label !== (channel.key || channel.id) ? channel.label + ' [' + (channel.key || channel.id) + ']' : channel.key || channel.label || '',
            })),
            akeneo.channels,
            data.channel_mapping
          ) +
          '</div>'
      );
      this.delegateEvents();
    },

    group: function (title, kind, rows, options, mapping) {
      const body = rows
        .filter(row => row.code)
        .map(row => {
          return (
            '<tr><td>' +
            escape(row.label) +
            '</td><td>' +
            this.select(kind, row.code, mappingValue(mapping, row.code), options) +
            '</td></tr>'
          );
        })
        .join('');

      return (
        '<h3>' +
        escape(title) +
        '</h3>' +
        (body
          ? '<table class="ct-table"><thead><tr><th>CommerceTool</th><th>Akeneo</th></tr></thead><tbody>' +
            body +
            '</tbody></table>'
          : '<p class="ct-code">None returned by CommerceTool.</p>')
      );
    },

    select: function (kind, code, selected, options) {
      const choices = options.slice();
      if (selected && choices.indexOf(selected) === -1) {
        choices.unshift(selected);
      }

      const html = choices
        .map(option => {
          return (
            '<option value="' +
            escape(option) +
            '"' +
            (option === selected ? ' selected' : '') +
            '>' +
            escape(option) +
            '</option>'
          );
        })
        .join('');

      return (
        '<select class="AknTextField" data-kind="' +
        escape(kind) +
        '" data-code="' +
        escape(code) +
        '"><option value="">Select</option>' +
        html +
        '</select>'
      );
    },
  });
});
