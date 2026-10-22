import {Deferred} from 'jquery';
import {mountReactElementRef, unmountReactElementRef} from './react-element-helper';

const BaseController = require('pim/controller/base');
const mediator = require('oro/mediator');

abstract class ReactController extends BaseController {
  abstract reactElementToMount(): JSX.Element;

  abstract routeGuardToUnmount(): RegExp;

  initialize() {
    mediator.on('route_start', this.handleRouteChange, this);

    return super.initialize();
  }

  renderRoute(_route: {name: string}) {
    this.$el.append(mountReactElementRef(this.reactElementToMount()));

    return Deferred().resolve();
  }

  remove() {
    mediator.off('route_start', this.handleRouteChange, this);
    this.$el.remove();

    return super.remove();
  }

  private handleRouteChange(routeName: string) {
    if (this.routeGuardToUnmount().test(routeName)) {
      return;
    }

    unmountReactElementRef();
  }
}

export = ReactController;
