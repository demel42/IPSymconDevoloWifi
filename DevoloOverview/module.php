<?php

// Constants will be defined with IP-Symcon 5.0 and newer
if (!defined('IPS_KERNELMESSAGE')) {
    define('IPS_KERNELMESSAGE', 10100);
}
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

class DevoloOverview extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('wan_download', 0);
        $this->RegisterPropertyInteger('wan_upload', 0);

		$this->RegisterPropertyBoolean('with_guest_info', true);

		$associations = [];
		$associations[] = ['Wert' => 1, 'Name' => 'An'];
		$associations[] = ['Wert' => 0, 'Name' => 'Aus'];
		$associations[] = ['Wert' => -1, 'Name' => 'teilweise an'];
		$this->CreateVarProfile('DevoloWifi.WLAN', IPS_INTEGER, '', 0, 0, 0, 1, 'Power', $associations);

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

        $this->SetStatus(102);
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

        $accesspoint_n = 0;
        $client_n = 0;
        foreach ($accesspoints as $accesspoint) {
            $accesspoint_n++;
            if (isset($accesspoint->clients)) {
                $client_n += count($accesspoint->clients);
            }
        }

        $vpos = 0;

        $this->MaintainVariable('accesspoints', $this->Translate('count of accesspoints'), IPS_INTEGER, '', $vpos++, true);
        $this->SetValue('accesspoints', $accesspoint_n);

        $this->MaintainVariable('clients', $this->Translate('count of clients'), IPS_INTEGER, '', $vpos++, true);
        $this->SetValue('clients', $client_n);

		$n_guest_active = 0;
		$n_guest_inactive = 0;
		$n_wlan_active = 0;
		$n_wlan_inactive = 0;

		$instIDs = IPS_GetInstanceListByModuleID("{23D74FD6-2468-4239-9D37-83D39CC3FEC1}");
		foreach ($instIDs as $instID) {
			$r = IPS_GetObject ($instID);
			$childIDs = $r['ChildrenIDs'];
			foreach ($childIDs as $childID) {
				$r = IPS_GetObject ($childID);
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
		} else if ($n_wlan_active) {
			$total_wlan_active = 1;
		} else if ($n_wlan_inactive) {
			$total_wlan_active = 0;
		}
		$this->MaintainVariable('total_wlan_active', $this->Translate('WLAN'), IPS_INTEGER, 'DevoloWifi.WLAN', $vpos++, true);
        $this->SetValue('total_wlan_active', $total_wlan_active);
		$this->EnableAction('total_wlan_active');

		if ($n_guest_active && $n_guest_inactive) {
			$total_guest_active = -1;
		} else if ($n_guest_active) {
			$total_guest_active = 1;
		} else if ($n_guest_inactive) {
			$total_guest_active = 0;
		}
		$this->MaintainVariable('total_guest_active', $this->Translate('Guest-WLAN'), IPS_INTEGER, 'DevoloWifi.WLAN', $vpos++, $with_guest_info);
		if ($with_guest_info) {
			$this->SetValue('total_guest_active', $total_guest_active);
			$this->EnableAction('total_guest_active');
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

	public function SwitchWLAN(boolean $value)
	{
		$instIDs = IPS_GetInstanceListByModuleID("{23D74FD6-2468-4239-9D37-83D39CC3FEC1}");
		foreach ($instIDs as $instID) {
			DevoloAP_SwitchWLAN($instID, $value);
		}
	}

	public function SwitchGuestWLAN(boolean $value, integer $timeout = null)
	{
		$instIDs = IPS_GetInstanceListByModuleID("{23D74FD6-2468-4239-9D37-83D39CC3FEC1}");
		foreach ($instIDs as $instID) {
			DevoloAP_SwitchGuestWLAN($instID, $value, $timeout);
		}
	}

    // Inspired from module SymconTest/HookServe
    private function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    private function ProcessHook_Status()
    {
        $wan_download = $this->ReadPropertyInteger('wan_download');
        $wan_upload = $this->ReadPropertyInteger('wan_upload');

        $s = $this->GetBuffer('Accesspoints');
        $accesspoints = json_decode($s, true);

        $this->SendDebug(__FUNCTION__, 'accesspoints=' . print_r($accesspoints, true), 0);

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
        $html .= "<col width=30%>\n";
        $html .= "<col>\n";
        $html .= "<col id=\"spalte_mac\">\n";
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
        $html .= "<th>Gast</th>\n";
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
                        $html .= "<td>$guest</td>\n";
                        $html .= "</tr>\n";
                        $row_no = $row_no ? 0 : 1;
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
        $html .= "<col id=\"spalte_mac\">\n";
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
                $dlan_name = $accesspoint['dlan_name'];
                $receive = $accesspoint['receive'];
                $transmit = $accesspoint['transmit'];
                $download = $receive > $wan_download ? $wan_download : $receive;
                $upload = $transmit > $wan_upload ? $wan_upload : $transmit;

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
            $this->ProcessHook_Status();
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

    // Inspired from module SymconTest/HookServe
    private function GetMimeType($extension)
    {
        $lines = file(IPS_GetKernelDirEx() . 'mime.types');
        foreach ($lines as $line) {
            $type = explode("\t", $line, 2);
            if (count($type) == 2) {
                $types = explode(' ', trim($type[1]));
                foreach ($types as $ext) {
                    if ($ext == $extension) {
                        return $type[0];
                    }
                }
            }
        }
        return 'text/plain';
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
