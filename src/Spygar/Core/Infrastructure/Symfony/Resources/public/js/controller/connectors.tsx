import React from 'react';
import {ConnectorsApp} from '../connectors/ConnectorsApp';
import ReactController from '../react/react-controller';

const mediator = require('oro/mediator');

class ConnectorsController extends ReactController {
    reactElementToMount() {
        return <ConnectorsApp />;
    }

    routeGuardToUnmount() {
        return /^spygar_connectors_(index|edit|any)$/;
    }

    renderRoute(route: {name: string}) {
        if ('spygar_connectors_index' === route.name) {
            mediator.trigger('pim_menu:highlight:tab', {extension: 'pim-menu-connect'});
            mediator.trigger('pim_menu:highlight:item', {extension: 'pim-menu-connect-connectors'});
        }

        return super.renderRoute(route);
    }
}

export = ConnectorsController;
