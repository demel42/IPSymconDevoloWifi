<?php

declare(strict_types=1);

$instID = $_IPS['InstanceID'];

$msg = '';
$msg_e = [];
$msg_v = [];
$msg_h = [];

$triggerVars = ['wlan_active', 'guest_active'];
foreach ($triggerVars as $triggerVar) {
    $triggerID = @IPS_GetObjectIDByIdent($triggerVar, $instID);
    if ($triggerID == false) {
        $msg_e[] = 'Variable ' . $hideVar . ' not found';
        continue;
    }
    switch ($triggerVar) {
        case 'guest_active':
            $hideVars = ['guest_sid', 'guest_timeout'];
            $do_hide = !GetValueBoolean($triggerID);
            break;
        case 'wlan_active':
            $hideVars = ['wlan_sid', 'wlan_band'];
            $do_hide = !GetValueBoolean($triggerID);
            break;
        default:
            $vars = [];
            break;
    }
    foreach ($hideVars as $hideVar) {
        $hideID = @IPS_GetObjectIDByIdent($hideVar, $instID);
        if ($hideID == false) {
            $msg_e[] = 'Variable ' . $hideVar . ' not found';
        }
        IPS_SetHidden($hideID, $do_hide);
        if ($do_hide) {
            $msg_h[] = $hideVar;
        } else {
            $msg_v[] = $hideVar;
        }
    }
}

if ($msg_e != []) {
    $msg .= $msg != '' ? '; ' : '';
    $msg .= implode(', ', $msg_e);
}
if ($msg_v != []) {
    $msg .= $msg != '' ? '; ' : '';
    $msg .= 'visible=' . implode(', ', $msg_v);
}
if ($msg_h != []) {
    $msg .= $msg != '' ? '; ' : '';
    $msg .= 'hidden=' . implode(', ', $msg_h);
}

echo $msg . "\n";
