<?php

namespace Ntcm\Ntm;

use Carbon\Carbon;
use Exception;
use Ntcm\Enums\HostStateEnum;
use Ntcm\Enums\HostTypeEnum;
use Ntcm\Enums\ScanEnum;
use Ntcm\Exceptions\ScanNotFoundException;
use Ntcm\Ntm\Model\Address;
use Ntcm\Ntm\Model\Hop;
use Ntcm\Ntm\Model\Host;
use Ntcm\Ntm\Model\Hostname;
use Ntcm\Ntm\Model\OsDetected;
use Ntcm\Ntm\Model\OsGeneric;
use Ntcm\Ntm\Model\Port;
use Ntcm\Ntm\Model\Scan;
use Ntcm\Ntm\Util\ProcessExecutor;

/**
 * @author Soroush Kazemi <kazemi.soroush@gmail.com>
 */
class Ntm {

    use NtmParameters, ProcessParameters;

    /**
     * This class is responsible for executing a single command process.
     *
     * @var ProcessExecutor
     */
    protected $executor;

    /**
     * Unique scan code.
     *
     * @var string
     */
    protected $scanCode;

    /**
     * @var array
     */
    protected $ports;

    /**
     * @return Ntm
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Create instance of class
     */
    public function __construct()
    {
        // set default timeout...
        $this->timeout = config('ntm.scan.timeout');

        // get last scan id...
        try {
            $this->setScanCode(intval(Scan::last()->id));
        } catch(Exception $e) {
            $this->setScanCode(0);
        }

        $this->executor = new ProcessExecutor();
    }

    /**
     * Starts new scan with input ranges and ports.
     *
     * @param array | string $ranges
     * @param array          $ports
     *
     * @return $this
     */
    public function scan($ranges, array $ports = [])
    {
        $this->ports = $ports;

        // for non-array ranges...
        if(is_string($ranges)) {
            $ranges = [$ranges];
        }

        // create a new scan...
        $scan = Scan::create([
            'id'      => $this->getScanCode() + 1,
            'user_id' => $this->getUserId(),
            'start'   => Carbon::now()->timestamp,
            'ranges'  => implode(' ', $ranges),
            'ports'   => $this->portScan,
            'os'      => $this->osDetection,
        ]);

        // set environment variables from .env file...
        $this->setEnvironmentVariables();

        // add scanner ip to ranges...
        $ranges[] = get_scanner_address();

        // make ranges ready for shell execution...
        $ranges = implode(' ', scape_shell_array($ranges));

        // update the current scan code...
        $this->setScanCode($scan->id);

        // check if scan directory exists...
        if( ! file_exists($this->getOutputDirectory())) {
            mkdir($this->getOutputDirectory());
        }

        // build the scan command...
        $command = sprintf('%s %s %s %s',
            $this->getExecutable(),
            implode(' ',
                scape_shell_array($this->getParameters())
            ),
            scape_shell($this->getOutputFile()),
            $ranges
        );

        log_info($command);

        try {
            // run the scan...
            $this->executor->execute($command, $this->getTimeout());
        } catch(Exception $e) {
            // scan is failed...
            $scan->update([
                'state' => ScanEnum::FATAL
            ]);
        }

        // continue chaining...
        return $this;
    }

    /**
     * Parses the latest scanned and persists it into the database.
     *
     * @return Scan
     * @throws ScanNotFoundException when no scan found.
     */
    public function parseOutputFile()
    {
        // check if any scan found...
        if( ! $scan = Scan::find($this->getScanCode())) {
            throw new ScanNotFoundException();
        }

        // change scan state to storing...
        $scan->update([
            'state' => ScanEnum::STORING
        ]);

        try {
            // parse xml file into xml object variable...
            $xml = simplexml_load_file(
                $this->getOutputFile()
            );
            foreach($xml->host ? : [] as $xmlHost) {
                $mainAddress = (string)array_first($xmlHost->address)->attributes()->addr;

                // if there were no trace hops then do not store the host...
                if (empty($xmlHost->trace) && ! in_range($mainAddress, get_range(env('SCANNER_ADDRESS')))) {
                    continue;
                }

                // parse and persist hosts...
                $host = Host::findOrCreate([
                    'address' => $mainAddress,
                    'state'   => (string)$xmlHost->status->attributes()->state == "up" ? HostStateEnum::STATE_UP : HostStateEnum::STATE_DOWN,
                    'start'   => (integer)$xmlHost->attributes()->starttime,
                    'end'     => (integer)$xmlHost->attributes()->endtime,
                    //'scan_id' => $scan->id
                ]);

                $scan->attachHost($host->id);

                // parse and persist addresses...
                foreach($xmlHost->address ? : [] as $xmlAddress) {
                    Address::findOrCreate([
                        'address' => (string)$xmlAddress->attributes()->addr,
                        'type'    => (string)$xmlAddress->attributes()->addrtype,
                        'vendor'  => (string)$xmlAddress->attributes()->vendor,
                        'host_id' => $host->id,
                    ]);
                }

                // parse and persist host names...
                foreach($xmlHost->hostnames->hostname ? : [] as $xmlHostname) {
                    Hostname::findOrCreate([
                        'name'    => (string)$xmlHostname->attributes()->name,
                        'type'    => (string)$xmlHostname->attributes()->type,
                        'host_id' => $host->id,
                    ]);
                }

                // remove deprecated os information...
                // $host->osCollection()->delete();
                OsDetected::where('host_id', $host->id)->delete();

                $index = 0;

                // parse and persist ports...
                foreach($xmlHost->os->osmatch ? : [] as $xmlOs) {
                    if($index == 0 && strtolower((string)$xmlOs->osclass->attributes()->type) == "router") {
                        $host->update(['type' => HostTypeEnum::ROUTER_HOST]);
                    }

                    // detected operating system family...
                    $osFamily = (string)$xmlOs->osclass->attributes()->osfamily;

                    $os = OsDetected::create([
                        //'address'   => $mainAddress,
                        'name'      => (string)$xmlOs->attributes()->name,
                        'type'      => (string)$xmlOs->osclass->attributes()->type,
                        'vendor'    => (string)$xmlOs->osclass->attributes()->vendor,
                        'os_family' => $osFamily,
                        'os_gen'    => (string)$xmlOs->osclass->attributes()->osgen,
                        'accuracy'  => (float)$xmlOs->osclass->attributes()->accuracy,
                        'host_id'   => $host->id,
                    ]);

                    $generic = OsGeneric::where('family', 'like', "%" . strtolower($osFamily) . "%")->first();

                    if( ! $host->os_generic_id && $generic) {
                        $host->update([
                            'os_generic_id' => $generic->id
                        ]);
                    }

                    $index ++;
                }

                // remove deprecated ports information...
                // $host->ports()->delete();
                Port::where('host_id', $host->id)->delete();

                // parse and persist ports...
                foreach($xmlHost->ports->port ? : [] as $xmlPort) {
                    try {
                        if(strtolower((string)$xmlPort->service->attributes()->devicetype) == "switch") {
                            $host->update(['type' => HostTypeEnum::SWITCH_HOST]);
                        }

                        Port::create([
                            //'address'    => $mainAddress,
                            'protocol'   => (string)$xmlPort->attributes()->protocol,
                            'port_id'    => (integer)$xmlPort->attributes()->portid,
                            'state'      => (string)$xmlPort->state->attributes()->state,
                            'reason'     => (string)$xmlPort->state->attributes()->reason,
                            'service'    => (string)$xmlPort->service->attributes()->name,
                            'method'     => (string)$xmlPort->service->attributes()->method,
                            'conf'       => (string)$xmlPort->service->attributes()->conf,
                            'product'    => (string)$xmlPort->service->attributes()->product ? : null,
                            'version'    => (string)$xmlPort->service->attributes()->version ? : null,
                            'extra_info' => (string)$xmlPort->service->attributes()->extrainfo ? : null,
                            'host_id'    => $host->id,
                        ]);
                    } catch(Exception $e) {
                        // ...
                    }
                }

                // initiate the first address...
                $firstAddress = get_scanner_address();
                $index = 0;

                // parse and persist hops...
                foreach($xmlHost->trace->hop ? : [] as $xmlHop) {
                    $secondAddress = (string)$xmlHop->attributes()->ipaddr;

                    // determine type of hosts...
                    $firstType = HostTypeEnum::ROUTER_HOST;
                    $secondType = HostTypeEnum::ROUTER_HOST;
                    if($index == 0) {
                        $firstType = HostTypeEnum::NODE_HOST;
                    }
                    if(sizeof($xmlHost->trace->hop) == $index + 1) {
                        $secondType = HostTypeEnum::NODE_HOST;
                    }

                    // find or create hosts...
                    $first = Host::findOrCreate([
                        'address' => $firstAddress,
                        //'scan_id' => $scan->id,
                        'type'    => $firstType,
                    ]);
                    $second = Host::findOrCreate([
                        'address' => $secondAddress,
                        //'scan_id' => $scan->id,
                        'type'    => $secondType,
                    ]);

                    $scan->attachHost($first->id);
                    $scan->attachHost($second->id);

                    // first or last hop...
                    if($index == 0 || $index == sizeof($xmlHost->trace->hop) - 1) {

                        // make a switch...
                        $switch = Host::findOrCreate([
                            'state'   => HostStateEnum::STATE_UP,
                            'address' => subnet_address(get_range($secondAddress)),
                            'type'    => HostTypeEnum::SWITCH_HOST,
                            //'scan_id' => $scan->id,
                        ]);

                        $scan->attachHost($switch->id);

                        // connect first host to switch...
                        Hop::findOrCreate([
                            'address_first'  => $first->id,
                            'address_second' => $switch->id,
                            'scan_id'        => $scan->id,
                        ]);

                        // move on to the switch...
                        $first = $switch;
                    }

                    // find or create hop...
                    Hop::findOrCreate([
                        'address_first'  => $first->id,
                        'address_second' => $second->id,
                        'scan_id'        => $scan->id,
                        'rtt'            => (float)$xmlHop->rtt,
                    ]);

                    // shift addresses...
                    $firstAddress = $secondAddress;

                    $index ++;
                }
            }

            // update scan info...
            $scan->update([
                'total_discovered' => $xml->runstats->hosts->attributes()->up,
                // 'start'            => $xml->attributes()->start,
                'end'              => Carbon::now()->timestamp,
                'state'            => ScanEnum::DONE
            ]);
        } catch(Exception $e) {
            log_info($e);

            $scan->update([
                'state' => ScanEnum::FATAL_STORING,
                'end'   => Carbon::now()->timestamp
            ]);
        }

        return $scan;
    }

    /**
     * @return integer
     */
    public function getScanCode()
    {
        return $this->scanCode;
    }

    /**
     * @param integer $scanCode
     */
    public function setScanCode($scanCode)
    {
        $this->scanCode = $scanCode;
    }

    /**
     * @return void
     */
    private function setEnvironmentVariables()
    {
        // command execution timeout...
        if($timeout = config('ntm.scan.timeout')) {
            $this->setTimeout($timeout);
            // $this->setScriptTimeout($timeout);
        }
        if($hostTimeout = config('ntm.scan.host_timeout')) {
            $this->setHostTimeout($hostTimeout);
        }
        if($osDetection = config('ntm.scan.os_detection')) {
            $this->setosDetection($osDetection);
        }
        if($serviceInfo = config('ntm.scan.service_info')) {
            $this->setserviceInfo($serviceInfo);
        }
        if($verbose = config('ntm.scan.verbose')) {
            $this->setverbose($verbose);
        }
        if($treatHostsAsOnline = config('ntm.scan.treat_hosts_as_online')) {
            $this->settreatHostsAsOnline($treatHostsAsOnline);
        }
        if($portScan = config('ntm.scan.port_scan')) {
            $this->setportScan($portScan);
        }
        if($reverseDns = config('ntm.scan.reverse_dns')) {
            $this->setreverseDns($reverseDns);
        }
        if($traceroute = config('ntm.scan.traceroute')) {
            $this->settraceroute($traceroute);
        }

    }

}
