<?php
declare(strict_types=1);

namespace Onlineconf;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FileReader implements ReaderInterface
{
    private string $moduleFilename;
    private bool $isChecked;
    private bool $isCLI;

    private $shmHandler = null;

    private int $shmKey;
    private int $shmVarKey;
    /**
     * @var resource
     */
    private $dbHandler;
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface|null $logger
     * @param string               $moduleFilename
     */
    public function __construct(?LoggerInterface $logger = null, string $moduleFilename = '/opt/onlineconf/TREE.cdb')
    {
        $this->moduleFilename = $moduleFilename;
        $this->logger = $logger ?? new NullLogger;
        $this->isCLI = PHP_SAPI === 'cli';
        $this->isChecked = false;
        $this->shmKey = $this->isShmAvailable() ? ftok(__FILE__, 'O') : 1;
        $this->shmVarKey = getmypid();

        $this->boot();
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, ?string $default = null)
    {
        if (!$this->dbHandler) {
            $this->logger->warning('No db handler');
            return $default;
        }

        $value = dba_fetch($key, $this->dbHandler);

        if ($value === false) {
            return $default;
        }

        return $this->parseCdbValue($value);
    }

    /**
     * @inheritdoc
     */
    public function getList(string $branch = ''): array
    {
        $results = [];

        $dbKey = dba_firstkey($this->dbHandler);

        $keyOffset = strlen($branch);

        while ($dbKey !== false) {
            if ($branch === '' || $this->isStrStartsWith($dbKey, $branch)) {
                $dbValue = dba_fetch($dbKey, $this->dbHandler);

                if ($dbValue !== false) {
                    $key = ($keyOffset > 0) ? substr($dbKey, $keyOffset) : $dbKey;
                    $results[$key] = $this->parseCdbValue($dbValue);
                }
            }

            $dbKey = dba_nextkey($this->dbHandler);
        }

        return $results;
    }

    private function boot(): void
    {
        if (!file_exists($this->moduleFilename)) {
            $this->logger->debug('Boot failed:' .$this->moduleFilename.' not found');
            return;
        }

        if (!function_exists('dba_popen')) {
            $this->logger->debug('Boot failed: dba php extension is not installed!');
            return;
        }

        $this->dbHandler = dba_popen($this->moduleFilename, 'r', 'cdb');

        if ($this->isDbStateActual()) {
            $this->isChecked = true;
            return;
        }

        dba_close($this->dbHandler);

        if ($this->shmHandler) {
            $this->updateCheckTime();
        }
        $this->dbHandler = dba_popen($this->moduleFilename, 'r', 'cdb');
        $this->isChecked = true;
    }

    /**
     * @return void
     */
    private function updateCheckTime(): void
    {
        //spams to error log
        $res = @shm_put_var($this->shmHandler, $this->shmVarKey, time());
        if (!$res) {
            $this->logger->debug('Renew SHM, probably full');
            shm_remove($this->shmHandler);

            $this->shmHandler = shm_attach($this->shmKey) ?: null;
            if (!$this->shmHandler) {
                $this->logger->error("SHM can't attach");
                return;
            }
            shm_put_var($this->shmHandler, $this->shmVarKey, time());
        }
    }

    /**
     * @return bool
     */
    private function isDbStateActual(): bool
    {
        if ($this->isChecked || !$this->isShmAvailable()) {
            return true;
        }

        $this->shmHandler = shm_attach($this->shmKey) ?: null;
        if (!$this->shmHandler) {
            $this->logger->error("SHM can't attach");
            return false;
        }
        if (!shm_has_var($this->shmHandler, $this->shmVarKey)) {
            return false;
        }

        if (!$last_updated = shm_get_var($this->shmHandler, $this->shmVarKey)) {
            return false;
        }

        clearstatcache(true, $this->moduleFilename);
        if (!$stat = stat($this->moduleFilename)) {
            $this->logger->warning('Module file stat error. Does it exist?');
            return true;
        }

        if ($stat['mtime'] > $last_updated) {
            $this->logger->debug('Module changed and need to reload');
            return false;
        }
        // module not changed
        return true;
    }

    /**
     * @return bool
     */
    private function isShmAvailable(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' && !extension_loaded('sysvshm')) {
            return false;
        }

        return !$this->isCLI;
    }

    /**
     * @param string $val
     * @return false|mixed|string
     */
    private function parseCdbValue(string $val)
    {
        if ('s' === $val[0]) {
            return substr($val, 1);
        }

        if ('j' === $val[0]) {
            return json_decode(substr($val, 1), true);
        }

        return $val;
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function isStrStartsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
