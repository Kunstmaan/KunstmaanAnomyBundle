<?php

namespace Kunstmaan\AnomyBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Command\Command;
use Inet\Neuralyzer\Configuration\Reader;

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

    /** @var Connection */
    private $conn;

    /** @var array */
    private $connParams;

    /** @var Reader */
    private $reader;

    /**
     * AnonymizeDatabaseCommand constructor.
     *
     * @param string     $backupDir
     * @param Connection $conn
     */
    public function __construct($backupDir, Connection $conn)
    {
        parent::__construct(null);

        $this->backupDir = $backupDir;
        $this->conn = $conn;

        $this->connParams = $params = $this->conn->getParams();
        unset($params['dbname'], $params['path'], $params['url']);
        $this->conn = DriverManager::getConnection($params);
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

        $this->importDatabase();
        $this->runPreAnonymizeQueries();

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

        $this->executeSudoCommand(
            sprintf(
                'mysql -u %s -p%s %s < %s > /dev/null 2>&1',
                $this->connParams['user'],
                $this->connParams['password'],
                $this->connParams['dbname'],
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

        $this->conn->connect();

        $databaseExists = in_array(self::DATABASE_NAME, $this->conn->getSchemaManager()->listDatabases());

        if ($databaseExists) {
            $this->logNotice(sprintf('Database %s exists!', self::DATABASE_NAME));
        } else {
            $this->conn->getSchemaManager()->createDatabase(self::DATABASE_NAME);
        }

        $this->conn->close();
    }

    /**
     * @throws \AppBundle\Exceptions\AnomyException
     */
    private function runPreAnonymizeQueries()
    {
        $this->logTask('Executing pre-anonymize-queries');

        $this->conn->connect();

        // Execute queries before anonymization
        if (!empty($this->reader->getPreQueries())) {
            foreach ($this->reader->getPreQueries() as $preQuery) {
                try {
                    $this->logNotice('Executing pre-query: '.$preQuery);

                    $pdo->query($preQuery);
                } catch (\Exception $e) {
                    $this->logError($e->getMessage());
                }
            }
        }

        $this->conn->close();
    }
}
