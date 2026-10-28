<?php

declare(strict_types=1);

namespace Pim\Upgrade\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_8_0_20261027120000_create_spygar_commercetool_tables extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS spygar_commercetool_credential (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(100) NOT NULL,
                label VARCHAR(255) NOT NULL,
                project_key VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                client_secret LONGTEXT NOT NULL,
                region VARCHAR(100) DEFAULT NULL,
                auth_url VARCHAR(255) DEFAULT NULL,
                api_url VARCHAR(255) DEFAULT NULL,
                scope LONGTEXT DEFAULT NULL,
                active TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_SPYGAR_CT_CREDENTIAL_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS spygar_commercetool_attribute_mapping (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(100) NOT NULL,
                akeneo_attribute VARCHAR(255) NOT NULL,
                commercetools_attribute VARCHAR(255) NOT NULL,
                variant_akeneo_attribute VARCHAR(255) DEFAULT NULL,
                mapping_type VARCHAR(20) NOT NULL DEFAULT 'field',
                position INT NOT NULL DEFAULT 0,
                locale VARCHAR(20) DEFAULT NULL,
                channel VARCHAR(100) DEFAULT NULL,
                active TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_SPYGAR_CT_MAPPING_CODE (code),
                INDEX akeneo_attribute_idx (akeneo_attribute),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS spygar_commercetool_settings (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(100) NOT NULL,
                enabled TINYINT(1) NOT NULL,
                export_products TINYINT(1) NOT NULL,
                export_categories TINYINT(1) NOT NULL,
                import_products TINYINT(1) NOT NULL,
                debug TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_SPYGAR_CT_SETTINGS_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE IF NOT EXISTS spygar_commercetool_credential_catalog (
                credential_code VARCHAR(100) NOT NULL,
                locales JSON NOT NULL,
                currencies JSON NOT NULL,
                channels JSON NOT NULL,
                locale_mapping JSON NOT NULL,
                currency_mapping JSON NOT NULL,
                channel_mapping JSON NOT NULL,
                fetched_at DATETIME NOT NULL,
                PRIMARY KEY (credential_code)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS spygar_commercetool_credential_catalog');
        $this->addSql('DROP TABLE IF EXISTS spygar_commercetool_credential');
        $this->addSql('DROP TABLE IF EXISTS spygar_commercetool_attribute_mapping');
        $this->addSql('DROP TABLE IF EXISTS spygar_commercetool_settings');
    }
}
