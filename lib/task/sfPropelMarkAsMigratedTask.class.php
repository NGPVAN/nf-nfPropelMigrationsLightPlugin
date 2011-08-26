<?php

/*
 * This file is part of the nfPropelMigrationsLightPlugin package.
 * (c) 2011 Graham Christensen <gchristensen@nationalfield.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A symfony 1.1 port for the pake migrate task.
 *
 * @package     nfPropelMigrationsLightPlugin
 * @subpackage  task
 * @author      Graham Christensen <gchristensen@nationalfield.org>
 */
class sfPropelMarkAsMigratedTask extends sfPropelBaseTask
{

    protected function configure()
    {
        $this->addArguments(array(
            new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
            new sfCommandArgument('schema-version', sfCommandArgument::OPTIONAL, 'The target schema version'),
        ));

        $this->addOptions(array(
            new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
        ));

        $this->namespace = 'propel';
        $this->name = 'mark-as-migrated';
        $this->briefDescription = 'Force marks the database schema to another version';
    }

    protected function execute($arguments = array(), $options = array())
    {
        $autoloader = sfSimpleAutoload::getInstance();
        $autoloader->addDirectory(sfConfig::get('sf_plugins_dir') . '/sfPropelMigrationsLightPlugin/lib');

        $configuration = ProjectConfiguration::getApplicationConfiguration($arguments['application'],
                        $options['env'], true);

        $databaseManager = new sfDatabaseManager($configuration);

        $migrator = new sfMigrator;

        if (isset($arguments['schema-version']) && ctype_digit($arguments['schema-version'])) {
            $max = $arguments['schema-version'];
        } else {
            $max = $migrator->getMaxVersion();
        }

        $migrations = $migrator->getMigrationsToRunUpTo($max);

        foreach ($migrations as $migration) {
            echo "Marking as Migrated: $migration\n";
            $migrator->markMigration($migration);
        }
    }

}
