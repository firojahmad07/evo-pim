const BaseForm = require('pim/form');
const mediator = require('oro/mediator');
const __ = require('oro/translator');

type MenuItem = {
  code: string;
  label: string;
  route: string;
  position: number;
};

type Connector = {
  code: string;
  label: string;
  logo: string | null;
  menu: MenuItem[];
};

type CurrentRoute = {
  name: string;
  params: {code?: string};
};

const SECTION_CODE = 'pim-menu-connector-navigation-block';

class ConnectorParamsProvider extends BaseForm {
  private catalog: Connector[] | null = null;
  private currentRoute: CurrentRoute | null = null;

  initialize(): ConnectorParamsProvider {
    this.catalog = null;
    this.currentRoute = null;

    fetch('/rest/connectors')
      .then(response => (response.ok ? response.json() : []))
      .then((catalog: Connector[]) => {
        this.catalog = catalog;
        this.apply();
      });

    mediator.on('route_start', this.onRouteStart, this);
    mediator.on('route_complete', this.onRouteStart, this);

    return BaseForm.prototype.initialize.apply(this, arguments);
  }

  onRouteStart(name: string, params: {code?: string} = {}): void {
    this.currentRoute = {name, params: params || {}};
    this.apply();
  }

  private apply(): void {
    const route = this.currentRoute;
    if (null === this.catalog || null === route) {
      return;
    }

    const column = this.findColumn();
    if (undefined === column) {
      return;
    }

    const code = route.params.code;
    if ('spygar_connectors_index' === route.name || undefined === code || '' === code) {
      column.navigationItems.splice(0, column.navigationItems.length);

      return;
    }

    const connector = this.catalog.find(item => item.code === code);
    if (undefined === connector) {
      column.navigationItems.splice(0, column.navigationItems.length);

      return;
    }

    const items = connector.menu.slice().sort((left, right) => left.position - right.position);
    const current =
      items.find(item => item.route === route.name) ||
      items.find(item => this.sectionOf(item.route) === this.sectionOf(route.name)) ||
      items[0];
    column.navigationItems.splice(
      0,
      column.navigationItems.length,
      ...items.map(item => ({
        code: this.itemCode(connector.code, item.route),
        route: item.route,
        title: __(item.label),
        label: __(item.label),
        position: item.position,
        routeParams: {code: connector.code},
        sectionCode: SECTION_CODE,
      }))
    );
    if (undefined === current) {
      return;
    }

    mediator.trigger('pim_menu:highlight:tab', {extension: 'pim-menu-connect'});
    mediator.trigger('pim_menu:highlight:item', {extension: this.itemCode(connector.code, current.route)});
  }

  private findColumn(): any {
    const stack = [this.getRoot()];
    const seen = new Set<any>();

    while (0 < stack.length) {
      const view = stack.pop();
      if (undefined === view || null === view || seen.has(view)) {
        continue;
      }
      seen.add(view);
      if ('pim-menu-connector-column' === view.code) {
        return view;
      }
      if (view.extensions) {
        Object.values(view.extensions).forEach((child: any) => stack.push(child));
      }
    }

    return undefined;
  }

  private itemCode(connectorCode: string, route: string): string {
    return `spygar-connector-${connectorCode}-${route}`.replace(/[^a-zA-Z0-9_-]/g, '_');
  }

  private sectionOf(routeName: string): string {
    return routeName.replace(/_(index|edit|create|remove)$/, '');
  }
}

export = ConnectorParamsProvider;
