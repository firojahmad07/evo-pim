'use strict';

define(['underscore', 'pim/form', 'routing', 'oro/mediator'], function (_, BaseForm, Routing, mediator) {
  return BaseForm.extend({
    configure: function () {
      mediator.on('pim_menu:highlight:item', this.render, this);

      return BaseForm.prototype.configure.apply(this, arguments);
    },

    render: function () {
      const column = this.column();
      const items = undefined === column ? [] : column.navigationItems || [];
      const hash = window.location.hash.replace(/^#/, '').split('|')[0];

      this.$el.html(
        items
          .map(item => {
            const path = Routing.generate(item.route, item.routeParams || {});
            const url = Routing.generateHash(item.route, item.routeParams || {});
            const active = hash === path || 0 === hash.indexOf(path + '/');

            return (
              '<a href="' +
              _.escape(url) +
              '" class="AknColumn-navigationLink' +
              (active ? ' AknColumn-navigationLink--active' : '') +
              ' navigation-item">' +
              _.escape(item.label || item.title || '') +
              '</a>'
            );
          })
          .join('')
      );

      return this;
    },

    column: function () {
      let view = this.getParent();
      while (undefined !== view && null !== view) {
        if ('pim-menu-connector-column' === view.code) {
          return view;
        }
        view = view.getParent();
      }

      return undefined;
    },
  });
});
