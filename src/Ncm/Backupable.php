<?php

namespace Ntcm\Ntm;

use Exception;
use Ntcm\Exceptions\ProcessExecutionFailedException;
use Ntcm\Ncm\Model\Backup;
use Ntcm\Ntm\Model\Host;
use Ntcm\Ntm\Util\ProcessExecutor;

trait Backupable {

    /**
     * File name for stored backup.
     *
     * @var string
     */
    protected $filename;

    /**
     * Restore host configuration.
     *
     * @throws ProcessExecutionFailedException
     */
    public function backup()
    {
        $executor = new ProcessExecutor();

        // run the command...
        $command = sprintf("%s %s %s %s \"%s\" \"%s\" %s",
            $this->getBackupExecutable(),
            $this->getBackupAddress(),
            $this->getBackupPort(),
            $this->getBackupUsername(),
            $this->getBackupPassword(),
            $this->getBackupSecondPassword(),
            $this->getDeviceType()
        );

        // run the backup command...
        $executor->execute($command, config('ncm.timeout'));

        // get output...
        $this->filename = $executor->getOutput();

        return $this;
    }

    /**
     * Save a new backup entity.
     *
     * @return Backup
     */
    public function store()
    {
        return $this->saveBackup($this->filename);
    }

    /**
     * @return string
     */
    protected function getBackupExecutable()
    {
        return "python" . config("ntm.python.version") . " " . remote_config_script_path("backup.py");
    }

    /**
     * @return Host
     */
    private function getHost()
    {
        return $this;
    }

    /**
     * @return string
     */
    protected function getBackupAddress()
    {
        return $this->getHost()->ip;
    }

    /**
     * @return string
     */
    protected function getBackupUsername()
    {
        return $this->getHost()->sshCredentials()->where('is_valid', true)->firstOrFail()->username;
    }

    /**
     * @return string
     */
    protected function getBackupPassword()
    {
        return $this->getHost()->sshCredentials()->where('is_valid', true)->firstOrFail()->password;
    }

    /**
     * @return string
     */
    protected function getBackupSecondPassword()
    {
        return $this->getHost()->sshCredentials()->where('is_valid', true)->firstOrFail()->second_password;
    }

    /**
     * @return integer
     */
    protected function getBackupPort()
    {
        return $this->getHost()->sshCredentials()->where('is_valid', true)->firstOrFail()->port;
    }

    /**
     * @return string
     */
    protected abstract function getDeviceType();

    /**
     * Create a new backup entity for the host.
     *
     * @param string $fileName
     *
     * @return Backup
     */
    protected function saveBackup($fileName)
    {
        // create the full file path...
        $filePath = tftp_path($fileName);

        // fetch the content...
        $content = file_get_contents($filePath);

        // remove the backup file...
        unlink_if_exists($filePath);

        // create new backup...
        return $this->getHost()->backups()->create([
            "configurations" => $content,
        ]);
    }
}