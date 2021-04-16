<?php

/**
 * This file is part of the project "dynisp"
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author:    Mario Ravalli <mario@raval.li>
 *
 * Creation Date: 2021-04-16 07:54:31
 * Modified by:   Mario Ravalli
 * Last Modified: 2021-04-16 08:19:41
 */

require('config.php');

$user = $_SERVER['PHP_AUTH_USER'];
$pass = $_SERVER['PHP_AUTH_PW'];

if ($argc<3) {
    print "Usage: php ./update.php HOST DOMAIN [IP]\n";
    print "If not given, the ip address will be queried from icanhazip.com\n";
    die();
}
// First parameter: hostname of DNS entry
$ddns_host = $argv[1];
// Second: domain name of DNS zone
$domain = $argv[2];
// If third parameter is present
if ($argc>3) {
    // Third parameter: IP address of target host
    $ip = $argv[3];
// Otherwise
} else {
    // Figure out the public IP of this host:
    $ip = trim(file_get_contents("http://icanhazip.com/"));
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        die("Unable to retrieve public IP address (icanhazip.com returned $ip)\n");
    }
}

print("Setting DDNS host $ddns_host.$domain to IP $ip\n");

// Using the SOAP module initialize a SoapClient
$client = new SoapClient(
    null,
    array('location'   => $soap_location,
        'uri'        => $soap_uri,
        'trace'      => 1,
        'exceptions' => 1)
);

try {
    // Login to SOAP server
    $session_id = $client->login($soap_user, $soap_password);

    // Grab DNS zone ID
    $zone_id = $client->dns_zone_get_id($session_id, $domain);
    // Grab DNS zone
    $zone = $client->dns_zone_get($session_id, $zone_id);
    // Grab DNS records
    $records = $client->dns_rr_get_all_by_zone($session_id, $zone_id);
    
    // Find right record: hostname must match and type must be A
    $dns_record = null;
    foreach ($records as $rec) {
        if ($rec['type']=='A' && $rec['name']==$ddns_host) {
            $dns_record = $rec;
        }
    }
    // If no record found
    if (is_null($dns_record)) {
        //Logout from SOAP server
        $client->logout($session_id);
        die("Unable to find DNS record for host $ddns_host in domain $domain on the server...\n");
    }
    // If IP stored in record is different from current IP
    if ($dns_record['data'] != $ip) {
        // Set new IP
        $dns_record['data'] = $ip;
        // Increment record serial number
        $dns_record['serial'] = $dns_record['serial']+1;
        // Update modified record in DNS server
        $client->dns_a_update($session_id, null, $dns_record['id'], $dns_record);
        
        // Increment zone serial number
        $zone['serial'] = $zone['serial'] + 1;
        // Update modified zone in DNS server
        $client->dns_zone_update($session_id, 0, $zone_id, $zone);
        
        print("Successfully set DNS entry for host $ddns_host in domain $domain to $ip.\n");
    // Otherwise
    } else {
        print("IP address of $ddns_host.$domain already set to $ip.\n");
    }
    
    //Logout from SOAP server
    $client->logout($session_id);
} catch (SoapFault $e) {
    die('SOAP Error: '.$e->getMessage()."\n");
}
