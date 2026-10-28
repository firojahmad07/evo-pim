import React, {useContext, useEffect, useState} from 'react';
import {HashRouter as Router, Route, Switch, useHistory, useParams} from 'react-router-dom';
import styled, {ThemeProvider} from 'styled-components';
import {Breadcrumb, pimTheme} from 'akeneo-design-system';

const __ = require('oro/translator');
const Routing = require('routing');
const router = require('pim/router');

type Connector = {
    code: string;
    label: string;
    logo: string | null;
    menu: Array<{code: string; label: string; route: string; position: number}>;
};

const ConnectorsContext = React.createContext<Connector[] | null>(null);

const useConnectors = () => useContext(ConnectorsContext);

const ConnectorsApp = () => {
    const [connectors, setConnectors] = useState<Connector[] | null>(null);

    useEffect(() => {
        let cancelled = false;
        fetch('/rest/connectors')
            .then(response => (response.ok ? response.json() : []))
            .then((loaded: Connector[]) => {
                if (!cancelled) {
                    setConnectors(loaded);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <ThemeProvider theme={pimTheme}>
            <ConnectorsContext.Provider value={connectors}>
                <Router>
                    <Switch>
                        <Route exact path="/connect/connectors">
                            <ListConnectors />
                        </Route>
                        <Route path="/connect/connectors/:code">
                            <ConnectorDetails />
                        </Route>
                    </Switch>
                </Router>
            </ConnectorsContext.Provider>
        </ThemeProvider>
    );
};

const ListConnectors = () => {
    const connectors = useConnectors();
    const history = useHistory();

    return (
        <Page>
            <Header>
                <Breadcrumb>
                    <Breadcrumb.Step href={`#${Routing.generate('akeneo_connectivity_connection_audit_index')}`}>
                        {__('pim_menu.tab.connect')}
                    </Breadcrumb.Step>
                    <Breadcrumb.Step>{__('pim_menu.item.spygar_connectors')}</Breadcrumb.Step>
                </Breadcrumb>
                <Title>{__('pim_menu.item.spygar_connectors')}</Title>
            </Header>
            <Content>
                {null === connectors ? null : 0 === connectors.length ? (
                    <Empty>{__('spygar.connectors.empty')}</Empty>
                ) : (
                    <Grid>
                        {connectors.map(connector => (
                            <Card
                                key={connector.code}
                                onClick={() => history.push(`/connect/connectors/${connector.code}`)}
                            >
                                <Logo>
                                    {null === connector.logo ? (
                                        <Initial>{connector.label.charAt(0).toUpperCase()}</Initial>
                                    ) : (
                                        <img src={connector.logo} alt="" />
                                    )}
                                </Logo>
                                <Caption title={connector.label}>{connector.label}</Caption>
                            </Card>
                        ))}
                    </Grid>
                )}
            </Content>
        </Page>
    );
};

const ConnectorDetails = () => {
    const connectors = useConnectors();
    const history = useHistory();
    const {code} = useParams<{code: string}>();
    const connector = null === connectors ? null : connectors.find(item => item.code === code) || null;

    const destination =
        null === connector
            ? null
            : connector.menu.slice().sort((left, right) => left.position - right.position)[0] || null;

    useEffect(() => {
        if (null !== connectors && null === connector) {
            history.replace('/connect/connectors');
        }
    }, [connectors, connector, history]);

    useEffect(() => {
        if (null === connector || null === destination) {
            return;
        }

        router.redirectToRoute(destination.route, {code: connector.code}, {trigger: true});
    }, [connector, destination]);

    if (null === connector || null !== destination) {
        return null;
    }

    return (
        <Page>
            <Header>
                <Breadcrumb>
                    <Breadcrumb.Step href={`#${Routing.generate('akeneo_connectivity_connection_audit_index')}`}>
                        {__('pim_menu.tab.connect')}
                    </Breadcrumb.Step>
                    <Breadcrumb.Step href={history.createHref({pathname: '/connect/connectors'})}>
                        {__('pim_menu.item.spygar_connectors')}
                    </Breadcrumb.Step>
                    <Breadcrumb.Step>{connector.label}</Breadcrumb.Step>
                </Breadcrumb>
                <Identity>
                    <IdentityLogo>
                        {null === connector.logo ? (
                            <Initial>{connector.label.charAt(0).toUpperCase()}</Initial>
                        ) : (
                            <img src={connector.logo} alt="" />
                        )}
                    </IdentityLogo>
                    <Title>{connector.label}</Title>
                </Identity>
            </Header>
            <Content>
                <Field>
                    <FieldLabel>{__('pim_common.code')}</FieldLabel>
                    <FieldValue>{connector.code}</FieldValue>
                </Field>
            </Content>
        </Page>
    );
};

const Page = ({children}: {children: React.ReactNode}) => (
    <div className="AknDefault-contentWithColumn">
        <div className="AknDefault-contentWithBottom">
            <div className="AknDefault-mainContent">{children}</div>
        </div>
    </div>
);

const Header = styled.header`
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 40px 40px 20px;
    background: white;
`;

const Title = styled.h1`
    margin: 0;
    color: ${({theme}) => theme.color.brand100};
    font-size: ${({theme}) => theme.fontSize.title};
    font-weight: 400;
    line-height: 32px;
`;

const Identity = styled.div`
    display: flex;
    align-items: center;
    gap: 20px;
`;

const IdentityLogo = styled.div`
    width: 142px;
    height: 142px;
    flex: 0 0 142px;
    border: 1px solid ${({theme}) => theme.color.grey80};
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: white;

    img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
`;

const Content = styled.div`
    padding: 10px 40px 40px;
`;

const Empty = styled.p`
    margin: 20px 0;
    color: ${({theme}) => theme.color.grey120};
    font-size: ${({theme}) => theme.fontSize.default};
`;

const Grid = styled.div`
    display: grid;
    grid-gap: 20px;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
`;

const Card = styled.button`
    padding: 0;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
    min-width: 0;
    font: inherit;
`;

const Logo = styled.div`
    position: relative;
    height: 140px;
    border: 1px solid ${({theme}) => theme.color.grey80};
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;

    img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    ::after {
        content: '';
        position: absolute;
        inset: 0;
        background: ${({theme}) => theme.color.grey140};
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    ${Card}:hover &::after {
        opacity: 0.4;
    }
`;

const Initial = styled.span`
    color: ${({theme}) => theme.color.brand100};
    font-size: 42px;
    line-height: 1;
`;

const Caption = styled.div`
    margin-top: 5px;
    height: 20px;
    line-height: 20px;
    color: ${({theme}) => theme.color.grey140};
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
`;

const Field = styled.div`
    max-width: 400px;
`;

const FieldLabel = styled.div`
    color: ${({theme}) => theme.color.grey120};
    font-size: ${({theme}) => theme.fontSize.small};
    text-transform: uppercase;
`;

const FieldValue = styled.div`
    margin-top: 4px;
    color: ${({theme}) => theme.color.grey140};
`;

export {ConnectorsApp};
