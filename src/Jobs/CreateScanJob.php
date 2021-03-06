<?php

namespace Ntcm\Ntm\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ntcm\Enums\ScanEnum;
use Ntcm\Ntm\Model\Scan;
use Ntcm\Ntm\Ntm;

class CreateScanJob implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var integer
     */
    public $timeout;

    /**
     * Ranges to scan.
     *
     * @var string
     */
    public $ranges;

    /**
     * Enable or disable well-known port scan.
     *
     * @var boolean
     */
    public $scanPorts;

    /**
     * Enable or disable operating system scan.
     *
     * @var boolean
     */
    public $scanOs;

    /**
     * @var int
     */
    public $userId;

    /**
     * Create a new job instance.
     *
     * @param string | array $ranges
     * @param integer        $userId
     * @param boolean        $scanPorts
     * @param boolean        $scanOs
     * @param integer        $timeout
     */
    public function __construct($ranges, $userId, $scanPorts = true, $scanOs = true, $timeout = null)
    {
        // target ranges...
        if(is_array($ranges)) {
            $this->ranges = $ranges;
        } else {
            $this->ranges = explode(' ', str_replace(',', ' ', $ranges));
        }

        // finger print flags...
        $this->scanPorts = $scanPorts;
        $this->scanOs = $scanOs;

        // user id...
        $this->userId = $userId;

        // set the timeout...
        if(is_null($timeout)) {
            $this->timeout = config('ntm.scan.timeout');
        } else {
            $this->timeout = $timeout;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // do the scan here...
        $scan = Ntm::create()
                   ->setUserId($this->userId)
                   ->setTimeout($this->timeout)
                   ->setPortScan($this->scanPorts)
                   ->setOsDetection($this->scanOs)
                   ->scan($this->ranges)
                   ->parseOutputFile();
    }

    /**
     * The job failed to process.
     *
     * @param Exception $exception
     *
     * @return void
     */
    public function failed(Exception $exception)
    {
        Scan::last()->update(['status' => ScanEnum::FATAL]);
    }
}
