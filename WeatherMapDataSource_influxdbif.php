<?php

// Influxdb IF (interface) - Queries non_negative derivatives from InfluxDB for interface speed values with data collected from collectd.

// HELP: Datasource plugin for influxdbif, target is defined as:
// HELP: influxdbif:influxhost:database:type_instance:seriesin:seriesout
// HELP: eg. "influxdbif:influxdb.localdomain:collectd:TenGigabitEthernet 11_0_25:snmp_rx:snmp_tx"
// HELP:     (use double quotes if there's a space in the name)

// NOTES: Influxdb Query: Query time period and _group by time_ depends on how your data is stored in influx.
// NOTES: Decoded/Rounded value paths may be different depending on how your data is stored. print_r should print the data structure for you to figure out.

// TODO: Needs authentication maybe.
// TODO: Might need hostname instead of just port name. (we got lucky and port names include fabric node)
// TODO: Redo this with either Telegraf or SNMPCollector (slowly backing away from graphite because high volume polling causes high disk iops).

namespace Weathermap\Plugins\Datasources;

use Weathermap\Core\StringUtility;
use Weathermap\Core\Map;
use Weathermap\Core\MapDataItem;

class WeatherMapDataSource_influxdbif extends Base {

    public function __construct() {

        parent::__construct();

        $this->regexpsHandled = array(
            '/^influxdbif:(.*):(.*):(.*):(.*):(.*)$/',
            '/^influxdbif:(.*):(.*):(.*):(.*):(.*):(.*):(.*)$/'
        );
        $this->name = "InfluxIF";
    }

    public function ReadData($targetstring, &$map, &$item)
    {
        $this->data[IN] = NULL;
        $this->data[OUT] = NULL;
        $data_time = time();

        #with login/pass #FIXME#maybe#
        #if (preg_match($this->regexpsHandled[1], $targetstring, $matches)) {
        #   TBD
        #}

        #no login/pass #FIXME#maybe#If we do add optional auto section, vars and loop should be split up.
        if (preg_match($this->regexpsHandled[0], $targetstring, $matches)) {
            $hostdb = $matches[1];
            $database = $matches[2];
            $typeinstance = $matches[3];
            $seriesin = $matches[4];
            $seriesout = $matches[5];
            #DEBUG#printf($hostdb . "-" . $database . "-" . $typeinstance . "-" . $seriesin . "-" . $seriesout . "\n");

            #seriesin
            $query = NULL;
            $url = NULL;
            $encode = NULL;
            $decoded = NULL;

            $query = urlencode("SELECT non_negative_derivative(mean(\"value\"),1s) *8 FROM \"$seriesin\" WHERE \"type_instance\"='$typeinstance' AND time > now() - 2m GROUP BY time(30s)");
            $url = "http://$hostdb:8086/query?db=$database&q=$query";
            $decoded = json_decode(file_get_contents($url), true);

            #DEBUG#print_r($decoded);
            #DEBUG#printf("DECODED: " . $decoded['results']['0']['series']['0']['values'][0][1] . "\n");
            $this->data[IN] = round($decoded['results']['0']['series']['0']['values'][0][1]);
            #DEBUG#printf("ROUNDED: " . $this->data[IN] . "\n");

            #seriesout
            $query = NULL;
            $url = NULL;
            $encode = NULL;
            $decoded = NULL;

            $query = urlencode("SELECT non_negative_derivative(mean(\"value\"),1s) *8 FROM \"$seriesout\" WHERE \"type_instance\"='$typeinstance' AND time > now() - 2m GROUP BY time(30s)");
            $url = "http://$hostdb:8086/query?db=$database&q=$query";
            $decoded = json_decode(file_get_contents($url), true);

            #DEBUG#print_r($decoded);
            #DEBUG#printf("DECODED: " . $decoded['results']['0']['series']['0']['values'][0][1] . "\n");
            $this->data[OUT] = round($decoded['results']['0']['series']['0']['values'][0][1]);
            #DEBUG#printf("ROUNDED: " . $this->data[IN] . "\n");

            $this->dataTime = time();
        }
        #DEBUG#printf("THIS: " . $this->data[IN] . " - " . $this->data[OUT] . " - " . $this->dataTime . "\n");
        return $this->returnData();
    }
}
// vim: ts=4:sw=4:
?>
