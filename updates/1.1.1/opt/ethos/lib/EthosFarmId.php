<?php

require_once __DIR__ . '/EthosConf.php';


/**
 * Ethos Farm ID
 *
 * Calculate the private and public ID of the current farm.  This determines
 * where the stats are stored on the API server.
 *
 * @author xist
 */
class EthosFarmId
{
    /**
     * API server mandates that all private ids must be 12 characters long
     */
    const PRIVATE_ID_LENGTH = 12;

    /**
     * API server mandates that all public ids must be 6 characters long.
     */
    const PUBLIC_ID_LENGTH = 6;  // must be less than PRIVATE_ID_LENGTH

    /**
     * The private ID of the current farm
     * This is exactly PRIVATE_ID_LENGTH characters long.
     * @var string
     */
    protected $privateId;

    /**
     * The public ID of the current farm
     * This is the first PUBLIC_ID_LENGTH characters of $privateId
     * @var string
     */
    protected $publicId;

    /**
     * Get the private ID of this farm
     * Before you call this you must have called $this->generateId()
     * @return string
     */
    public function getPrivateId() { return $this->privateId; }

    /**
     * Get the public ID of this farm
     * Before you call this you must have called $this->generateId()
     * @return string
     */
    public function getPublicId() { return $this->publicId; }

    /**
     * Generate a new private ID
     *
     * You should call this at least once.
     *
     * If you intend for the ID to be able to change during execution,
     * call this each time you think it might have changed.
     * @return void
     */
    public function generateId()
    {
        $this->privateId = $this->generatePrivateId();
        $this->publicId = substr($this->privateId, 0, self::PUBLIC_ID_LENGTH);
    }

    /**
     * Generate a random private ID based loosely on the public IP address
     * @return string
     */
    protected function generatePrivateIdByIp()
    {
        $ip = trim(file_get_contents("https://api.ipify.org"));
        $hash = substr(hash("sha256",$ip),0,self::PRIVATE_ID_LENGTH);
        return $hash;
    }

    /**
     * Allow the user to specify a private ID
     * @return string
     */
    protected function generatePrivateId()
    {
        $id = EthosConf::get('custompanel');
        if ($id !== '') {

            try {

                if (strlen($id) !== self::PRIVATE_ID_LENGTH) {
                    throw new Exception("Invalid custompanel length: ".strlen($id));
                }

                // must be alphanumeric
                if (preg_match("/[^a-z0-9]/i", $id)) {
                    throw new Exception("Invalid format of custompanel, must be alphanumeric");
                }

                return $id;
            }
            catch(Exception $e) {
                // TODO- print a warning message in the log
                fwrite(STDERR, "Error in custom ID: ".$e->getMessage()."\n");
            }
        }

        // No suitable custom ID was found, use the default
        return $this->generatePrivateIdByIp();
    }
}
