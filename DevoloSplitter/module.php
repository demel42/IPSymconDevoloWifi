<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class DevoloSplitter extends IPSModule
{
    use DevoloCommon;

    public function Create()
    {
        parent::Create();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        $formElements = [];

        $formActions = [];
        if (IPS_GetKernelVersion() < 5.2) {
            $formActions[] = ['type' => 'Button', 'caption' => 'Module description', 'onClick' => 'echo \'https://github.com/demel42/IPSymconDevoloWifi/blob/master/README.md\';'];
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

    public function ForwardData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'receive data=' . print_r($jdata, true), 0);

        $njdata = [];
        foreach ($jdata as $key => $value) {
            if ($key == 'DataID') {
                continue;
            }
            if ($key == 'ForwardID') {
                $key = 'DataID';
            }
            $njdata[$key] = $value;
        }
        $this->SendDebug(__FUNCTION__, 'send data=' . print_r($njdata, true), 0);
        $this->SendDataToChildren(json_encode($njdata));
    }
}
