<?php

// Constants will be defined with IP-Symcon 5.0 and newer
if (!defined('KR_READY')) {
    define('KR_READY', 10103);
}
if (!defined('IPS_BOOLEAN')) {
    define('IPS_BOOLEAN', 0);
}
if (!defined('IPS_INTEGER')) {
    define('IPS_INTEGER', 1);
}
if (!defined('IPS_FLOAT')) {
    define('IPS_FLOAT', 2);
}
if (!defined('IPS_STRING')) {
    define('IPS_STRING', 3);
}

class DevoloAccesspoint extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('ap_name', '');

        $this->RegisterPropertyString('username', 'admin');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyInteger('wan_port', 0);
        $this->RegisterPropertyString('wan_bridge', '');

        $this->RegisterPropertyBoolean('with_ap_detail', true);
        $this->RegisterPropertyBoolean('with_wlan_info', true);
        $this->RegisterPropertyBoolean('with_wlan_detail', true);
        $this->RegisterPropertyBoolean('with_guest_info', true);
        $this->RegisterPropertyBoolean('with_guest_detail', true);

        $this->RegisterPropertyInteger('UpdateDataInterval', 5);

        $this->RegisterTimer('UpdateData', 0, 'DevoloAP_UpdateData(' . $this->InstanceID . ');');

        $this->ConnectParent('{C3550FAA-C939-4E85-BA63-7C4DE72ED487}');

        $this->CreateVarProfile('Devolo.TransferRate', IPS_INTEGER, ' Mbit/s', 0, 300, 0, 0, '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $ap_name = $this->ReadPropertyString('ap_name');

        if ($ap_name != '') {
            $this->SetUpdateInterval();
            $this->SetStatus(102);
        } else {
            $this->SetStatus(104);
        }
    }

    protected function SetValue($Ident, $Value)
    {
        if (IPS_GetKernelVersion() >= 5) {
            parent::SetValue($Ident, $Value);
        } else {
            SetValue($this->GetIDForIdent($Ident), $Value);
        }
    }

    // Variablenprofile erstellen
    private function CreateVarProfile($Name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon, $Asscociations = '')
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $ProfileType);
            IPS_SetVariableProfileText($Name, '', $Suffix);
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
            IPS_SetVariableProfileDigits($Name, $Digits);
            IPS_SetVariableProfileIcon($Name, $Icon);
            if ($Asscociations != '') {
                foreach ($Asscociations as $a) {
                    $w = isset($a['Wert']) ? $a['Wert'] : '';
                    $n = isset($a['Name']) ? $a['Name'] : '';
                    $i = isset($a['Icon']) ? $a['Icon'] : '';
                    $f = isset($a['Farbe']) ? $a['Farbe'] : 0;
                    IPS_SetVariableProfileAssociation($Name, $w, $n, $i, $f);
                }
            }
        }
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('UpdateDataInterval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        $this->reallyUpdateData(false);
    }

    public function reallyUpdateData($status_only)
    {
        $ap_name = $this->ReadPropertyString('ap_name');

        $wan_bridge = $this->ReadPropertyString('wan_bridge');
        $wan_port = $this->ReadPropertyInteger('wan_port');

        $with_ap_detail = $this->ReadPropertyBoolean('with_ap_detail');
        $with_wlan_info = $this->ReadPropertyBoolean('with_wlan_info');
        $with_wlan_detail = $this->ReadPropertyBoolean('with_wlan_detail');
        $with_guest_info = $this->ReadPropertyBoolean('with_guest_info');
        $with_guest_detail = $this->ReadPropertyBoolean('with_guest_detail');

        $getjson_url = '/cgi-bin/htmlmgr?_file=getjson&service=';
        $cmd_stations = 'knownstations';
        $cmd_powerline = 'hpdevices';
        $cmd_isGateway = 'isGateway'; // funktioniert nicht wirklich
        $cmd_ethernet = 'switchstatus';

        $status_url = '/cgi-bin/htmlmgr?_file=/wgl/main.wgl&_page=home&_dir=status';

        $now = time();

        $do_abort = false;

        $vpos = 0;

        if (!$status_only) {
            $ap_hostname = '';
            $ap_ip = '';
            $ap_mac = '';
            $ap_dlan_name = '';
            $adapters = [];
            $clients = [];

            if (preg_match('/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}/', $ap_name)) {
                $ap_ip = $ap_name;
                $ap_hostname = gethostbyaddr($ap_ip);
                if ($ap_ip == $ap_hostname) {
                    echo "can't resolve ip '$ap_ip'\n";
                    $ap_hostname = '';
                }
            } else {
                $ap_hostname = $ap_name;
                $ap_ip = gethostbyname($ap_hostname);
                if ($ap_hostname == $ap_ip) {
                    echo "can't resolve host '$ap_hostname'\n";
                    $ap_ip = '';
                }
            }

            $r = IPS_GetObject($this->InstanceID);
            $ap_pos = $r['ObjectPosition'];

            $this->MaintainVariable('Hostname', $this->Translate('Hostname'), IPS_STRING, '', $vpos++, true);
            $this->SetValue('Hostname', $ap_hostname);

            $this->MaintainVariable('IP', $this->Translate('IP-Address'), IPS_STRING, '', $vpos++, true);
            $this->SetValue('IP', $ap_ip);

            $devices = $this->SendQuery2Accesspoint($getjson_url . $cmd_powerline, '', true);
            if ($devices == '') {
                $do_abort = true;
            }

            if (!$do_abort) {
                $ap_receive = 0;
                $ap_transmit = 0;

                $link_speed = 0;
                if ($wan_port > 0) {
                    $sdata = $this->SendQuery2Accesspoint($getjson_url . $cmd_ethernet, '', true);
                    if ($sdata != '') {
                        $info = $sdata['Info'];
                        $ports = $info['Port'];
                        if (count($ports) >= $wan_port) {
                            $port = $ports[$wan_port - 1];
                            if ($port['LinkState'] == 'up') {
                                $link_speed = $port['LinkSpeed'];
                            }
                        }
                    }
                }

                $wan_bridges = [];
                if ($wan_bridge != '') {
                    $wan_bridges[] = $wan_bridge;
                }

                $instIDs = IPS_GetInstanceListByModuleID('{23D74FD6-2468-4239-9D37-83D39CC3FEC1}');
                foreach ($instIDs as $instID) {
                    $varID = @IPS_GetObjectIDByIdent('MAC', $instID);
                    if ($varID == false) {
                        continue;
                    }
                    $mac = GetValueString($varID);

                    $_wan_port = IPS_GetProperty($instID, 'wan_port');
                    if ($_wan_port > 0) {
                        $wan_bridges[] = $mac;
                    }
                }

                foreach ($devices as $device) {
                    // es kommen manchmal array-elemente ohne inhalt
                    if ($device == []) {
                        continue;
                    }

                    if ($device['loc'] == 'local') {
                        $ap_dlan_name = $device['ustr'];
                        $ap_dlan_type = $device['name'];
                        $ap_mac = $device['mac'];
                    }

                    if ($device['loc'] == 'remote') {
                        if ($wan_port > 0) {
                            $ap_receive = $link_speed;
                            $ap_transmit = $link_speed;
                        } else {
                            foreach ($wan_bridges as $wan_bridge) {
                                if ($device['mac'] == $wan_bridge) {
                                    $rx = $device['rx'];
                                    if (is_numeric($rx)) {
                                        $ap_receive = round($rx);
                                    }
                                    $tx = $device['tx'];
                                    if (is_numeric($tx)) {
                                        $ap_transmit = round($tx);
                                    }
                                }
                            }
                        }

                        $rx = is_numeric($device['rx']) ? $device['rx'] : 0;
                        $tx = is_numeric($device['tx']) ? $device['rx'] : 0;
                        $adapter = [
                                'mac'         => $device['mac'],
                                'dlan_name'   => $device['ustr'],
                                'dlan_type'   => $device['name'],
                                'receive'     => $rx,
                                'transmit'    => $tx,
                            ];
                        $adapters[] = $adapter;
                    }
                }
                $jdata = $this->SendQuery2Accesspoint($getjson_url . $cmd_stations, '', true);
                if ($jdata == '') {
                    $do_abort = true;
                }
            }

            $this->MaintainVariable('MAC', $this->Translate('MAC-Address'), IPS_STRING, '', $vpos++, true);
            $this->SetValue('MAC', $ap_mac);

            if (!$do_abort) {
                $knownStations = $jdata['KnownStations'];
                if ($knownStations) {
                    $stations = $knownStations['Station'];
                    foreach ($stations as $station) {
                        if ($station['State'] != 'connected') {
                            continue;
                        }
                        $mac = $station['Mac'];
                        $ip = $station['Ip'];
                        $name = $station['Name'];
                        if ($ip == '' || $ip == '0.0.0.0') {
                            continue;
                        }
                        $s = gethostbyaddr($ip);
                        if ($s != $ip) {
                            $name = $s;
                        }
                        $radio = ($station['Radio'] == '1') ? '5' : '2.4';
                        $rate = $station['Rate'];
                        $time = $station['Time'];
                        $tm = DateTime::createFromFormat('d.m.Y*H:i', $time);
                        if ($tm) {
                            $time = date_format($tm, 'U');
                        }
                        $guest = ($station['AP'] == '1') ? '(Gast)' : '';

                        $client = [
                                'ip'           => $ip,
                                'name'         => $name,
                                'mac'          => $mac,
                                'band'         => $radio,
                                'rate'         => $rate,
                                'connected_ts' => $time,
                                'guest'        => $guest,
                            ];
                        $clients[] = $client;
                    }
                }
            }
        }

        if (!$do_abort) {
            // hier muss mangels API leider die Webseite direkt geparsed werden
            $cdata = $this->SendQuery2Accesspoint($status_url, '', false);
            if ($cdata == '') {
                $do_abort = true;
            }
        }

        $vpos = 100;

        if (!$do_abort) {
            // WLAN für Frequenzband aktiv

            // 2.4GHz
            $wlanActive0 = $this->parse4variable($cdata, 'wlanActive0');

            // 5GHz
            $wlanActive1 = $this->parse4variable($cdata, 'wlanActive1');

            // radiomode
            $radioMode = $this->parse4variable($cdata, 'radioMode');

            // 2.4GHz
            $wlanRadio0 = $this->parse4variable($cdata, 'wlanRadio0');
            $security0 = $this->parse4variable($cdata, 'security0');

            // 5GHz
            $wlanRadio1 = $this->parse4variable($cdata, 'wlanRadio1');
            $security1 = $this->parse4variable($cdata, 'security1');

            $unifiedRadios = $this->parse4variable($cdata, 'unifiedRadios');
            $wlan_unified = $unifiedRadios == 'yes' && $wlanRadio0 != 'off' && $wlanRadio1 != 'off';

            // Gast-WLAN

            // 2.4GHz
            $wlanRadio2 = $this->parse4variable($cdata, 'wlanRadio2');
            $security2 = $this->parse4variable($cdata, 'security2');

            // 5GHz
            $wlanRadio3 = $this->parse4variable($cdata, 'wlanRadio3');
            $security3 = $this->parse4variable($cdata, 'security3');

            // Dauer
            $wlanGuTimeOut = $this->parse4variable($cdata, 'wlanGuTimeOut');

            // Netzwerkinformation
            $network_24 = $this->parse4networkinfo($cdata, '24ghz');
            $network_5 = $this->parse4networkinfo($cdata, '5ghz');
            $network_guest = $this->parse4networkinfo($cdata, 'Guestghz');

            $wlan_24 = [];
            $wlan_24['active'] = $wlanRadio0 == 'on' ? true : false;
            $wlan_24['security'] = $security0;
            if ($network_24 != '') {
                $wlan_24['sid'] = $network_24['sid'];
                $wlan_24['key'] = $network_24['key'];
            }

            $wlan_5 = [];
            $wlan_5['active'] = $wlanRadio1 == 'on';
            $wlan_5['security'] = $security1;
            if ($network_5 != '') {
                $wlan_5['sid'] = $network_5['sid'];
                $wlan_5['key'] = $network_5['key'];
            }

            $wlan_guest = [];
            $wlan_guest['active'] = $wlanRadio2 == 'on' || $wlanRadio3 == 'on';
            $wlan_guest['security'] = $security3;
            if ($network_guest != '') {
                $wlan_guest['sid'] = $network_guest['sid'];
                $wlan_guest['key'] = $network_guest['key'];
            }
            $wlan_guest['timeout'] = is_numeric($wlanGuTimeOut) ? $wlanGuTimeOut : 0;

            $this->MaintainVariable('wlan_active', $this->Translate('WLAN'), IPS_BOOLEAN, '~Switch', $vpos++, $with_wlan_info);
            $wlan_active = $wlan_24['active'] || $wlan_5['active'];
            if ($with_wlan_info) {
                $this->SetValue('wlan_active', $wlan_active);
                $this->EnableAction('wlan_active');
            }

            $use_wlan_band = $with_wlan_info && $wlan_active;
            $this->MaintainVariable('wlan_band', $this->Translate('Band'), IPS_STRING, '', $vpos++, $use_wlan_band);
            if ($use_wlan_band) {
                if ($wlan_24['active'] && $wlan_5['active']) {
                    $band = '2.4 + 5 GHz';
                } elseif ($wlan_24['active']) {
                    $band = '2.4 GHz';
                } elseif ($wlan_5['active']) {
                    $band = '5 GHz';
                } else {
                    $band = $this->Translate('off');
                }
                $this->SetValue('wlan_band', $band);
            }

            $use_wlan_sid = $with_wlan_detail && ($wlan_unified || ($wlan_24['active'] != $wlan_5['active']) && $wlan_active);
            $this->MaintainVariable('wlan_sid', $this->Translate('SID'), IPS_STRING, '', $vpos++, $use_wlan_sid);
            if ($use_wlan_sid) {
                $sid = $wlan_24['sid'] != '' ? $wlan_24['sid'] : $wlan_5['sid'];
                $this->SetValue('wlan_sid', $sid);
            }

            $use_wlan24_sid = $with_wlan_detail && $wlan_24['active'] && !$use_wlan_sid && $wlan_active;
            $this->MaintainVariable('wlan24_sid', $this->Translate('SID 2.4'), IPS_STRING, '', $vpos++, $use_wlan24_sid);
            if ($use_wlan24_sid) {
                $this->SetValue('wlan24_sid', $wlan_24['sid']);
            }

            $use_wlan5_sid = $with_wlan_detail && $wlan_5['active'] && !$use_wlan_sid && $wlan_active;
            $this->MaintainVariable('wlan5_sid', $this->Translate('SID 5'), IPS_STRING, '', $vpos++, $use_wlan5_sid);
            if ($use_wlan5_sid) {
                $this->SetValue('wlan5_sid', $wlan_5['sid']);
            }

            $this->MaintainVariable('guest_active', $this->Translate('Guest-WLAN'), IPS_BOOLEAN, '~Switch', $vpos++, $with_guest_info);
            $guest_active = $wlan_guest['active'];
            if ($with_guest_info) {
                $this->SetValue('guest_active', $guest_active);
                $this->EnableAction('guest_active');
            }

            $use_guest_timeout = $with_guest_detail && $guest_active && $wlan_guest['timeout'] > 0;
            $this->MaintainVariable('guest_timeout', $this->Translate('Guest-Timeout'), IPS_INTEGER, '', $vpos++, $use_guest_timeout);
            if ($use_guest_timeout) {
                $this->SetValue('guest_timeout', $wlan_guest['timeout']);
            }

            $use_guest_sid = $with_guest_detail && $guest_active;
            $this->MaintainVariable('guest_sid', $this->Translate('Guest-SID'), IPS_STRING, '', $vpos++, $use_guest_sid);
            if ($use_guest_sid) {
                $this->SetValue('guest_sid', $wlan_guest['sid']);
            }
        }

        if (!$do_abort) {
            if (!$status_only) {
                $this->MaintainVariable('receive', $this->Translate('receive data'), IPS_INTEGER, 'Devolo.TransferRate', $vpos++, $with_ap_detail);
                $this->MaintainVariable('transmit', $this->Translate('transmit data'), IPS_INTEGER, 'Devolo.TransferRate', $vpos++, $with_ap_detail);
                if ($with_ap_detail) {
                    $this->SetValue('receive', $ap_receive);
                    $this->SetValue('transmit', $ap_transmit);
                }

                $client_n = count($clients);
                $this->MaintainVariable('clients', $this->Translate('count of clients'), IPS_INTEGER, '', $vpos++, true);
                $this->SetValue('clients', $client_n);

                $this->MaintainVariable('last_status', $this->Translate('last status'), IPS_INTEGER, '~UnixTimestamp', $vpos++, true);
                $this->SetValue('last_status', $now);

                $accesspoint = [
                        'timestamp'    => $now,
                        'pos'          => $ap_pos,
                        'name'         => $ap_name,
                        'hostname'     => $ap_hostname,
                        'ip'           => $ap_ip,
                        'mac'          => $ap_mac,
                        'dlan_name'    => $ap_dlan_name,
                        'dlan_type'    => $ap_dlan_type,
                        'receive'      => $ap_receive,
                        'transmit'     => $ap_transmit,
                        'clients'      => $clients,
                        'adapters'     => $adapters,
                        'wlan_unified' => $wlan_unified,
                        'wlan_24'      => $wlan_24,
                        'wlan_5'       => $wlan_5,
                        'wlan_guest'   => $wlan_guest,
                    ];
            } else {
                $accesspoint = json_decode($this->GetBuffer('Accesspoint'), true);
                $accesspoint['wlan_unified'] = $wlan_unified;
                $accesspoint['wlan_24'] = $wlan_24;
                $accesspoint['wlan_5'] = $wlan_5;
                $accesspoint['wlan_guest'] = $wlan_guest;
            }

            $this->SendDebug(__FUNCTION__, 'accesspoint=' . print_r($accesspoint, true), 0);
            $this->SetBuffer('Accesspoint', json_encode($accesspoint));

            $this->SetStatus(102);
            $data = [
                    'DataID'      => '{232A0372-880F-4535-AF1E-8ECF0C7EEF00}',
                    'accesspoint' => $accesspoint
                ];
            $this->SendDataToParent(json_encode($data));
        }

        if ($do_abort) {
            $accesspoint = [
                    'timestamp'    => $now,
                    'pos'          => $ap_pos,
                    'name'         => $ap_name,
                    'hostname'     => $ap_hostname,
                    'ip'           => $ap_ip,
                    'mac'          => $ap_mac,
                ];
            $data = [
                    'DataID'      => '{232A0372-880F-4535-AF1E-8ECF0C7EEF00}',
                    'accesspoint' => $accesspoint
                ];
            $this->SendDataToParent(json_encode($data));
            $this->SetBuffer('Accesspoint', '');
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $setopt_url = '/cgi-bin/htmlmgr';

        switch ($Ident) {
            case 'wlan_active':
                $this->SwitchWLAN($Value);
                break;
            case 'guest_active':
                $this->SwitchGuestWLAN($Value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, "invalid ident $Ident", 0);
                break;
        }
    }

    public function SwitchWLAN(boolean $value)
    {
        $onoff = $value ? 'on' : 'off';

        $accesspoint = json_decode($this->GetBuffer('Accesspoint'), true);
        $dlan_type = $accesspoint['dlan_type'];
        switch ($dlan_type) {
            case 'dLAN 550 WiFi':
                $postdata = [
                        ':sys:Wireless.Radio[0].WLANRadio'			=> $onoff,
                    ];
                break;
            case 'dLAN 1200+ WiFi ac':
                $postdata = [
                        ':sys:Wireless.Radio[0].WLANRadio'			=> $onoff,
                        ':sys:Wireless.Radio[1].WLANRadio'			=> $onoff,
                    ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, "unknown dlan_type $dlan_type", 0);
                return false;
        }

        $r = $this->SendCommand2Accesspoint('/cgi-bin/htmlmgr', $postdata);
        $this->reallyUpdateData(true);
        return $r;
    }

    public function SwitchGuestWLAN(boolean $value, integer $timeout = null)
    {
        $onoff = $value ? 'on' : 'off';
        $tmout = $timeout != null && is_numeric($timeout) ? $timeout : 0;

        $accesspoint = json_decode($this->GetBuffer('Accesspoint'), true);
        $dlan_type = $accesspoint['dlan_type'];
        switch ($dlan_type) {
            case 'dLAN 550 WiFi':
                $postdata = [
                        ':sys:Wireless.GuestTimeout'                => $timeout,
                        ':sys:Wireless.Radio[0].AP[1].Active'       => $onoff,
                    ];
                break;
            case 'dLAN 1200+ WiFi ac':
                $postdata = [
                        ':sys:Wireless.GuestTimeout'                => $timeout,
                        ':sys:Wireless.Radio[0].AP[1].Active'       => $onoff,
                        ':sys:Wireless.Radio[1].AP[1].Active'       => $onoff,
                    ];
                break;
            default:
                $this->SendDebug(__FUNCTION__, "unknown dlan_type $dlan_type", 0);
                return false;
        }

        $r = $this->SendCommand2Accesspoint('/cgi-bin/htmlmgr', $postdata);
        $this->reallyUpdateData(true);
        return $r;
    }

    private function SendQuery2Accesspoint($url, $postdata = '', $do_json = false)
    {
        $ap_name = $this->ReadPropertyString('ap_name');
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $ap_name . $url);
        if ($username != '' && $password != '') {
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        if ($postdata != '') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $statuscode = 0;
        $err = '';
        $data = $cdata;
        if ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = 201;
                $err = "got http-code $httpcode (unauthorized) from $ap_name";
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = 202;
                $err = "got http-code $httpcode (server error) from $ap_name";
            } else {
                $statuscode = 203;
                $err = "got http-code $httpcode from $ap_name";
            }
        } elseif ($cdata == '') {
            $statuscode = 204;
            $err = "no data from $ap_name";
        } elseif ($do_json) {
            $data = json_decode($cdata, true);
            if ($data == '') {
                $statuscode = 204;
                $err = "malformed response from $ap_name";
            }
        }

        if ($statuscode) {
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            $data = '';
        }

        return $data;
    }

    private function SendCommand2Accesspoint($url, $postdata)
    {
        $ap_name = $this->ReadPropertyString('ap_name');
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $ap_name . $url);
        if ($username != '' && $password != '') {
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:  application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $cdata = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $statuscode = 0;
        $err = '';
        $data = $cdata;
        if ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = 201;
                $err = "got http-code $httpcode (unauthorized) from $ap_name";
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = 202;
                $err = "got http-code $httpcode (server error) from $ap_name";
            } else {
                $statuscode = 203;
                $err = "got http-code $httpcode from $ap_name";
            }
        }

        if ($statuscode) {
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }

        return true;
    }

    private function parse4variable($data, $var)
    {
        $val = '';

        if (preg_match('/var ' . $var . '[ ]*=[ ]*".*;/', $data, $r, PREG_OFFSET_CAPTURE)) {
            $val = preg_replace('/.*"([^"]*)".*/', '$1', $r[0][0]);
            $s = substr($data, $r[0][1] + strlen($r[0][0]));
            if (preg_match('/var ' . $var . '[ ]*=[ ]*".*;/', $s, $r)) {
                $val = preg_replace('/.*"([^"]*)".*/', '$1', $r[0]);
            }
        }

        return $val;
    }

    private function parse4networkinfo($cdata, $network)
    {
        $ret = [];

        $s = preg_replace('/\n/', ' ', $cdata);
        $s = preg_replace('/\t/', ' ', $s);
        $t = '<div id="' . $network . '" class="inactive">';

        if (preg_match('/' . $t . '.*/', $s, $r)) {
            $s = preg_replace('/' . $t . '(.*)/', '$1', $r[0]);
            $ok = true;
            for ($i = 1; $i <= 3; $i++) {
                if (!preg_match('/<p class="caption-type-2">.*/', $s, $r)) {
                    $ok = false;
                    break;
                }
                $s = preg_replace('/<p class="caption-type-2">(.*)/', '$1', $r[0]);
            }
            if ($ok) {
                $sid = preg_replace('/<p class="caption-type-2">([^<]*).*/', '$1', $r[0]);
                for ($i = 1; $i <= 3; $i++) {
                    if (!preg_match('/<p class="caption-type-2">.*/', $s, $r)) {
                        $ok = false;
                        break;
                    }
                    $s = preg_replace('/<p class="caption-type-2">(.*)/', '$1', $r[0]);
                }
                if ($ok) {
                    $key = preg_replace('/<p class="caption-type-2">([^<]*).*/', '$1', $r[0]);
                    $ret = [
                            'sid' => $sid,
                            'key' => $key
                        ];
                }
            }
        }

        return $ret;
    }
}
