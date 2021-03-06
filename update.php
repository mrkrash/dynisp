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
 * Last Modified: 2021-04-16 16:42:20
 */

require('config.php');

$username = (isset($_SERVER['PHP_AUTH_USER']) ? filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_STRING) : null);
$password = (isset($_SERVER['PHP_AUTH_PW']) ? filter_var($_SERVER['PHP_AUTH_PW']) : null);
$hostname = filter_input(INPUT_GET, 'hostname', FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
$ipaddress = filter_input(INPUT_GET, 'myip', FILTER_VALIDATE_IP);

openlog("DynISP-Provider", LOG_PID | LOG_PERROR, LOG_LOCAL0);

if (empty($username) || empty($password)) {
    syslog(LOG_WARN, "Username and password must be set!");
    header('HTTP/1.0 401 Unauthorized');
    echo "Username and password must be set!";
    closelog();
    exit;
}
if (!in_array($hostname, $user_domain[$username]['domains'])) {
    syslog(LOG_WARN, "User and Domain not match!");
    echo "!yours";
    closelog();
    exit;
}
if ($password != $user_domain[$username]['password']) {
    syslog(LOG_WARN, "Username and password must be set!");
    header('HTTP/1.0 401 Unauthorized');
    echo "Username or password do not match!";
    closelog();
    exit;
}
if (empty($hostname) || empty($ipaddress)) {
    syslog(LOG_WARN, "Malformed or Invalid Hostname!");
    echo "notfqdn";
    closelog();
    exit;
}

$domainname = implode('.', array_slice(preg_split('/\./', $hostname), -2, 2));
$hostname = substr(strstr($hostname, $domainname, true), 0, -1);

$opts = [
'ssl' => [
  // set some SSL/TLS specific options
  'verify_peer' => false,
  'verify_peer_name' => false,
  'allow_self_signed' => true
],
  'http'=>[
    'user_agent' => 'PHPSoapClient'
  ]
];

// Using the SOAP module initialize a SoapClient
$client = new SoapClient(
    null,
    [
        'location'   => $soap_location,
        'uri'        => $soap_uri,
        'trace'      => 1,
        'exceptions' => 1,
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ],
        ])
    ]
);

try {
    // Login to SOAP server
    $session_id = $client->login($soap_username, $soap_password);

    // Grab DNS zone ID
    $zone_id = $client->dns_zone_get_id($session_id, $domainname);

    // Grab DNS zone
    $zone = $client->dns_zone_get($session_id, $zone_id);

    // Grab DNS records
    $records = $client->dns_rr_get_all_by_zone($session_id, $zone_id);
    
    // Find right record: hostname must match and type must be A
    $dns_record = null;
    foreach ($records as $rec) {
        if ($rec['type']=='A' && $rec['name']==$hostname) {
            $dns_record = $rec;
        }
    }
    // If no record found
    if (is_null($dns_record)) {
        //Logout from SOAP server
        $client->logout($session_id);
        syslog(LOG_ERR, "Unable to find DNS record for host $hostname in domain $domainname on the server...");
        echo "nohost";
        closelog();
        exit;
    }
    // If IP stored in record is different from current IP
    if ($dns_record['data'] != $ipaddress) {
        // Set new IP
        $dns_record['data'] = $ipaddress;
        // Increment record serial number
        $dns_record['serial'] = $dns_record['serial']+1;
        // Update modified record in DNS server
        $client->dns_a_update($session_id, null, $dns_record['id'], $dns_record);
        
        // Increment zone serial number
        $zone['serial'] = $zone['serial'] + 1;
        // Update modified zone in DNS server
        $client->dns_zone_update($session_id, 0, $zone_id, $zone);
        
        syslog(LOG_INFO, "Successfully set DNS entry for host $hostname in domain $domainname to $ipaddress.");
        echo "good ";
        closelog();
        exit;
    // Otherwise
    } else {
        syslog(LOG_WARN, "IP address of $hostname.$domainname already set to $ipaddress.");
        echo "nochg";
        closelog();
        exit;
    }
    
    //Logout from SOAP server
    $client->logout($session_id);
} catch (SoapFault $e) {
    syslog(LOG_Err, 'SOAP Error: '. $e->getMessage());
}
