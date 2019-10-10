<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class DevoloAccesspoint extends IPSModule
{
    use DevoloCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('ap_name', '');

        $this->RegisterPropertyString('username', 'admin');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyInteger('wan_port', 0);
        $this->RegisterPropertyString('wan_bridge', '');

        $this->RegisterPropertyBoolean('with_dns', true);

        $this->RegisterPropertyBoolean('with_ap_detail', true);
        $this->RegisterPropertyBoolean('with_wlan_info', true);
        $this->RegisterPropertyBoolean('with_wlan_detail', false);
        $this->RegisterPropertyBoolean('with_guest_info', false);
        $this->RegisterPropertyBoolean('with_guest_detail', false);

        $this->RegisterPropertyInteger('visibility_script', 0);

        $this->RegisterPropertyInteger('UpdateDataInterval', 5);

        $this->RegisterTimer('UpdateData', 0, 'DevoloAP_UpdateData(' . $this->InstanceID . ');');

        $this->ConnectParent('{122688B6-1306-4B99-A943-CBF39A6EFFD2}');

        $this->CreateVarProfile('Devolo.TransferRate', VARIABLETYPE_INTEGER, ' Mbit/s', 0, 300, 0, 0, '');

        $associations = [];
        $associations[] = ['Wert' =>  0, 'Name' => 'nie', 'Farbe' => -1];
        $associations[] = ['Wert' =>  1, 'Name' => '%d min', 'Farbe' => -1];
        $this->CreateVarProfile('Devolo.Timeout', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, 'Hourglass', $associations);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $ap_name = $this->ReadPropertyString('ap_name');

        $with_dns = $this->ReadPropertyBoolean('with_dns');
        $with_ap_detail = $this->ReadPropertyBoolean('with_ap_detail');
        $with_wlan_info = $this->ReadPropertyBoolean('with_wlan_info');
        $with_wlan_detail = $this->ReadPropertyBoolean('with_wlan_detail');
        $with_guest_info = $this->ReadPropertyBoolean('with_guest_info');
        $with_guest_detail = $this->ReadPropertyBoolean('with_guest_detail');

        $vpos = 0;
        $this->MaintainVariable('Hostname', $this->Translate('Hostname'), VARIABLETYPE_STRING, '', $vpos++, $with_dns);
        $this->MaintainVariable('IP', $this->Translate('IP-Address'), VARIABLETYPE_STRING, '', $vpos++, $with_dns && $with_ap_detail);
        $this->MaintainVariable('MAC', $this->Translate('MAC-Address'), VARIABLETYPE_STRING, '', $vpos++, $with_ap_detail);
        $this->MaintainVariable('wlan_active', $this->Translate('WLAN'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $with_wlan_info);
        $this->MaintainVariable('wlan_band', $this->Translate('Band'), VARIABLETYPE_STRING, '', $vpos++, $with_wlan_info);
        $this->MaintainVariable('wlan_sid', $this->Translate('SID'), VARIABLETYPE_STRING, '', $vpos++, $with_wlan_detail);
        $this->MaintainVariable('guest_active', $this->Translate('Guest-WLAN'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $with_guest_info);
        $this->MaintainVariable('guest_timeout', $this->Translate('Guest-Timeout'), VARIABLETYPE_INTEGER, 'Devolo.Timeout', $vpos++, $with_guest_detail);
        $this->MaintainVariable('guest_sid', $this->Translate('Guest-SID'), VARIABLETYPE_STRING, '', $vpos++, $with_guest_detail);
        $this->MaintainVariable('receive', $this->Translate('receive data'), VARIABLETYPE_INTEGER, 'Devolo.TransferRate', $vpos++, $with_ap_detail);
        $this->MaintainVariable('transmit', $this->Translate('transmit data'), VARIABLETYPE_INTEGER, 'Devolo.TransferRate', $vpos++, $with_ap_detail);
        $this->MaintainVariable('clients', $this->Translate('count of clients'), VARIABLETYPE_INTEGER, '', $vpos++, true);
        $this->MaintainVariable('last_status', $this->Translate('last status'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('alert', $this->Translate('Status'), VARIABLETYPE_BOOLEAN, '~Alert', $vpos++, true);

        if ($with_wlan_info) {
            $this->EnableAction('wlan_active');
        }
        if ($with_guest_detail) {
            $this->EnableAction('guest_active');
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($ap_name != '') {
            $this->SetUpdateInterval();
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INACTIVE);
        }
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];
        $formElements[] = ['type' => 'Label', 'label' => 'name can be a hostname or ip-address'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'ap_name', 'caption' => 'Name'];
        $formElements[] = ['type' => 'Label', 'label' => 'must only be filled, if restricted access is configured'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'username', 'caption' => 'Username'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $formElements[] = ['type' => 'Label', 'label' => 'optional data of accesspoint'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_dns', 'caption' => 'resolve ip/hostname by DNS'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_ap_detail', 'caption' => 'Details'];
        $formElements[] = ['type' => 'Label', 'label' => 'optional data of WLAN'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_wlan_info', 'caption' => 'Informations'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_wlan_detail', 'caption' => 'Details'];
        $formElements[] = ['type' => 'Label', 'label' => 'optional data of Guest-WLAN'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_guest_info', 'caption' => 'Informations'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'with_guest_detail', 'caption' => 'Details'];
        $formElements[] = ['type' => 'Label', 'label' => 'optional script to hide/show variables'];
        $formElements[] = ['type' => 'SelectScript', 'name' => 'visibility_script', 'caption' => 'visibility'];
        $formElements[] = ['type' => 'Label', 'label' => 'Number of ethernet-port connected to LAN/WAN'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'wan_port', 'caption' => 'WAN-Port'];
        $formElements[] = ['type' => 'Label', 'label' => 'MAC of the dLAN-Adapter next to ethernet'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'wan_bridge', 'caption' => 'WAN-Bridge'];
        $formElements[] = ['type' => 'Label', 'label' => 'query accesspoint every X minutes'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'UpdateDataInterval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Label', 'label' => 'one query makes take several seconds to complete'];
        $formActions[] = ['type' => 'Button', 'label' => 'Perform query', 'onClick' => 'DevoloAP_UpdateData($id);'];
        if (IPS_GetKernelVersion() < 5.2) {
            $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
            $formActions[] = ['type' => 'Button', 'label' => 'Module description', 'onClick' => 'echo \'https://github.com/demel42/IPSymconDevoloWifi/blob/master/README.md\';'];
        }

        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
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

    private function reallyUpdateData($status_only)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $ap_name = $this->ReadPropertyString('ap_name');

        $wan_bridge = $this->ReadPropertyString('wan_bridge');
        $wan_port = $this->ReadPropertyInteger('wan_port');

        $with_dns = $this->ReadPropertyBoolean('with_dns');
        $with_ap_detail = $this->ReadPropertyBoolean('with_ap_detail');
        $with_wlan_info = $this->ReadPropertyBoolean('with_wlan_info');
        $with_wlan_detail = $this->ReadPropertyBoolean('with_wlan_detail');
        $with_guest_info = $this->ReadPropertyBoolean('with_guest_info');
        $with_guest_detail = $this->ReadPropertyBoolean('with_guest_detail');

        $visibility_script = $this->ReadPropertyInteger('visibility_script');

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
            $ap_dlan_type = '';
            $adapters = [];
            $clients = [];

            $r = IPS_GetObject($this->InstanceID);
            $ap_pos = $r['ObjectPosition'];

            if (preg_match('/[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}/', $ap_name)) {
                $ap_ip = $ap_name;
                if ($with_dns) {
                    $ap_hostname = gethostbyaddr($ap_ip);
                    if ($ap_ip == $ap_hostname) {
                        $this->LogMessage('can\'t resolve ip "' . $ap_ip . '"', KL_WARNING);
                        $ap_hostname = '';
                    }
                }
            } else {
                $ap_hostname = $ap_name;
                if ($with_dns) {
                    $ap_ip = gethostbyname($ap_hostname);
                    if ($ap_hostname == $ap_ip) {
                        $this->LogMessage('can\'t resolve host "' . $ap_hostname . '"', KL_WARNING);
                        $ap_ip = '';
                    }
                }
            }

            if ($with_dns) {
                $this->SetValue('Hostname', $ap_hostname);
                $this->SetValue('IP', $ap_ip);
            }

            $devices = $this->SendQuery2Accesspoint($getjson_url . $cmd_powerline, true);
            if ($devices == '') {
                $do_abort = true;
            }

            if (!$do_abort) {
                $ap_receive = 0;
                $ap_transmit = 0;

                $link_speed = 0;
                if ($wan_port > 0) {
                    $sdata = $this->SendQuery2Accesspoint($getjson_url . $cmd_ethernet, true);
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
                $jdata = $this->SendQuery2Accesspoint($getjson_url . $cmd_stations, true);
                if ($jdata == '') {
                    $do_abort = true;
                }
            }

            if ($with_ap_detail) {
                $this->SetValue('MAC', $ap_mac);
            }

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
                        $guest = $station['AP'] == '1';

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
            $cdata = $this->SendQuery2Accesspoint($status_url, false);
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

            if ($with_wlan_info) {
                $wlan_active = $wlan_24['active'] || $wlan_5['active'];
                $this->SetValue('wlan_active', $wlan_active);

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

            if ($with_wlan_detail) {
                $sid = $wlan_24['sid'] != '' ? $wlan_24['sid'] : $wlan_5['sid'];
                $this->SetValue('wlan_sid', $sid);
            }

            if ($with_guest_info) {
                $guest_active = $wlan_guest['active'];
                $this->SetValue('guest_active', $guest_active);
            }

            if ($with_guest_detail) {
                $this->SetValue('guest_timeout', $wlan_guest['timeout']);
                $this->SetValue('guest_sid', $wlan_guest['sid']);
            }
        }

        if (!$do_abort) {
            if (!$status_only) {
                if ($with_ap_detail) {
                    $this->SetValue('receive', $ap_receive);
                    $this->SetValue('transmit', $ap_transmit);
                }
                $client_n = count($clients);
                $this->SetValue('clients', $client_n);
                $this->SetValue('last_status', $now);

                $accesspoint = [
                    'timestamp'    => $now,
                    'alert'        => false,
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
            $this->SetValue('alert', false);

            $this->SetStatus(IS_ACTIVE);
            $data = [
                'DataID'      => '{28AFB779-2495-4462-BFEE-CC5E7CCFE4DB}',
                'ForwardID'   => '{232A0372-880F-4535-AF1E-8ECF0C7EEF00}',
                'accesspoint' => $accesspoint
            ];
            $this->SendDataToParent(json_encode($data));

            if ($visibility_script > 0) {
                $ret = IPS_RunScriptWaitEx($visibility_script, ['InstanceID' => $this->InstanceID]);
                $this->SendDebug(__FUNCTION__, 'visibility_script: ' . $ret, 0);
            }
        }

        if ($do_abort) {
            $accesspoint = [
                'timestamp'    => $now,
                'alert'        => true,
                'pos'          => $ap_pos,
                'name'         => $ap_name,
                'hostname'     => $ap_hostname,
                'ip'           => $ap_ip,
                'mac'          => $ap_mac,
            ];
            $data = [
                'DataID'      => '{28AFB779-2495-4462-BFEE-CC5E7CCFE4DB}',
                'ForwardID'   => '{232A0372-880F-4535-AF1E-8ECF0C7EEF00}',
                'accesspoint' => $accesspoint
            ];
            $this->SendDataToParent(json_encode($data));
            $this->SetBuffer('Accesspoint', '');
            $this->SetValue('alert', true);
        }
    }

    public function RequestAction($Ident, $Value)
    {
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

    public function ReceiveData($data)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        if (isset($jdata->Function)) {
            switch ($jdata->Function) {
                case 'SwitchWLAN':
                    $this->SwitchWLAN($jdata->Value);
                    break;
                case 'SwitchGuestWLAN':
                    $this->SwitchGuestWLAN($jdata->Value, $jdata->Timeout);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata->Function . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }
    }

    public function SwitchWLAN(bool $value)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $onoff = $value ? 'on' : 'off';

        $accesspoint = json_decode($this->GetBuffer('Accesspoint'), true);
        if ($accesspoint == '') {
            $this->SendDebug(__FUNCTION__, 'query must be done before', 0);
            return false;
        }
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

    public function SwitchGuestWLAN(bool $value, int $timeout = null)
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

        $onoff = $value ? 'on' : 'off';
        $tmout = $timeout != null && is_numeric($timeout) ? $timeout : 0;

        $accesspoint = json_decode($this->GetBuffer('Accesspoint'), true);
        if ($accesspoint == '') {
            $this->SendDebug(__FUNCTION__, 'query must be done before', 0);
            return false;
        }
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

    private function SendQuery2Accesspoint($url, $do_json = false)
    {
        $ap_name = $this->ReadPropertyString('ap_name');
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $url = 'http://' . $ap_name . $url;

        $this->SendDebug(__FUNCTION__, 'http-get: url=' . $url, 0);
        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($username != '' && $password != '') {
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        $data = $cdata;
        if ($cerrno) {
            $statuscode = IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = IS_INVALIDDATA;
            $err = 'no data';
        } elseif ($do_json) {
            $data = json_decode($cdata, true);
            if ($data == '') {
                $statuscode = IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
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

        $url = 'http://' . $ap_name . $url;

        $this->SendDebug(__FUNCTION__, 'http-post: url=' . $url, 0);
        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($username != '' && $password != '') {
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:  application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        $data = $cdata;
        if ($cerrno) {
            $statuscode = IS_HTTPERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 400 || $httpcode == 401) {
                $statuscode = IS_UNAUTHORIZED;
                $err = "got http-code $httpcode (unauthorized)";
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = IS_SERVERERROR;
                $err = "got http-code $httpcode (server error)";
            } else {
                $statuscode = IS_HTTPERROR;
                $err = "got http-code $httpcode";
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
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
