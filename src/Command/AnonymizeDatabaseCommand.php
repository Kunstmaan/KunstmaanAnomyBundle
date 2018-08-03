<?php

namespace Kunstmaan\AnomyBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Inet\Neuralyzer\Anonymizer\DB;
use Inet\Neuralyzer\Configuration\Reader;
use Symfony\Component\Console\Command\Command;

/**
 * Class AnonymizeDatabaseCommand
 *
 * @package Kunstmaan\AnomyBundle\Command
 */
class AnonymizeDatabaseCommand extends AbstractCommand
{
    const CONNECTION_NAME = 'anomy';

    const DATABASE_NAME = 'anomy';

    /** @var string */
    private $backupDir;

    /** @var string */
    private $configFile;

    /** @var Connection */
    private $conn;

    /** @var Connection */
    private $rootConn;

    /** @var Reader */
    private $reader;

    /**
     * AnonymizeDatabaseCommand constructor.
     *
     * @param string     $backupDir
     * @param string     $configFile
     * @param Connection $conn
     */
    public function __construct($backupDir, $configFile, Connection $conn)
    {
        parent::__construct(null);

        $this->backupDir = $backupDir;
        $this->configFile = $configFile;
        $this->conn = $conn;

        $params = $this->conn->getParams();
        unset($params['dbname'], $params['path'], $params['url']);
        $this->rootConn = DriverManager::getConnection($params);
    }

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('kuma:anonymize:database')
            ->setDescription('Import database in temp database and run anonymization.')
            ->setHelp(
                <<<EOT
The <info>kuma:anonymize:database</info> command will import the backup in a temp database.

- Run queries after importing
- Decerypt database
- Run anonymizer with:
    - Execution of pre queries
    - Anonymization
    - Execution of post queries
 - Dump database to file
 - Remove temporary database

<info>php anomy.phat import-db will /home/projects/will/data/will /home/projects/will/backup</info>
EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute()
    {
        $this->logNotice('Welcome to the anonymization for your database! It will be my pleasure to help you on your yourney.');

        // Anon READER
        if (!file_exists($this->configFile)) {
            $this->logError('There is no anon.yml file in your .skylab directory');
        } else {
            $this->reader = new Reader($this->configFile);;
        }

        $this->importDatabase();
        $this->runPreAnonymizeQueries();
        $this->runAnonymization();
        $this->runPostAnonymizeQueries();
        $this->exportDatabase();
        $this->dropDatabase();

        $this->logTask('<info> I\'m done </info>');
        if ($this->progress !== null) {
            $this->progress->finish();
        }
        $this->clearLine();
    }

    private function importDatabase()
    {
        $this->createAnonymizedDatabase();

        if (!file_exists($this->backupDir.'/mysql.dmp')) {
            $this->logError('There is no mysql.dmp file in the given backup directory');
        }

        $this->logTask('Importing backup in temp database');

        $params = $this->conn->getParams();

        $this->executeSudoCommand(
            sprintf(
                'mysql -u %s -p%s %s < %s > /dev/null 2>&1',
                $params['user'],
                $params['password'],
                $params['dbname'],
                $this->backupDir.'/mysql.dmp'
            )
        );
    }

    /**
     * Creating a temporary database to run the anonymization on.
     */
    private function createAnonymizedDatabase()
    {
        $this->logTask('Creating new database to anonymize');

        $databaseExists = in_array(self::DATABASE_NAME, $this->rootConn->getSchemaManager()->listDatabases());

        if ($databaseExists) {
            $this->logNotice(sprintf('Database %s exists!', self::DATABASE_NAME));
        } else {
            $this->rootConn->getSchemaManager()->createDatabase(self::DATABASE_NAME);
        }
    }

    /**
     * @throws \AppBundle\Exceptions\AnomyException
     */
    private function runPreAnonymizeQueries()
    {
        $this->logTask('Executing pre-anonymize-queries');

        // Execute queries before anonymization
        if (!empty($this->reader->getPreQueries())) {
            foreach ($this->reader->getPreQueries() as $preQuery) {
                try {
                    $this->logNotice('Executing pre-query: '.$preQuery);

                    $this->conn->query($preQuery);
                } catch (\Exception $e) {
                    $this->logError($e->getMessage());
                }
            }
        }
    }

    /**
     * @throws \AppBundle\Exceptions\AnomyException
     */
    private function runAnonymization()
    {
        // Now work on the DB
        $anon = new DB($this->conn->getWrappedConnection());
        $anon->setConfiguration($this->reader);

        // Get tables
        $tables = $this->reader->getEntities();

        foreach ($tables as $table) {
            try {
                $result = $this->conn->query("SELECT COUNT(1) FROM $table");
            } catch (\Exception $e) {
                $this->logError("Could not count records in table '$table' defined in your config");
            }

            $data = $result->fetchAll(\PDO::FETCH_COLUMN);
            $total = (int) $data[0];
            if ($total === 0) {
                $this->logNotice("<info>$table is empty</info>");
                continue;
            }

            $this->logNotice("<info>Anonymizing $table</info>");
            $anon->processEntity($table, null, false);
        }
    }

    /**
     * @throws \AppBundle\Exceptions\AnomyException
     */
    private function runPostAnonymizeQueries()
    {
        $this->logTask('Executing post-anonymize-queries');

        // Execute queries after anonymization
        if (!empty($this->reader->getPostQueries())) {
            foreach ($this->reader->getPostQueries() as $preQuery) {
                try {
                    $this->logNotice('Executing post-query: '.$preQuery);

                    $this->conn->query($preQuery);
                } catch (\Exception $e) {
                    $this->logError($e->getMessage());
                }
            }
        }
    }

    private function exportDatabase()
    {
        $this->executeCommand('rm -f '.$this->backupDir.'/mysql_anonymized.dmp');
        $this->executeCommand('rm -f '.$this->backupDir.'/mysql_anonymized.dmp.gz');

        $this->executeCommand('touch '.$this->backupDir.'/mysql_anonymized.dmp');
        $this->executeCommand('chmod -R 755 '.$this->backupDir.'/mysql_anonymized.dmp');
        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "SET autocommit=0;\n", true);
        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "SET unique_checks=0;\n", true);
        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "SET foreign_key_checks=0;\n", true);

        $tmpfname = tempnam(sys_get_temp_dir(), 'anomy');

        $params = $this->conn->getParams();

        $this->executeCommand(
            'mysqldump --skip-opt --add-drop-table --add-locks --create-options --disable-keys --single-transaction --skip-extended-insert --quick --set-charset -u '.$params['user'].' -p'.$params['password'].' '.$params['dbname'].' -r '.$tmpfname.' > /dev/null 2>&1'
        );

        $this->executeCommand('cat '.$tmpfname.' | sudo tee -a '.$this->backupDir.'/mysql_anonymized.dmp > /dev/null');
        $this->executeCommand('rm -f '.$tmpfname);

        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "COMMIT;\n", true);
        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "SET autocommit=1;\n", true);
        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "SET unique_checks=1;\n", true);
        $this->writeProtectedFile($this->backupDir.'/mysql_anonymized.dmp', "SET foreign_key_checks=1;\n", true);
        $this->executeCommand(
            'gzip -f < '.$this->backupDir.'/mysql_anonymized.dmp > '.$this->backupDir.'/mysql_anonymized.dmp.gz'
        );
        $this->executeCommand('rm -f '.$this->backupDir.'/mysql_anonymized.dmp');
    }

    private function dropDatabase()
    {
        $this->conn->exec($this->logQuery('drop database if exists '.$this->conn->getParams()['dbname']));
    }
}
