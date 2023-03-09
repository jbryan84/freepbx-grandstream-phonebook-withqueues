<?php
set_include_path(get_include_path() . PATH_SEPARATOR . '/var/www/html/admin/modules/queues');
require_once("/etc/freepbx.conf");
require_once("Queues.class.php");

// Get a reference to the FreePBX Database
$mysqli = new mysqli($amp_conf['AMPDBHOST'], $amp_conf['AMPDBUSER'], $amp_conf['AMPDBPASS'], $amp_conf['AMPDBNAME']);

function DBQuery($query){
    global $mysqli;
    if (!$sqlResult = mysqli_query($mysqli, $query)) {
        trigger_error('DB query failed: ' . $mysqli->error . "\nquery: " . $query);
        return false;
    } else {
        $all_rows = array();
        while ($row = mysqli_fetch_assoc($sqlResult)) {
            $all_rows[] = $row;
        }
        return $all_rows;
    }
}

function formatXML($xml){
    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = FALSE;
    $dom->loadXML($xml);
    $dom->formatOutput = TRUE;
    return $dom->saveXml();
}

function httpAuthenticate(){
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo '401 Unauthorized';
    exit;
}

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    httpAuthenticate();
} else {
    $PHP_AUTH_USER = mysqli_real_escape_string($mysqli, $_SERVER['PHP_AUTH_USER']);
    $userPasswordLookupResult = DBQuery("select * from sip where id='$PHP_AUTH_USER' and keyword='secret'");
    if (!$userPasswordLookupResult || !$userPasswordLookupResult[0]['data'] == $_SERVER['PHP_AUTH_PW']) {
        httpAuthenticate();
    }
}

function getQueues()
{
    // create a FreePBX object
    $freepbx = \FreePBX::create();

    // create an instance of the Queues class
    $queuesObj = new \FreePBX\modules\Queues($freepbx);
    $queues = $queuesObj->listQueues();
    return array_map(function ($q) {
        return ['name' => $q[1], 'number' => $q[0]];
    }, $queues);
}

header('Content-type: application/xml');
$xml_obj = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AddressBook />');

$entries = array_merge(DBQuery("select * from users"), getQueues());

foreach ($entries as $entry) {
    $name = explode(" ", $entry['name']);
    $Contact = $xml_obj->addChild('Contact');
    $FirstName = $Contact->addChild('FirstName', $name[0]);
    if ($name[1]){
        $LastName = $Contact->addChild('LastName', $name[1]);
    }
    $Phone = $Contact->addChild('Phone');
    $phonenumber = $Phone->addChild('phonenumber', isset($entry['extension']) ? $entry['extension'] : $entry['number']);
    //$phonenumber = $Phone->addChild('phonenumber', $entry['extension'] ?? $entry['number']);
    $accountindex = $Phone->addChild('accountindex', isset($entry['extension']) ? 1 : 2);
}

print formatXML($xml_obj->asXML());

?>