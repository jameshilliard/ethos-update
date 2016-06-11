<?php

require_once __DIR__ . '/MinerProcess.php';


/**
 * GPU Monitor
 * @author xist
 */
class GPUMonitor
{
    /**
     * Max bytes to read of the miner log file when we first open it
     * @var int
     */
    const MINER_SEEK_OFFSET = 10000; // roughly 50 lines of MH/s logs plus extra stuff

    /**
     * Max number of MH/s lines to keep in the buffer at a time
     * This constitutes the size of the moving average.
     * @var int
     */
    const HASH_MOVING_AVERAGE_SIZE = 20;

    protected static $adapters = null;

    protected $fh_miner;
    protected $hashBuffer = '';

    protected $hashLog = array();
    protected $hashLogLen = 0;
    protected $hashLogSize = 0;

    /**
     * Total number of hashes in moving average
     * Meta data used to compute moving average of MH/s
     * @var int
     */
    protected $hashMetaHashes = 0;

    /**
     * Total time spent hashing (seconds) in moving average
     * Meta data used to compute moving average of MH/s
     * @var float
     */
    protected $hashMetaSeconds = 0.0;

    /**
     * @param bool $refresh If TRUE, probe the GPUs again to get the latest stats
     * @return array
     * @throws Exception if GPU stats cannot be probed
     */
    public static function getAdapters($refresh=false)
    {
        if (self::$adapters === null || $refresh) {
            // Haven't yet found out what GPUs are in this server, let's do it now.
            // We can cache this - GPUs don't change while the server is running.
            self::$adapters = self::parseAtiTweak();
        }
        return self::$adapters;
    }

    public function probe()
    {
        // Refresh adapters
        $adapters = self::getAdapters(true);

        $this->readMinerOutput();

        if (MinerProcess::isStuck()) {
            // When the miner is stuck, report 0 hashrate regardless of what the
            // moving average says.
            $MHps = 0;
        } else {
            // Miner is not stuck, report the moving average hashrate.
            if ($this->hashMetaSeconds != 0) {
                $hps = $this->hashMetaHashes / $this->hashMetaSeconds;
                $MHps = $hps / 1000000;
            } else {
                $MHps = 0;
            }
        }

        $result = array(
            'adapters' => $adapters,
            'MHps' => $MHps,
        );
        return $result;
    }

    public function readMinerOutput()
    {
        if ($this->openMinerOutputLog()) {
            $this->scanNewMinerOutputData();
        }
    }

    protected function scanNewMinerOutputData()
    {
        // Find out if more data has been written to the log
        $pos = ftell($this->fh_miner);
        fseek($this->fh_miner, 0, SEEK_END);
        $endPos = ftell($this->fh_miner);

        // Find out if the file has been rotated, e.g. recreated, in which case
        // we need to open it again
        if ($endPos < $this->hashLogSize) {
            // the new size of the file is smaller than the position we were reading from!
            // This means the file has been rotated, we need to close it and re-open.

            fclose($this->fh_miner);
            $this->fh_miner = null;
            return;
        }

        if ($pos == $endPos) {
            // Nothing new was written
            return;
        }

        // Remember the new size of the log
        $this->hashLogSize = $endPos;

        // There is more data in the log
        fseek($this->fh_miner, $pos, SEEK_SET);

        while (! feof($this->fh_miner)) {

            // Read more data.  fgets() will get only 1 line,
            // which should end with a '\n' if it is a full line.
            $data = fgets($this->fh_miner, 32768);
            if ($data === false) {
                $data = '';
            }

            $data = $this->hashBuffer . $data;

            // If $data doesn't end with a newline, it is a partial
            // line, add it back to the buffer for next time
            if (substr($data, -1) !== "\n") {
                $this->hashBuffer = $data;
                return;
            } else {
                // We read a full line, so zero out the hashBuffer,
                // there is nothing to preserve for the next call.
                $this->hashBuffer = '';
            }

            // We're looking for lines that look like the following.
            // Anything else is stuff we don't care about.
            //
            // miner  22:35:01.846|ethminer  Mining on PoWhash #a10cdc41… : 125203104 H/s = 25165824 hashes / 0.201 s
            //
            // Note that terminal colors are being logged as well, so really
            // the log looks something like this as binary:
            // ESC[32mminer  ESC[35m22:37:15.954ESC[0mESC[30m|ESC[34methminerESC[0m  Mining on PoWhash ESC[96m#a26785e5…ESC[0m : 119986308 H/s = 24117248 hashes / 0.201 s

            if (preg_match('/miner  .*\d\d:\d\d:\d\d\.\d\d\d.*ethminer.*  Mining on PoWhash .*#[a-z0-9]+.* : \d+ H\/s = (\d+) hashes \/ (\d+)\.(\d+) s/i', $data, $match)) {
                $hashes = $match[1];
                $seconds = $match[2];
                $millisec = $match[3];

                $this->addMinerLogData($hashes, $seconds, $millisec);
            }
        }
    }

    protected function addMinerLogData($hashes, $seconds, $millisec)
    {
        $log = array($hashes, $seconds, $millisec);

        array_push($this->hashLog, $log);
        $this->hashLogLen++;

        // If we have too much data, discard old data
        if ($this->hashLogLen > self::HASH_MOVING_AVERAGE_SIZE) {

            array_shift($this->hashLog);
            $this->hashLogLen--;
        }

        // Recompute the moving average meta data

        $this->hashMetaHashes = 0;
        $this->hashMetaSeconds = 0;

        for ($i=0; $i<$this->hashLogLen; $i++) {
            $log = $this->hashLog[$i];
            $this->hashMetaHashes += $log[0];
            $this->hashMetaSeconds += ($log[2] / 1000) + $log[1];
        }
    }

    /**
     * @return resource|false
     */
    protected function openMinerOutputLog()
    {
        if (! $this->fh_miner) {

            $file = MinerProcess::MINER_LOG_FILE;
            $this->fh_miner = fopen($file, "r");
            if ($this->fh_miner) {
                // Seek to near the end of the file so we will just read in the
                // last lines rather than the entire file.
                fseek($this->fh_miner, 0, SEEK_END);
                $pos = ftell($this->fh_miner);
                if ($pos > self::MINER_SEEK_OFFSET) {
                    // file size is greater than the size we wish to read,
                    // so back up the maximum amount we wish to read
                    fseek($this->fh_miner, $pos-self::MINER_SEEK_OFFSET, SEEK_SET);
                } else {
                    // file size is smaller than the size we wish to read,
                    // so read in the entire file
                    fseek($this->fh_miner, 0, SEEK_SET);
                }
            } else {
                fwrite(STDERR, "WARNING: Failed to open miner output file: $file\n");
            }
        }

        // Could return false if file open failed
        return $this->fh_miner;
    }

    public static function parseAtiTweak()
    {
        $adapters = array();

        $cmd = 'timeout 10 atitweak -s';
        exec($cmd, $output, $r);
        if ($r !== 0) {
            throw new Exception("Command failed (exit code $r): $cmd");
        }

        try {

            // Parse output, which has 5 lines like the following for each GPU:

            // 0. AMD Radeon (TM) R9 380 Series  (:0.0)
            //     engine clock 1009.15MHz, memory clock 1450MHz, core voltage 0VDC, performance level 0, utilization 100%
            //     fan speed 50% (1213 RPM) (default)
            //     temperature 66 C
            //     powertune 20%

            for (; count($output) >= 5; $output = array_slice($output, 5)) {

                $a = array();

                if (! preg_match('/^(\d+)\.\s+(.*)\(([:\d\.]+)\)\s*$/', $output[0], $match)) {
                    throw new Exception("Expected GPU definition on line 1");
                }
                $a['adapterId'] = intval($match[1]);
                $a['cardName'] = trim($match[2]);
                $a['display'] = $match[3];

                if (! preg_match('/^\s+engine clock ([\d\.]+)MHz, memory clock ([\d\.]+)MHz, core voltage ([\d\.]+)VDC, performance level (\d+), utilization ([\d\.]+)%/', $output[1], $match)) {
                    throw new Exception("Expected GPU stats on line 2");
                }
                $a['clock'] = intval($match[1]);
                $a['memClock'] = intval($match[2]);
                $a['volts'] = intval($match[3]);
                $a['perfLevel'] = intval($match[4]);
                $a['usage'] = intval($match[5]);

                if (! preg_match('/^\s+fan speed ([\d\.]+)% \((\d+) RPM\)/', $output[2], $match)) {
                    throw new Exception("Expected fan speed on line 3");
                }
                $a['fanPercent'] = intval($match[1]);
                $a['fanRPM'] = intval($match[2]);

                if (! preg_match('/^\s+temperature ([\d\.]+) C/', $output[3], $match)) {
                    throw new Exception("Expected temperature on line 4");
                }
                $a['tempC'] = intval($match[1]);

                if (! preg_match('/^\s+powertune ([\d\.]+)%/', $output[4], $match)) {
                    throw new Exception("Expected powertune on line 5");
                }
                $a['powertune'] = intval($match[1]);

                // All the info looks valid, remember this adapter info
                array_push($adapters, $a);
            }

            // If we didn't read all the output, that is a problem.
            // It means atitweak didn't give us back what we expected.
            if (count($output) != 0) {
                throw new Exception("Unknown footer info");
            }
        }
        catch (Exception $e) {
            $msg = $e->getMessage();
            throw new Exception("Unexpected output from atitweak: $msg near:\n".implode("\n", $output));
        }

        return $adapters;
    }
}
