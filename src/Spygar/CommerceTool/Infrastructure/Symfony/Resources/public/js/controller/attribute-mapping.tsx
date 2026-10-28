import React from 'react';
import ReactController from 'spygar/react/react-controller';
import {AttributeMappingApp} from '../attribute-mapping/AttributeMappingApp';

const mediator = require('oro/mediator');

class AttributeMappingController extends ReactController {
  reactElementToMount() {
    return <AttributeMappingApp />;
  }

  routeGuardToUnmount() {
    return /^spygar_commercetool_attribute_mapping_index$/;
  }

  renderRoute(route: {name: string}) {
    mediator.trigger('pim_menu:highlight:tab', {extension: 'pim-menu-connect'});

    return super.renderRoute(route);
  }
}

export = AttributeMappingController;
