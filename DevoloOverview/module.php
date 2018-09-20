<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

// Constants will be defined with IP-Symcon 5.0 and newer
if (!defined('IPS_KERNELMESSAGE')) {
    define('IPS_KERNELMESSAGE', 10100);
}
if (!defined('KR_READY')) {
    define('KR_READY', 10103);
}

if (!defined('vtBoolean')) {
    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
    define('vtArray', 8);
    define('vtObject', 9);
}

class DevoloOverview extends IPSModule
{
    use DevoloCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('wan_download', 0);
        $this->RegisterPropertyInteger('wan_upload', 0);

        $this->RegisterPropertyBoolean('with_guest_info', false);

        $this->RegisterPropertyBoolean('with_status_box', false);

        $this->RegisterPropertyInteger('statusbox_script', 0);
        $this->RegisterPropertyInteger('webhook_script', 0);

        $associations = [];
        $associations[] = ['Wert' => 1, 'Name' => 'An'];
        $associations[] = ['Wert' => 0, 'Name' => 'Aus'];
        $associations[] = ['Wert' => -1, 'Name' => 'teilweise an'];
        $this->CreateVarProfile('DevoloWifi.WLAN', vtInteger, '', 0, 0, 0, 1, 'Power', $associations);

        // Inspired by module SymconTest/HookServe
        // We need to call the RegisterHook function on Kernel READY
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    // Inspired by module SymconTest/HookServe
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->RegisterHook('/hook/DevoloWifi');
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $with_guest_info = $this->ReadPropertyBoolean('with_guest_info');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');

        $vpos = 0;
        $this->MaintainVariable('accesspoints', $this->Translate('count of accesspoints'), vtInteger, '', $vpos++, true);
        $this->MaintainVariable('clients', $this->Translate('count of clients'), vtInteger, '', $vpos++, true);
        $this->MaintainVariable('StatusBox', $this->Translate('State of accesspoints / clients'), vtString, '~HTMLBox', $vpos++, $with_status_box);
        $this->MaintainVariable('total_wlan_active', $this->Translate('WLAN'), vtInteger, 'DevoloWifi.WLAN', $vpos++, true);
        $this->MaintainAction('total_wlan_active', true);
        $this->MaintainVariable('total_guest_active', $this->Translate('Guest-WLAN'), vtInteger, 'DevoloWifi.WLAN', $vpos++, $with_guest_info);
        $this->MaintainAction('total_guest_active', $with_guest_info);

        $this->SetStatus(102);
    }

    public function ForwardData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $new_accesspoint = $jdata->accesspoint;
        if (isset($new_accesspoint->name)) {
            $ap_name = $new_accesspoint->name;
        } else {
            $ap_name = '';
        }

        $this->SendDebug(__FUNCTION__, 'update accesspoint ' . $ap_name, 0);

        $dbuf = $this->GetBuffer('Accesspoints');
        $accesspoints = json_decode($dbuf);

        $new_accesspoints = [];
        if ($ap_name != '') {
            $new_accesspoints[] = $new_accesspoint;
        }
        if ($accesspoints != '') {
            $ap_names = [];
            $instIDs = IPS_GetInstanceListByModuleID('{23D74FD6-2468-4239-9D37-83D39CC3FEC1}');
            foreach ($instIDs as $instID) {
                $cfg = IPS_GetConfiguration($instID);
                $jcfg = json_decode($cfg, true);
                if (isset($jcfg['ap_name'])) {
                    $ap_names[] = $jcfg['ap_name'];
                }
            }

            foreach ($accesspoints as $accesspoint) {
                if ($accesspoint == '') {
                    continue;
                }
                if (!in_array($accesspoint->name, $ap_names)) {
                    continue;
                }
                if ($accesspoint->name != $ap_name) {
                    $new_accesspoints[] = $accesspoint;
                }
            }
        }

        $this->SetBuffer('Accesspoints', json_encode($new_accesspoints));

        $this->DecodeData($new_accesspoints);
    }

    private function DecodeData($accesspoints)
    {
        $with_guest_info = $this->ReadPropertyBoolean('with_guest_info');
        $with_status_box = $this->ReadPropertyBoolean('with_status_box');

        $accesspoint_n = 0;
        $client_n = 0;
        foreach ($accesspoints as $accesspoint) {
            $accesspoint_n++;
            if (isset($accesspoint->clients)) {
                $client_n += count($accesspoint->clients);
            }
        }

        $this->SetValue('accesspoints', $accesspoint_n);
        $this->SetValue('clients', $client_n);
        if ($with_status_box) {
            $statusbox_script = $this->ReadPropertyInteger('statusbox_script');
            if ($statusbox_script > 0) {
                $html = IPS_RunScriptWaitEx($statusbox_script, ['InstanceID' => $this->InstanceID]);
            } else {
                $html = $this->Build_StatusBox(json_encode($accesspoints));
            }
            $this->SetValue('StatusBox', $html);
        }

        $n_guest_active = 0;
        $n_guest_inactive = 0;
        $n_wlan_active = 0;
        $n_wlan_inactive = 0;

        $instIDs = IPS_GetInstanceListByModuleID('{23D74FD6-2468-4239-9D37-83D39CC3FEC1}');
        foreach ($instIDs as $instID) {
            $r = IPS_GetObject($instID);
            $childIDs = $r['ChildrenIDs'];
            foreach ($childIDs as $childID) {
                $r = IPS_GetObject($childID);
                if ($r['ObjectIdent'] == 'wlan_active') {
                    if (GetValueBoolean($r['ObjectID'])) {
                        $n_wlan_active++;
                    } else {
                        $n_wlan_inactive++;
                    }
                }
                if ($r['ObjectIdent'] == 'guest_active') {
                    if (GetValueBoolean($r['ObjectID'])) {
                        $n_guest_active++;
                    } else {
                        $n_guest_inactive++;
                    }
                }
            }
        }

        if ($n_wlan_active && $n_wlan_inactive) {
            $total_wlan_active = -1;
        } elseif ($n_wlan_active) {
            $total_wlan_active = 1;
        } else {
            $total_wlan_active = 0;
        }
        $this->SetValue('total_wlan_active', $total_wlan_active);

        if ($n_guest_active && $n_guest_inactive) {
            $total_guest_active = -1;
        } elseif ($n_guest_active) {
            $total_guest_active = 1;
        } else {
            $total_guest_active = 0;
        }
        if ($with_guest_info) {
            $this->SetValue('total_guest_active', $total_guest_active);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $setopt_url = '/cgi-bin/htmlmgr';

        switch ($Ident) {
            case 'total_wlan_active':
                if ($Value != 0 && $Value != 1) {
                    $this->SendDebug(__FUNCTION__, "unusable value $Value for Ident $Ident", 0);
                    break;
                }
                $this->SwitchWLAN($Value);
                break;
            case 'total_guest_active':
                if ($Value != 0 && $Value != 1) {
                    $this->SendDebug(__FUNCTION__, "unusable value $Value for Ident $Ident", 0);
                    break;
                }
                $this->SwitchGuestWLAN($Value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, "invalid ident $Ident", 0);
                break;
        }
    }

    public function SwitchWLAN(bool $value)
    {
        $data = ['DataID' => '{68DFE4E1-13BA-4CB0-97C7-3624436869F2}', 'Function' => 'SwitchWLAN', 'Value' => $value];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function SwitchGuestWLAN(bool $value, int $timeout = null)
    {
        $data = ['DataID' => '{68DFE4E1-13BA-4CB0-97C7-3624436869F2}', 'Function' => 'SwitchGuestWLAN', 'Value' => $value, 'Timeout' => $timeout];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    private function Build_StatusBox($data)
    {
        $accesspoints = json_decode($data, true);

        $total_guest_active = false;
        if ($accesspoints != '') {
            foreach ($accesspoints as $accesspoint) {
                if (isset($accesspoint['wlan_guest'])) {
                    if ($accesspoint['wlan_guest']['active']) {
                        $total_guest_active = true;
                        break;
                    }
                }
            }
        }

        $html = '';

        $html .= "<style>\n";
        $html .= ".right-align { text-align: right; }\n";
        $html .= "table { border-collapse: collapse; border: 1px solid; margin: 1; width: 95%; }\n";
        $html .= "tr { border-left: 1px solid; border-top: 1px solid; border-bottom: 1px solid; } \n";
        $html .= "tr:first-child { border-top: 0 none; } \n";
        $html .= "th, td { border: 1px solid; margin: 1; padding: 3px; } \n";
        $html .= "tbody th { text-align: left; }\n";
        $html .= "</style>\n";

        $html .= "<table>\n";
        $html .= "<thead>\n";
        $html .= "<tr class=\"row_title\">\n";
        $html .= "<th>Accesspoint / Client</th>\n";
        $html .= "<th>IP</th>\n";
        $html .= "<th>Frequenz</th>\n";
        $html .= "<th>Übertragungsrate</th>\n";
        $html .= "<th>verbunden seit</th>\n";
        if ($total_guest_active) {
            $html .= "<th>Gast</th>\n";
        }
        $html .= "</tr>\n";
        $html .= "</thead>\n";
        $html .= "<tdata>\n";

        if ($accesspoints != '') {
            usort($accesspoints, ['DevoloOverview', 'cmp_accesspoint']);
            foreach ($accesspoints as $accesspoint) {
                $ap_name = $accesspoint['name'];

                $html .= "<tr>\n";
                $html .= "<td colspan=\"6\">$ap_name</td>\n";
                $html .= "</tr>\n";

                if (isset($accesspoint['clients'])) {
                    $clients = $accesspoint['clients'];
                    if ($clients != '') {
                        usort($clients, ['DevoloOverview', 'cmp_client']);
                        $row_no = 0;
                        foreach ($clients as $client) {
                            $ip = $client['ip'];
                            $name = $client['name'];
                            $band = $client['band'];
                            $rate = $client['rate'];
                            $time = date('d.m. H:i', $client['connected_ts']);
                            $guest = $client['guest'] ? 'Ja' : 'Nein';

                            $html .= "<tr class=\"row_$row_no\">\n";
                            $html .= "<td>&emsp;$name</td>\n";
                            $html .= "<td>$ip</td>\n";
                            $html .= "<td class=\"right-align\">$band GHz</td>\n";
                            $html .= "<td class=\"right-align\">$rate Mbit/s</td>\n";
                            $html .= "<td>$time</td>\n";
                            if ($total_guest_active) {
                                $html .= "<td>$guest</td>\n";
                            }
                            $html .= "</tr>\n";
                            $row_no = $row_no ? 0 : 1;
                        }
                    }
                }
            }
        }

        $html .= "</tdata>\n";
        $html .= "</table>\n";

        $html .= "<br>\n";

        $html .= "</body>\n";

        return $html;
    }

    public function GetRawData()
    {
        $s = $this->GetBuffer('Accesspoints');
        return $s;
    }

    private function ProcessHook_Status()
    {
        $wan_download = $this->ReadPropertyInteger('wan_download');
        $wan_upload = $this->ReadPropertyInteger('wan_upload');

        $s = $this->GetBuffer('Accesspoints');
        $accesspoints = json_decode($s, true);

        $this->SendDebug(__FUNCTION__, 'accesspoints=' . print_r($accesspoints, true), 0);

        $total_guest_active = false;
        if ($accesspoints != '') {
            foreach ($accesspoints as $accesspoint) {
                if (isset($accesspoint['wlan_guest'])) {
                    if ($accesspoint['wlan_guest']['active']) {
                        $total_guest_active = true;
                        break;
                    }
                }
            }
        }

        $html = '';

        $html .= "<!DOCTYPE html>\n";
        $html .= "<html>\n";
        $html .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
        $html .= "<title>HomeAP - Status der dLAN-Accesspoints</title>\n";
        $html .= "<style>\n";
        $html .= "html { height: 100%; background-color: white; }\n";
        $html .= "body { table-cell; text-align: left; vertical-align: top; height: 100%; }\n";
        $html .= "</style>\n";
        $html .= "</head>\n";
        $html .= "<body>\n";
        $html .= "<style>\n";
        $html .= ".monospace { font-family: monospace; }\n";
        $html .= ".right-align { text-align: right; }\n";
        $html .= "body { margin: 1; padding: 0; } \n";
        $html .= "table { border-collapse: collapse; border: 1px solid; margin: 1; width: 95%; }\n";
        $html .= "tr { border-left: 1px solid; border-top: 1px solid; border-bottom: 1px solid; } \n";
        $html .= "tr:first-child { border-top: 0 none; } \n";
        $html .= "th, td { border: 1px solid; margin: 1; padding: 3px; } \n";
        $html .= ".row_title { background-color: grey; }\n";
        $html .= ".row_ap { background-color: lightyellow; font-weight: bold; }\n";
        $html .= ".row_0 { background-color: white; }\n";
        $html .= ".row_1 { background-color: lightgrey; }\n";
        $html .= "tbody th { text-align: left; }\n";
        $html .= "#spalte_mac { width: 120px; }\n";
        $html .= "</style>\n";

        $now = date('d.m. H:i', time());
        $html .= "<h2><center>HomeAP-Status - Stand: $now</center></h2>\n";

        $html .= "<h3>Clients</h3>\n";

        $html .= "<table>\n";
        $html .= "<colgroup>\n";
        $html .= "<col width=25%>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "</colgroup>\n";
        $html .= "<thead>\n";
        $html .= "<tr class=\"row_title\">\n";
        $html .= "<th>Accesspoint / Client</th>\n";
        $html .= "<th>IP</th>\n";
        $html .= "<th>MAC</th>\n";
        $html .= "<th>Frequenz</th>\n";
        $html .= "<th>Übertragungsrate</th>\n";
        $html .= "<th>verbunden seit</th>\n";
        if ($total_guest_active) {
            $html .= "<th>Gast</th>\n";
        }
        $html .= "</tr>\n";
        $html .= "</thead>\n";
        $html .= "<tdata>\n";

        if ($accesspoints != '') {
            usort($accesspoints, ['DevoloOverview', 'cmp_accesspoint']);
            foreach ($accesspoints as $accesspoint) {
                $ap_name = $accesspoint['name'];

                $html .= "<tr class=\"row_ap\">\n";
                $html .= "<td colspan=\"7\">$ap_name</td>\n";
                $html .= "</tr>\n";

                if (isset($accesspoint['clients'])) {
                    $clients = $accesspoint['clients'];
                    if ($clients != '') {
                        usort($clients, ['DevoloOverview', 'cmp_client']);
                        $row_no = 0;
                        foreach ($clients as $client) {
                            $mac = $client['mac'];
                            $ip = $client['ip'];
                            $name = $client['name'];
                            $band = $client['band'];
                            $rate = $client['rate'];
                            $time = date('d.m. H:i', $client['connected_ts']);
                            $guest = $client['guest'] ? 'Ja' : 'Nein';

                            $html .= "<tr class=\"row_$row_no\">\n";
                            $html .= "<td>&emsp;$name</td>\n";
                            $html .= "<td>$ip</td>\n";
                            $html .= "<td class=\"monospace\">$mac</td>\n";
                            $html .= "<td class=\"right-align\">$band GHz</td>\n";
                            $html .= "<td class=\"right-align\">$rate Mbit/s</td>\n";
                            $html .= "<td>$time</td>\n";
                            if ($total_guest_active) {
                                $html .= "<td>$guest</td>\n";
                            }
                            $html .= "</tr>\n";
                            $row_no = $row_no ? 0 : 1;
                        }
                    }
                }
            }
        }

        $html .= "</tdata>\n";
        $html .= "</table>\n";

        $html .= "<br>\n";
        $html .= "<h3>Accesspoints</h3>\n";

        $html .= "<table>\n";
        $html .= "<colgroup>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "<col>\n";
        $html .= "</colgroup>\n";

        $html .= "<thead>\n";
        $html .= "<tr class=\"row_title\">\n";
        $html .= "<th>Accesspoint</th>\n";
        $html .= "<th>IP</th>\n";
        $html .= "<th>dLAN-Name</th>\n";
        $html .= "<th>Empfangen</th>\n";
        $html .= "<th>Senden</th>\n";
        $html .= "<th>max. Download</th>\n";
        $html .= "<th>max. Upload</th>\n";
        $html .= "</tr>\n";
        $html .= "</thead>\n";
        $html .= "<tdata>\n";

        if ($accesspoints != '') {
            $row_no = 0;
            foreach ($accesspoints as $accesspoint) {
                $name = $accesspoint['name'];
                $ip = $accesspoint['ip'];
                $mac = $accesspoint['mac'];
                $dlan_name = isset($accesspoint['dlan_name']) ? $accesspoint['dlan_name'] : '';
                if (isset($accesspoint['receive'])) {
                    $receive = $accesspoint['receive'];
                    $download = $receive > $wan_download ? $wan_download : $receive;
                } else {
                    $receive = '';
                    $download = '';
                }
                if (isset($accesspoint['transmit'])) {
                    $transmit = $accesspoint['transmit'];
                    $upload = $transmit > $wan_upload ? $wan_upload : $transmit;
                } else {
                    $transmit = '';
                    $upload = '';
                }

                $url = 'http://' . $name . '/cgi-bin/htmlmgr?_file=/wgl/main.wgl&_page=home&_dir=status';

                $html .= "<tr class=\"row_$row_no\">\n";
                $html .= "<td><a href=\"$url\">$name</a></td>\n";
                $html .= "<td>$ip</td>\n";
                $html .= "<td>$dlan_name</td>\n";
                $html .= "<td class=\"right-align\">$receive Mbit/s</td>\n";
                $html .= "<td class=\"right-align\">$transmit Mbit/s</td>\n";
                $html .= "<td class=\"right-align\">$download Mbit/s</td>\n";
                $html .= "<td class=\"right-align\">$upload Mbit/s</td>\n";
                $html .= "</tr>\n";
                $row_no = $row_no ? 0 : 1;
            }
        }

        $html .= "</tdata>\n";
        $html .= "</table>\n";

        $html .= "<br>\n";
        $html .= "</body>\n";
        $html .= "</html>\n";

        echo $html;
    }

    // Inspired from module SymconTest/HookServe
    protected function ProcessHookData()
    {
        $this->SendDebug('WebHook SERVER', print_r($_SERVER, true), 0);

        $root = realpath(__DIR__);
        $uri = $_SERVER['REQUEST_URI'];
        if (substr($uri, -1) == '/') {
            http_response_code(404);
            die('File not found!');
        }
        $basename = substr($uri, strlen('/hook/DevoloWifi/'));
        if ($basename == 'status') {
            $webhook_script = $this->ReadPropertyInteger('webhook_script');
            if ($webhook_script > 0) {
                $html = IPS_RunScriptWaitEx($webhook_script, ['InstanceID' => $this->InstanceID]);
                echo $html;
            } else {
                $this->ProcessHook_Status();
            }
            return;
        }
        $path = realpath($root . '/' . $basename);
        if ($path === false) {
            http_response_code(404);
            die('File not found!');
        }
        if (substr($path, 0, strlen($root)) != $root) {
            http_response_code(403);
            die('Security issue. Cannot leave root folder!');
        }
        header('Content-Type: ' . $this->GetMimeType(pathinfo($path, PATHINFO_EXTENSION)));
        readfile($path);
    }

    private function cmp_accesspoint($a, $b)
    {
        $a_pos = $a['pos'];
        $b_pos = $b['pos'];
        if ($a_pos != $b_pos) {
            return ($a_pos < $b_pos) ? -1 : 1;
        }
        $a_name = $a['name'];
        $b_name = $b['name'];
        if ($a_name != $b_name) {
            return (strcmp($a_name, $b_name) < 0) ? -1 : 1;
        }

        $a_mac = $a['mac'];
        $b_mac = $b['mac'];
        return (strcmp($a_mac, $b_mac) < 0) ? -1 : 1;
    }

    private function cmp_client($a, $b)
    {
        $a_time = $a['connected_ts'];
        $b_time = $b['connected_ts'];
        if ($a_time != $b_time) {
            return ($a_time < $b_time) ? -1 : 1;
        }

        $a_name = $a['name'];
        $b_name = $b['name'];
        if ($a_name != $b_name) {
            return (strcmp($a_name, $b_name) < 0) ? -1 : 1;
        }

        $a_mac = $a['mac'];
        $b_mac = $b['mac'];
        return (strcmp($a_mac, $b_mac) < 0) ? -1 : 1;
    }
}
