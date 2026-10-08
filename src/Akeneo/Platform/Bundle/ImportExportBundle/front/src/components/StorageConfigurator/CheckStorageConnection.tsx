import React from 'react';
import styled from 'styled-components';
import { Button, CheckIcon, getColor, Helper } from 'akeneo-design-system';

const AnyButton = Button as any;
const AnyCheckIcon = CheckIcon as any;
const AnyHelper = Helper as any;
import { useTranslate } from '@akeneo-pim-community/shared';
import { useCheckStorageConnection } from '../../hooks';
import { RemoteStorage } from '../../models';

const Form = styled.div`
  display: flex;
  flex-direction: column;
  gap: 5px;
`;

const Field = styled.div`
  display: flex;
  align-items: center;
  gap: 8.5px;
  color: ${getColor('green', 100)};
`;

type CheckStorageConnectionProps = {
  jobInstanceCode: string;
  storage: RemoteStorage;
};

const CheckStorageConnection = ({ jobInstanceCode, storage }: CheckStorageConnectionProps) => {
  const translate = useTranslate();
  const [isValid, canCheckConnection, checkReliability] = useCheckStorageConnection(jobInstanceCode, storage);

  return (
    <Form>
      <Field>
        <AnyButton onClick={checkReliability} disabled={!canCheckConnection} level="primary">
          {translate('pim_import_export.form.job_instance.connection_checker.label')}
        </AnyButton>
        {isValid && <AnyCheckIcon />}
      </Field>
      {false === isValid && (
        <AnyHelper inline={true} level="error">
          {translate('pim_import_export.form.job_instance.connection_checker.exception')}
        </AnyHelper>
      )}
    </Form>
  );
};

export { CheckStorageConnection };
