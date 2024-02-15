<?php

function get_daily_stats($feed,$app,$start,$end,$starting_power) {

    $timezone = 'Europe/London';
    $date = new DateTime();
    $date->setTimezone(new DateTimeZone($timezone));

    if ($end===null || $start===null) {
        $date->modify("midnight");
        $end = $date->getTimestamp();
        $date->modify("-30 day");
        $start = $date->getTimestamp();
    } else {
        $start = convert_time($start,$timezone);
        $end = convert_time($end,$timezone);
        
        $date->setTimestamp($start);
        $date->modify("midnight");
        $start = $date->getTimestamp();
    }
    
    $out = "";
    $fields = array();
    $fields[] = "timestamp";
    
    $fields[] = "combined_elec_kwh";
    $fields[] = "combined_heat_kwh";
    $fields[] = "combined_cop";
    $fields[] = "combined_data_length";
    $fields[] = "combined_flowT_mean";
    $fields[] = "combined_returnT_mean";
    $fields[] = "combined_outsideT_mean";
        
    $fields[] = "running_elec_kwh";
    $fields[] = "running_heat_kwh";
    $fields[] = "running_cop";
    $fields[] = "running_data_length";
    $fields[] = "running_flowT_mean";
    $fields[] = "running_returnT_mean";
    $fields[] = "running_outsideT_mean";

    $fields[] = "space_elec_kwh";
    $fields[] = "space_heat_kwh";
    $fields[] = "space_cop";
    $fields[] = "space_data_length";
    $fields[] = "space_flowT_mean";
    $fields[] = "space_returnT_mean";
    $fields[] = "space_outsideT_mean";
    
    $fields[] = "water_elec_kwh";
    $fields[] = "water_heat_kwh";
    $fields[] = "water_cop";
    $fields[] = "water_data_length";
    $fields[] = "water_flowT_mean";
    $fields[] = "water_returnT_mean";
    $fields[] = "water_outsideT_mean";
    
    $fields[] = "quality_elec";
    $fields[] = "quality_heat";
    $fields[] = "quality_flowT";
    $fields[] = "quality_returnT";
    $fields[] = "quality_outsideT";

    $out .= implode(",",$fields)."\n";
        
    $time = $start;
    while ($time<$end) {
        // print $date->format("c")."\n";
        
        $stats = get_heatpump_stats($feed,$app,$time,$time+(3600*24),$starting_power);
        
        $values = array();
        
        $values[] = $stats['start'];
        $values[] = $stats['stats']['combined']['elec_kwh'];
        $values[] = $stats['stats']['combined']['heat_kwh'];
        $values[] = $stats['stats']['combined']['cop'];
        $values[] = $stats['stats']['combined']['data_length'];
        $values[] = $stats['stats']['combined']['flowT_mean'];
        $values[] = $stats['stats']['combined']['returnT_mean'];
        $values[] = $stats['stats']['combined']['outsideT_mean'];

        $values[] = $stats['stats']['when_running']['elec_kwh'];
        $values[] = $stats['stats']['when_running']['heat_kwh'];
        $values[] = $stats['stats']['when_running']['cop'];
        $values[] = $stats['stats']['when_running']['data_length'];
        $values[] = $stats['stats']['when_running']['flowT_mean'];
        $values[] = $stats['stats']['when_running']['returnT_mean'];
        $values[] = $stats['stats']['when_running']['outsideT_mean'];
        
        $values[] = $stats['stats']['space_heating']['elec_kwh'];
        $values[] = $stats['stats']['space_heating']['heat_kwh'];
        $values[] = $stats['stats']['space_heating']['cop'];
        $values[] = $stats['stats']['space_heating']['data_length'];
        $values[] = $stats['stats']['space_heating']['flowT_mean'];
        $values[] = $stats['stats']['space_heating']['returnT_mean'];
        $values[] = $stats['stats']['space_heating']['outsideT_mean'];
                
        $values[] = $stats['stats']['water_heating']['elec_kwh'];
        $values[] = $stats['stats']['water_heating']['heat_kwh'];
        $values[] = $stats['stats']['water_heating']['cop'];
        $values[] = $stats['stats']['water_heating']['data_length'];
        $values[] = $stats['stats']['water_heating']['flowT_mean'];
        $values[] = $stats['stats']['water_heating']['returnT_mean'];
        $values[] = $stats['stats']['water_heating']['outsideT_mean'];
        
        $values[] = $stats['quality']['elec'];
        $values[] = $stats['quality']['heat'];
        $values[] = $stats['quality']['flowT'];
        $values[] = $stats['quality']['returnT'];
        $values[] = $stats['quality']['outsideT'];
                
        $out .= implode(",",$values)."\n";
        
        $date->modify("+1 day");
        $time = $date->getTimestamp();
    }

    return $out;
}

function get_heatpump_stats($feed,$app,$start,$end,$starting_power) {

    // --------------------------------------------------------------------------------------------------------------    
    // Validate params
    // --------------------------------------------------------------------------------------------------------------
    if ($end===null || $start===null) {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone("Europe/London"));
        $date->modify("midnight");
        $end = $date->getTimestamp();
        $date->modify("-30 day");
        $start = $date->getTimestamp();
    } else {
        $timezone = 'Europe/London';
        $start = convert_time($start,$timezone);
        $end = convert_time($end,$timezone);
    }
    
    if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");

    $period = $end-$start;
    if ($period<=3600*24*7) {
        $interval = 10;
    } else if ($period<=3600*24*14) {
        $interval = 20;
    } else if ($period<=3600*24*21) {
        $interval = 30;
    } else if ($period<=3600*24*42) {
        $interval = 60;
    } else if ($period<=3600*24*90) {
        $interval = 120;
    } else {
        return array('success'=>false, 'message'=>"period to large");
    }
    
    //$interval = 60;
    
    
    if (!isset($app->config->heatpump_elec) || $app->config->heatpump_elec<1) return array('success'=>false, 'message'=>"Missing electricity consumption feed");
        
    // --------------------------------------------------------------------------------------------------------------    
    // Load data
    // --------------------------------------------------------------------------------------------------------------    
    $data = array();
    
    $elec_meta = $feed->get_meta($app->config->heatpump_elec);
    
    $feeds = array("heatpump_elec","heatpump_flowT","heatpump_returnT","heatpump_outsideT","heatpump_heat","heatpump_dhw");
    
    foreach ($feeds as $key) {
        $data[$key] = false;
        if (isset($app->config->$key) && $app->config->$key>0) {   
            $data[$key] = $feed->get_data($app->config->$key,$start,$end-$interval,$interval,1,"Europe/London","notime");
            $data[$key] = remove_null_values($data[$key],$interval);
        }
    }
    
    $cop_stats = calculate_window_cops($data, $interval, $starting_power);
    
    $stats = process_stats($data, $interval, $starting_power);
    
    foreach ($stats as $category => $val) {
        $cop_stats[$category]["flowT_mean"] = $stats[$category]["flowT"]["mean"];
        $cop_stats[$category]["returnT_mean"] = $stats[$category]["returnT"]["mean"];
        $cop_stats[$category]["outsideT_mean"] = $stats[$category]["outsideT"]["mean"];
    }
    
    $result = [
      "start"=>(int)$start,
      "end"=>(int)$end,
      "interval"=>(int)$interval,
      "stats"=>$cop_stats,
      //"stats"=>$stats,
      "quality"=>[
        "elec"=>get_quality($data["heatpump_elec"]),
        "heat"=>get_quality($data["heatpump_heat"]),
        "flowT"=>get_quality($data["heatpump_flowT"]),
        "returnT"=>get_quality($data["heatpump_returnT"]),
        "outsideT"=>get_quality($data["heatpump_outsideT"])
      ]
    ];
    
    return $result;
}

function process_stats($data, $interval, $starting_power) {
    $stats = [
        'combined' => [],
        'when_running' => [],
        'space_heating' => [],
        'water_heating' => []
    ];
    
    $feed_options = [
        "elec" => ["name" => "Electric consumption", "unit" => "W", "dp" => 0],
        "heat" => ["name" => "Heat output", "unit" => "W", "dp" => 0],
        //"heat_carnot" => ["name" => "Simulated heat output", "unit" => "W", "dp" => 0],
        "flowT" => ["name" => "Flow temperature", "unit" => "°C", "dp" => 1],
        "returnT" => ["name" => "Return temperature", "unit" => "°C", "dp" => 1],
        "outsideT" => ["name" => "Outside temperature", "unit" => "°C", "dp" => 1],
        //"roomT" => ["name" => "Room temperature", "unit" => "°C", "dp" => 1],
        //"targetT" => ["name" => "Target temperature", "unit" => "°C", "dp" => 1],
        //"flowrate" => ["name" => "Flow rate", "unit" => "", "dp" => 3]
    ];

    foreach ($feed_options as $key => $props) {
        foreach ($stats as $x => $val) {
            $stats[$x][$key] = [
                'sum' => 0,
                'count' => 0,
                'mean' => null,
                'kwh' => null,
                //'minval' => null,
                //'maxval' => null
            ];
        }
    }

    $dhw_enable = false;
    if (isset($data["heatpump_dhw"]) && $data["heatpump_dhw"] != false) {
        $dhw_enable = true;
    }

    for ($z = 0; $z < count($data["heatpump_elec"]); $z++) {
        $power = $data["heatpump_elec"][$z];

        $dhw = false;
        if ($dhw_enable) {
            $dhw = $data["heatpump_dhw"][$z];
        }

        foreach ($feed_options as $key => $props) {
            if (isset($data["heatpump_".$key][$z])) {
                $value = $data["heatpump_".$key][$z];
                if ($value !== null) {

                    $stats['combined'][$key]['sum'] += $value;
                    $stats['combined'][$key]['count']++;
                    //stats_min_max($stats, 'combined', $key, $value);

                    if ($power !== null && $power >= $starting_power) {
                        $stats['when_running'][$key]['sum'] += $value;
                        $stats['when_running'][$key]['count']++;
                        //stats_min_max($stats, 'when_running', $key, $value);

                        if ($dhw_enable) {
                            if ($dhw) {
                                $stats['water_heating'][$key]['sum'] += $value;
                                $stats['water_heating'][$key]['count']++;
                                //stats_min_max($stats, 'water_heating', $key, $value);
                            } else {
                                $stats['space_heating'][$key]['sum'] += $value;
                                $stats['space_heating'][$key]['count']++;
                                //stats_min_max($stats, 'space_heating', $key, $value);
                            }
                        }
                    }
                }
            }
        }
    }

    foreach ($stats as $x => $val) {
        foreach ($feed_options as $key => $props) {

            $stats[$x][$key]["mean"] = null;
            if ($stats[$x][$key]["count"] > 0) {
                $stats[$x][$key]["mean"] = $stats[$x][$key]["sum"] / $stats[$x][$key]["count"];
            }
            /*
            $stats[$x][$key]["diff"] = null;
            if ($stats[$x][$key]["minval"] !== null && $stats[$x][$key]["maxval"] !== null) {
                $stats[$x][$key]["diff"] = $stats[$x][$key]["maxval"] - $stats[$x][$key]["minval"];
            }
            */
        }
    }
    
    foreach ($stats as $x => $val) {
        foreach ($feed_options as $key => $props) {
        
            if ($props["unit"] == "W" && $stats[$x][$key]["mean"] !== null) {
                $stats[$x][$key]["kwh"] = ($stats[$x][$key]["mean"] * $stats[$x][$key]["count"] * $interval) / 3600000;
                $stats[$x][$key]["kwh"] = number_format($stats[$x][$key]["kwh"],3,".","")*1;            
            } else {
                unset($stats[$x][$key]["kwh"]);
            }
        
            //if ($stats[$x][$key]["sum"]!==null) {
            //    $stats[$x][$key]["sum"] = number_format($stats[$x][$key]["sum"],$props["dp"],".","")*1;
            //}
            unset($stats[$x][$key]["sum"]);
            unset($stats[$x][$key]["count"]);
            
            if ($stats[$x][$key]["mean"]!==null) {
                $stats[$x][$key]["mean"] = number_format($stats[$x][$key]["mean"],$props["dp"],".","")*1;
            }
            /*
            if ($stats[$x][$key]["minval"]!==null) {
                $stats[$x][$key]["minval"] = number_format($stats[$x][$key]["minval"],$props["dp"],".","")*1;  
            }
            if ($stats[$x][$key]["maxval"]!==null) {
                $stats[$x][$key]["maxval"] = number_format($stats[$x][$key]["maxval"],$props["dp"],".","")*1;  
            }    
            if ($stats[$x][$key]["diff"]!==null) {
                $stats[$x][$key]["diff"] = number_format($stats[$x][$key]["diff"],$props["dp"],".","")*1;  
            }*/
             
        }
    }

    return $stats;
}

function stats_min_max(&$stats, $category, $key, $value) {
    // This function should update the min and max values in $stats array
    // Initialize min and max if they are null
    if ($stats[$category][$key]['minval'] === null || $value < $stats[$category][$key]['minval']) {
        $stats[$category][$key]['minval'] = $value;
    }
    if ($stats[$category][$key]['maxval'] === null || $value > $stats[$category][$key]['maxval']) {
        $stats[$category][$key]['maxval'] = $value;
    }
}


// Remove null values from feed data
function remove_null_values($data, $interval) {
    $last_valid_pos = 0;
    for ($pos = 0; $pos < count($data); $pos++) {
        if ($data[$pos] !== null) {
            $null_time = ($pos - $last_valid_pos) * $interval;
            if ($null_time < 900) {
                for ($x = $last_valid_pos + 1; $x < $pos; $x++) {
                    $data[$x] = $data[$last_valid_pos];
                }
            }
            $last_valid_pos = $pos;
        }
    }
    return $data;
}

function get_quality($data) {
    $count = count($data);
    if ($count<1) return 0;
    
    $null_count = 0;
    for ($pos = 0; $pos < $count; $pos++) {
        if (is_null($data[$pos])) {
            $null_count ++;
        }
    }
    $quality = 100*(1-($null_count / $count));
    return number_format($quality,2,'.','')*1;
}

function calculate_window_cops($data, $interval, $starting_power) {
    $cop_stats = array(
        "combined" => array(),
        "when_running" => array(),
        "space_heating" => array(),
        "water_heating" => array(),
    );

    foreach ($cop_stats as $category => $value) {
        $cop_stats[$category]["elec_kwh"] = 0;
        $cop_stats[$category]["heat_kwh"] = 0;
        $cop_stats[$category]["data_length"] = 0;
    }

    if (isset($data["heatpump_elec"]) && isset($data["heatpump_heat"])) {

        $dhw_enable = false;
        if (isset($data["heatpump_dhw"]) && $data["heatpump_dhw"] != false) {
            $dhw_enable = true;
        }

        $power_to_kwh = 1.0 * $interval / 3600000.0;

        foreach ($data["heatpump_elec"] as $z => $elec_data) {
            $elec = $data["heatpump_elec"][$z]*1;
            $heat = $data["heatpump_heat"][$z]*1;

            $dhw = false;
            if ($dhw_enable) {
                $dhw = $data["heatpump_dhw"][$z];
            }

            if ($elec !== null && $heat !== null) {
                $cop_stats["combined"]["elec_kwh"] += $elec * $power_to_kwh;
                $cop_stats["combined"]["heat_kwh"] += $heat * $power_to_kwh;
                $cop_stats["combined"]["data_length"] += $interval;
                
                if ($elec >= $starting_power) {
                    $cop_stats["when_running"]["elec_kwh"] += $elec * $power_to_kwh;
                    $cop_stats["when_running"]["heat_kwh"] += $heat * $power_to_kwh;
                    $cop_stats["when_running"]["data_length"] += $interval;

                    if ($dhw_enable) {
                        if ($dhw) {
                            $cop_stats["water_heating"]["elec_kwh"] += $elec * $power_to_kwh;
                            $cop_stats["water_heating"]["heat_kwh"] += $heat * $power_to_kwh;
                            $cop_stats["water_heating"]["data_length"] += $interval;
                        } else {
                            $cop_stats["space_heating"]["elec_kwh"] += $elec * $power_to_kwh;
                            $cop_stats["space_heating"]["heat_kwh"] += $heat * $power_to_kwh;
                            $cop_stats["space_heating"]["data_length"] += $interval;
                        }
                    }
                }
            }
        }

        foreach ($cop_stats as $category => $stats) {
        
            $cop_stats[$category]["cop"] = 0;
            if ($cop_stats[$category]["elec_kwh"] > 0) {
                $cop_stats[$category]["cop"] = $cop_stats[$category]["heat_kwh"] / $cop_stats[$category]["elec_kwh"];
            }

            $cop_stats[$category]["elec_kwh"] = number_format($cop_stats[$category]["elec_kwh"],3,".","")*1;            
            $cop_stats[$category]["heat_kwh"] = number_format($cop_stats[$category]["heat_kwh"],3,".","")*1;            
            $cop_stats[$category]["cop"] = number_format($cop_stats[$category]["cop"],2,".","")*1;
            
            if ($cop_stats[$category]["data_length"] == 0) {
                $cop_stats[$category]["elec_kwh"] = null;
                $cop_stats[$category]["heat_kwh"] = null;
                $cop_stats[$category]["cop"] = null;
            }
        }
    }
    return $cop_stats;
}


function convert_time($time,$timezone) {
    // Option to specify times as date strings
    if (!is_numeric($time)) {
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone($timezone));
        $date->modify($time);
        $date->modify('midnight');
        $time = $date->getTimestamp();
    }    
    
    // If timestamp is in milliseconds convert to seconds
    if (($time/1000000000)>100) {
        $time *= 0.001;
    }
    return $time;
}
