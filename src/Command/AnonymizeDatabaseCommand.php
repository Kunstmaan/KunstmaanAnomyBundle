<?php

namespace Kunstmaan\AnomyBundle\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Class AnonymizeDatabaseCommand
 *
 * @package Kunstmaan\AnomyBundle\Command
 */
class AnonymizeDatabaseCommand extends AbstractCommand
{
    /** @var string */
    private $backupDir;

    /**
     * AnonymizeDatabaseCommand constructor.
     *
     * @param string $backupDir
     */
    public function __construct($backupDir)
    {
        $this->backupDir = $backupDir;
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
    protected function getWelcomeText()
    {
        return 'Welcome to the anonymization for your database! It will be my pleasure to help you on your yourney.';
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute()
    {
       $this->importDatabase();
    }

    private function importDatabase()
    {
        die('lol');
        $this->createAnonymizedDatabase();

        if (!file_exists($this->backupDir.'/mysql.dmp')) {
            $this->logError('There is mysql.dmp file in the given backup directory');
        }

        $this->logTask('Importing backup in temp database');

        $this->executeSudoCommand(
            'mysql -u '.$this->mysqlConfig['user'].' -p'.$this->mysqlConfig['password'].' '.$this->databaseName.' < '.$this->backupDir.'/mysql.dmp > /dev/null 2>&1'
        );
    }
}
