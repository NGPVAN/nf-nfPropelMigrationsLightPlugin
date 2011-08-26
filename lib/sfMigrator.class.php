<?php

/*
 * This file is part of the nfPropelMigrationsLightPlugin package.
 * Originally part of the sfPropelMigrationsLightPlugin package.
 * (c) 2006-2008 Martin Kreidenweis <sf@kreidenweis.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Manage all calls to the sfMigration class instances.
 *
 * @package    symfony
 * @subpackage plugin
 * @author     Graham Christensen <gchristensen@nationalfield.org>
 * @author     Martin Kreidenweis <sf@kreidenweis.com>
 */
class sfMigrator
{
  /**
   * Migration filenames.
   *
   * @var array $migrations
   */
  protected $migrations = array();

  /**
   * Perform an update on the database.
   *
   * @param   string $sql
   *
   * @return  integer
   */
  static public function executeUpdate($sql)
  {
    $con = Propel::getConnection();

    return $con instanceof PropelPDO ? $con->exec($sql) : $con->executeUpdate($sql);
  }

  /**
   * Perform a query on the database.
   *
   * @param   string $sql
   * @param   string $fetchmode
   *
   * @return  mixed
   */
  static public function executeQuery($sql, $fetchmode = null)
  {
    $con = Propel::getConnection();

    if ($con instanceof PropelPDO)
    {
      $stmt = $con->prepare($sql);
      $stmt->execute();

      return $stmt;
    }
    else
    {
      return $con->executeQuery($sql, $fetchmode);
    }
  }

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->loadMigrations();
  }

  /**
   * Execute migrations.
   *
   * @param   integer $destVersion  Version number to migrate to, defaults to
   *                                the max existing
   *
   * @return  integer Number of executed migrations
   */
  public function migrate($destVersion = null)
  {
    $maxVersion = $this->getMaxVersion();
    if ($destVersion === null)
    {
      $destVersion = $maxVersion;
    }
    else
    {
      $destVersion = (int) $destVersion;

      if (($destVersion > $maxVersion) || ($destVersion < 0))
      {
        throw new sfException(sprintf('Migration %d does not exist.', $destVersion));
      }
    }

    $sourceVersion = $this->getCurrentVersion();

    if ($destVersion < $sourceVersion)
    {
      $res = $this->migrateDown($destVersion);
    }
    else
    {
      $res = $this->migrateUp($destVersion);
    }

    return $res;
  }

  /**
   * Generate a new migration stub
   *
   * @param   string $name Name of the new migration
   *
   * @return  string Filename of the new migration file
   */
  public function generateMigration($name)
  {
    // calculate version number for new migration
    $newVersion = date('YmdHis');

    // sanitize name
    $name = preg_replace('/[^a-zA-Z0-9]/', '_', $name);

    $newClass = <<<EOF
<?php

/**
 * Migration up to version $newVersion
 */
class Migration$newVersion extends sfMigration
{
  /**
   * Migrate up to version $newVersion.
   */
  public function up()
  {
  }

  /**
   * Migrate down from version $newVersion.
   */
  public function down()
  {
  }
}

EOF;

    // write new migration stub
    $newFileName = $this->getMigrationsDir().DIRECTORY_SEPARATOR.$newVersion.'_'.$name.'.php';
    file_put_contents($newFileName, $newClass);

    return $newFileName;
  }

  /**
   * Get the list of migration filenames.
   *
   * @return array
   */
  public function getMigrations()
  {
    return $this->migrations;
  }

  /**
   * @return integer The lowest migration that exists
   */
  public function getMinVersion()
  {
    return $this->migrations ? $this->getMigrationNumberFromFile($this->migrations[0]) : 0;
  }

  /**
   * @return integer The highest existing migration that exists
   */
  public function getMaxVersion()
  {
    return max(array_keys($this->migrations));
  }

  /**
   * Get the current schema version from the database.
   *
   * If no schema version is currently stored in the database, one is created
   * and initialized with 0.
   *
   * @return integer
   */
  public function getCurrentVersion()
  {
    try
    {
      $result = $this->executeQuery('SELECT version FROM schema_info ORDER BY version DESC');
      if ($result instanceof PDOStatement)
      {
        $currentVersion = $result->fetchColumn(0);
      }
      else
      {
        if ($result->next())
        {
          $currentVersion = $result->getInt('version');
        }
        else
        {
          throw new sfDatabaseException('Unable to retrieve current schema version.');
        }
      }
    }
    catch (Exception $e)
    {
      // assume no schema_info table exists yet so we create it
      $this->executeUpdate('CREATE TABLE schema_info (version BIGINT)');

      // and insert the version record as 0
      $this->executeUpdate('INSERT INTO schema_info (version) VALUES (0)');
      $currentVersion = 0;
    }

    return $currentVersion;
  }

  /**
   * Get the number encoded in the given migration file name.
   *
   * @param   string $file The filename to look at
   *
   * @return  integer
   */
  public function getMigrationNumberFromFile($file)
  {
    $number = (int)basename($file);

    if (!ctype_digit($number))
    {
      throw new sfParseException('Migration filename could not be parsed.');
    }

    return $number;
  }

  /**
   * Get the directory where migration classes are saved.
   *
   * @return  string
   */
  public function getMigrationsDir()
  {
    return sfConfig::get('sf_data_dir').DIRECTORY_SEPARATOR.'migrations';
  }

  /**
   * Get the directory where migration fixtures are saved.
   *
   * @return  string
   */
  public function getMigrationsFixturesDir()
  {
    return $this->getMigrationsDir().DIRECTORY_SEPARATOR.'fixtures';
  }

  /**
   * Mark a migration as executed or not executed.
   *
   * @param integer $version
   * @param boolean $executed Whether or not a migration has been executed
   */
  public function markMigration($version, $executed = true)
  {
      if ($executed) {
          $this->executeQuery('INSERT IGNORE INTO schema_info (version) VALUES (' . (int) $version . ');');
      } else {
          $this->executeQuery('DELETE FROM schema_info WHERE version = "' . (int) $version . '"');
      }
  }

  /**
   * Retrieve all migration versions after $version which have been executed
   *
   * @param int $version The version you want to base it from
   * @return array(int)
   */
  public function getMigrationsExecutedAfter($version)
  {
      $r = $this->executeQuery('SELECT version FROM schema_info WHERE version > ' . (int)$version);
      return $r->fetchAll(PDO::FETCH_COLUMN);
  }

  /**
   * Get all the migrations before $version which have not been run
   *
   * @param int $version The version you want to go up to
   * @return array(int)
   */
  public function getMigrationsToRunUpTo($version)
  {
      $migrations = array_keys($this->migrations);

      $r = $this->executeQuery('SELECT version FROM schema_info WHERE version <= ' . (int)$version);
      $executedMigrations = $r->fetchAll(PDO::FETCH_COLUMN);

      return array_diff($migrations, $executedMigrations);
  }

  /**
   * Write the given version as current version to the database.
   *
   * @param integer $version New current version
   * @deprecated Use markMigration instead
   */
  protected function setCurrentVersion($version)
  {
    $version = (int) $version;

    $this->executeUpdate("UPDATE schema_info SET version = $version");
  }

  /**
   * Migrate down to version $to.
   *
   * @param   integer $to
   * @return  integer Number of executed migrations
   */
  protected function migrateDown($to)
  {
    $con = Propel::getConnection();
    $counter = 0;

    $migrations = $this->getMigrationsExecutedAfter($to);

    // iterate over all needed migrations
    foreach ($migrations as $version)
    {
      try
      {
        $con instanceof PropelPDO ? $con->beginTransaction() : $con->begin();

        $migration = $this->getMigrationObject($version);
        $migration->down();

        $this->markMigration($version, false);

        $con->commit();
      }
      catch (Exception $e)
      {
        $con->rollback();
        throw $e;
      }

      $counter++;
    }

    return $counter;
  }

  /**
   * Migrate up to version $to.
   *
   * @param   integer $to
   * @return  integer Number of executed migrations
   */
  protected function migrateUp($to)
  {
    $con = Propel::getConnection();
    $counter = 0;

    $migrations = $this->getMigrationsToRunUpTo($to);
    foreach ($migrations as $version)
    {
      try
      {
        $con instanceof PropelPDO ? $con->beginTransaction() : $con->begin();

        $migration = $this->getMigrationObject($version);
        $migration->up();

        $this->markMigration($version, true);

        $con->commit();
      }
      catch (Exception $e)
      {
        $con->rollback();
        throw $e;
      }

      $counter++;
    }

    return $counter;
  }

  /**
   * Get the migration object for the given version.
   *
   * @param   integer $version
   *
   * @return  sfMigration
   */
  protected function getMigrationObject($version)
  {
    $file = $this->getMigrationFileName($version);

    // load the migration class
    require_once $file;
    $migrationClass = 'Migration'.$this->getMigrationNumberFromFile($file);

    return new $migrationClass($this, $version);
  }

  /**
   * Version to filename.
   *
   * @param   integer $version
   *
   * @return  string Filename
   */
  protected function getMigrationFileName($version)
  {
    return $this->migrations[$version];
  }

  /**
   * Load all migration file names.
   */
  protected function loadMigrations()
  {
    $migrations = sfFinder::type('file')->name('/^\d.*\.php$/')->maxdepth(0)->in($this->getMigrationsDir());

    $this->migrations = array();
    foreach ($migrations as $migration) {
        $number = (int)basename($migration);

        if (isset($this->migrations[$number])) {
            throw new DuplicateMigrationException('Migration ' . $migration . ' conflicts with ' . $this->migrations[$number]);
        }

        $this->migrations[$number] = $migration;
    }

    ksort($this->migrations);
  }
}
