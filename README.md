# Network Topology Mapper

Network topology mapper with Laravel 5. This package runs an Nmap scan on input hosts and networks, parses and populates the database with extracted values. First you must add service provider into your `app.php` config file.
```php
'providers' => [
    ...
    Ntcm\Ntm\NtmServiceProvider::class
    ...
]
```

Then you must publish config files. This will copy `ntm.php` config file inside your config directory.
```
$ php artisan vendor:publish
```

Before doing the migrations, you can customize your table names inside `ntm.php` file in your config directory. With no config file and by default all tables are prefixed with `mapper_`. After that do the database migration.
```
$ php artisan migrate
```

Now you can run a scan like so:
```php
use Ntcm\Ntm\Ntm;

$targets = 'scanme.nmap.org';

Ntm::create()
   ->setTimeout(60) // in seconds...
   ->scan($targets)
   ->parseOutputFile();
```

This will scan the host inside a `1.xml` file and store the parsed information into the database with `scan_id = 1`. You can also input an array of targets like so: 
```php
$targets = ['scanme.nmap.org', '192.168.101.0/24'];

Ntm::create()
   ->setTimeout(60)
   ->scan($targets)
   ->parseOutputFile();
```

Besides you can enable / disable different scan attributes. These are the default values for the attributes.
```php
$targets = ['scanme.nmap.org', '192.168.101.0/24'];

Ntm::create()
   ->setTimeout(60)
   ->scan($targets)
   ->setTraceroute(true)
   ->setReverseDns(true)
   ->setPortScan(true)
   ->setOsDetection(true)
   ->setServiceInfo(true)
   ->setVerbose(false)
   ->setTreatHostsAsOnline(true)
   ->parseOutputFile();
```

## Scan Command

You can run a scan using `ScanCommand` class like so:

```php
$targets = ['scanme.nmap.org', '192.168.101.0/24'];

// call scan artisan command...
Artisan::call('scan', [
  'targets'     => $targets,
  '--os'        => true, // to enable operating system scan...
  '--ports'     => true, // to enable well-known TCP ports...
  '--scheduled' => '0 1,13 * * * *' // you can use this to schedule the scan...
]);

```

## Scheduled Scan

You can schedule your scans by adding this code to `schedule` method of your `App\Console\Kernel` class:


```php
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        foreach(Target::scheduled()->get() as $target) {
            $schedule
                ->command('scan', [
                    'ranges'  => $target->range,
                    '--os'    => $target->ports,
                    '--ports' => $target->os,
                ])
                ->cron($target->scheduled);
        }
    }
```
