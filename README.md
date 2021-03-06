# IPSymconDevoloWifi

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.3+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Die Firma Devolo stellt Powerline-Adapter her (_dLAN_), sowohl reine dLAN-Adapter als auch dLAN-Accesspoints, die Verbindung zum WAN (Internet) oder lokalen LAN per Powerline-Technologie und herstellen von WLAN-Accesspoints.
Besonders nützlich sind diese Wifi-dLAN-Adapter um eine WLAN auch unter räumlich schwierigen Umständen herstellen zu können.
Die reinen dLAN-Adapter sind reine Layer 2-Geräte, verfügen also nicht über eine IP-Adresse und können keine IP-basierte Information zur Verfügung stellen; die dLAN-Wifi-Adapter haben eine Web-Oberfläche zur Administration.

Es gibt von Devolo keine Möglichkeit, in einer Übersicht mehrere dLAN-Wifi-Adapter zu monitoren oder zu administriere

Da es gibt von Devolo auch keine (dokumentierte) API gibt, ist dieses Modul auf Basis der Analyse der Weboberfläche der dLAN-Wifi-Adapter entstanden.

Hinweis: das gilt für die dLAN-Adapter der Consumer-Serie, die PRO-Serie soll über weitergehende Möglichkeiten verfügen.

Das Modul bietet die Möglichkeit, jeden dieser Adapter als Instanz von _DevoloAccesspoint_ einzubinden und folgende Daten zu ermitteln:
- Angaben zu dem / den WLAN-Bändern unklusive Gast-WLAN
- Angaben zu den WLAN-Clients und der Verbindungsgeschwindigkeit mit der Accesspoint
- Angabe zu der Sende- und Empfangsgeschwindigtkeit des Adapters (mit dem WAN/LAN)<br>
Die Geschwindigkeitsangaben sind immer mit Vorsicht zu verwenden
- dLAN stellt immer Verbindungen direkt zwischen den Adaptern her und ĸann deutliche Unterschiede zwischen einzelnen Verbindungen ausweisen. Ich weise hier in Variablen die Verbindung zum WAN/LAN aus, die anderen Verbindungsinformationen stellt das Modul als Rohdaten zur Verfügung
- die Geschwindingkeit ist stark schwankend und stellt die Momentaufnahme der laufenden Übertragungen dar - bedeutet, das diese u.U. niedirig ist, wenn wenig Daten fliessen
- wie hoch der Anteil der Nutzdaten bzw. der Kommunikations-Overhead ist, ist mir nicht bekannt.

Zusätzlich kann man eine Instanz _DevoloOverview_ anlegen, hier bekommt man eine Übersicht über alle Adapter.

## 2. Voraussetzungen

 - IP-Symcon ab Version 5.3<br>
   Version 4.4 mit Branch _ips_4.4_ (nur noch Fehlerkorrekturen)
 - dLAN Wifi-Adapter, getestet mit _dLAN 1200+ WiFi ac_ und _dLAN 550 WiFi_.<br>
 Meiner Beobachtung nach hat sich an der Weboberfläche nicht viel getan, ѕodaß auch ältere Typen genauso funktionieren sollten.

## 3. Installation

### a. Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconDevoloWifi.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _IO-Symcon_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Devolo_ und als Gerät _DevoloAccesspoint_ auswählen.

In dem Konfigurationsdialog die Zugangsdaten zum Accesspoint eintragen, das ist im wesentlichen die Bezeichnung im Netzwerk sowie das Zugangspasswort (soweit im Accesspoint gesetzt),

Nach der Eingabe der Daten kann man dieses durch die Schaltfläche _Abfrage durchführen_ prüfen - falsche Zugangsdaten werden dann moniert.<br>
Hinweis: der Abruf des Daten dauert einige Sekunden; das liegt daran, das 4-5 HTTP-Requests gemacht werden müssen, um die Daten zu ermitteln.

Die Variablen zu der Instanz werden bei der Abfrage von Daten angelegt und auch ggfs. wieder deaktiviert.

Hier stehen die Möglichkeiten zur Verfügung; das WLAN und das Gast-WLAN des Accesspoints aus- und einzuschalten.

Die Instanz von _DevoloOverview_ legt einen WebHook an, der eine Übersicht als Webseite anbietet (_/hook/DevoloOverview/status_) sowie optional als HTML-Box; beide Ausgaben können alternativ über ein Script gefüllt werden.

Hier stehen die Möglichkeiten zur Verfügung; das gesamte WLAN und das Gast-WLAN aus- und einzuschalten.

Die Instanzen können dann in gewohnter Weise im Objektbaum frei positioniert werden.

## 4. Funktionsreferenz

### zentrale Funktion

`DevoloAP_UpdateData(int $InstanzID)`

ruft die Daten von dem jeweiligen Adapter  ab. Wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

`bool DevoloAP_SwitchWLAN(int $InstanzID, bool $OnOff)`

schaltet das WLAN des Accesspoints.

`bool DevoloAP_SwitchGuestWLAN(int $InstanzID, bool $OnOff, int $Timeout = null)`

schaltet das Gast-WLAN des Accesspoints. Wird beim aktivieren _Timeout_ als *0*, *null* oder *''* übergeben, bleibt das Gast-WLAN dauerhaft eingeschaltet.

`string DevoloAP_GetRawData(int $InstanzID)`

liefert die Zusatzdaten, die nicht in den Variablen gespeichert sind und zu Darstellung der HTML-Box bzw WebHook verwendet werden

Datenstruktur (muss mit json_decode() aufbereitet werden):

- _adapter_: Information zu einem dLAN-Adaptern

| Attribut  | Datentyp | Bedeutung |
| :-------- | :------- | :-------- |
| mac       | string   | MAC-Addresse |
| dlan_name | string   | Bezeichnung im dLAN |
| dlan_type | string   | dLAN-Type  (zB _dLAN 1200+ WiFi ac_, _dLAN 550 WiFi_) |
| receive   | integer  | Empfangsrate vom Adapter zum AP (in Mbit/s) |
| transmit  | integer  | Senderate vom AP zum Adapter (in Mbit/s) |


- _client_: Information zu einem WLAN-Client

| Attribut     | Datentyp       | Bedeutung |
| :----------- | :------------- | :-------- |
| ip           | string         | IP-Adresse |
| name         | string         | Hostname |
| mac          | string         | MAC-Addresse |
| band         | string         | Frequenzband (_2.4_ oder _5_) |
| rate         | integer        | Übertragungsrate (in Mbit/s) |
| connected_ts | UNIX-Timestamp | Verbinsungszeitpunkt |
| guest        | boolean        | ist im Gast-WLAN |

- _wlan_24_, _wlan_5_: Einstellungen zum den WLAN-Frequenzbändern

| Attribut | Datentyp | Bedeutung |
| :------- | :------- | :-------- |
| active   | boolean  | Frequenzband ist aktiv |
| security | string   | Verschlüsslung (_WPA_, _WPA2_, _none_) |
| sid      | string   | SID |
| key      | string   | Schlüssel |

- _wlan_guest_: Einstellungen zum Gast-WLAN

| Attribut | Datentyp | Bedeutung |
| :------- | :------- | :-------- |
| active   | boolean  | Frequenzband ist aktiv |
| security | string   | Verschlüsslung (_WPA_, _WPA2_, _none_) |
| sid      | string   | SID |
| key      | string   | Schlüssel |
| timeout  | integer  | auto. Ascbhaltung des Gast-WLAN |

- accesspoint_: Information zu einem Accesspoint

| Attribut     | Datentyp            | Bedeutung |
| :----------- | :------------------ | :-------- |
| timestamp    | UNIX-Timestamp      | Zeitpunkt der Abfrage |
| pos          | integer             | Position im IPS-Baum (zur Sortierung bei einer Ausgabe) |
| name         | string              | Bezeichnung |
| hostname     | string              | Hostname (i.d.R. der _name_) |
| ip           | string              | IP-Adresse |
| mac          | string              | MAC-Addresse |
| dlan_name    | string              | Bezeichnung im dLAN |
| dlan_type    | string              | dLAN-Type  (zB _dLAN 1200+ WiFi ac_, _dLAN 550 WiFi_) |
| receive      | integer             | Empfangsrate vom AP (in Mbit/s) |
| transmit     | integer             | Senderate vom AP (in Mbit/s) |
| clients      | array von _clients_ | die mit dem AP verbundenen WLAN-Clients |
| adapters     | array von _adapter_ | die mit dem AP kommunizierenden dLAN-Adapter |
| wlan_unified | boolean             | WLAN-Einstellungen für beide Frequenzbänder gleich |
| wlan_24      | boolean             | Einstellung für 2.4 GHz |
| wlan_5       | boolean             | Einstellung für 5 GHz (nur für Typen mit beiden Frequenzen sowie wenn nicht _wlan_unified_) |
| wlan_guest   | boolean             | Einstellung für Gast-WLAN |

Die gelieferte Struktur ist _accesspoint_; kein Array, weil es immer nur um einen Accesspoint geht.

`bool DevoloOverview_SwitchWLAN(int $InstanzID, bool $OnOff)`

schaltet das gsamte WLAN.

`bool DevoloOverview_SwitchGuestWLAN(int $InstanzID, bool $OnOff, int $Timeout = null)`

schaltet das gesamte Gast-WLAN.

`string DevoloOverview_GetRawData(int $InstanzID)`

Daten siehe _DevoloAP_GetRawData_, nur wird ein array von _accesspoint_ übergeben.

## 5. Konfiguration:

### DevoloAccesspoint

#### Variablen

| Eigenschaft        | Typ     | Standardwert | Beschreibung |
| :----------------- | :------ | :----------- | :----------- |
| module_disable     | boolean | false        | Modul temporär deaktivieren |
|                    |         |              | |
| ap_name            | string  |              | Bezeichnung des Hostname oder (statische) IP-Adresse |
| username           | string  | admin        | Benutzerkennung |
| password           | string  |              | Passwort (sofern im Accesspoint gesetzt) |
| with_dns           | boolean | true         | Ermitteln von IP-Adresse/Hostname des AP per DNS |
| with_ap_detail     | boolean | true         | Übertragungsraten |
| with_wlan_info     | boolean | false        | WLAN aktiv?, Frequenzband |
| with_wlan_detail   | boolean | true         | SID(s) |
| with_guest_info    | boolean | false        | Gast-WLAN aktiv? |
| with_guest_detail  | boolean | false        | SID, Timeout |
| visibility_script  | integer |              | Script um die Sichtbarkeit von Variablen zu steuern |
| wan_port           | integer | 0            | Ethernetport, an den das WNA/LAN angeschlossen ist |
| wan_bridge         | integer |              | dLAN-Adpter, der die Verbinung zum WAN/LAN darstellt |
| UpdateDataInterval | integer | 5            | Angabe in Minuten |

- _wan_port_: hier ist der Ethernet-Port des dLAN-Accesspoints der z.B. mit dem Router verbunden ist
- _wan_bridge_: wenn die Verbindung des zum WAN nicht über einen Ethernetport des Accesspoints hergestellt wird, versucht er unter den Adaptern den zu finden, der verbunden ist. Das geht aber ja nur bei den dLAN-Accesspoints - ist es ein reiner dLAN-Adapter, kann dessen MAC-Adresse hier eingtragen werden.
- _visibility_script_: diese optionale Script ermöglicht es dem Anwender, Variablen in Abhängigkeit von Variable auszublenden (z.B. keine Details zum Gast-WLAN, wenn das aus ist). Ein Muster eines solchen Scriptes ist _libs/DevoloVisibility.php_.

#### Schaltflächen

| Bezeichnung         | Beschreibung |
| :------------------ | :----------- |
| Abfrage durchführen | führt eine sofortige Abfrage des Accesspoints durch |

### DevoloOverview

#### Variablen

| Eigenschaft      | Typ     | Standardwert | Beschreibung |
| :--------------- | :------ | :----------- | :----------- |
| module_disable   | boolean | false        | Modul temporär deaktivieren |
|                  |         |              | |
| wan_download     | integer | 0            | WAN/LAN Download (in Mbit/s) |
| wan_upload       | integer | 0            | WAN/LAN Upload (in Mbit/s) |
| with_guest_info  | boolean | false        | Informationen zu Gast-WLAN |
| with_status_box  | boolean | false        | Ausgabe aller verbundenen WLAN-Clients aller Accesspoints |
| statusbox_script | integer |              | Script zum Füllen der Variable _StatusBox_ |
| webhook_script   | integer |              | Script zur Verwendung im WebHook |

- _wan_download_, _wan_upload_: nur zur Darstellung der maximalen Transferaten mit dem Internet.

- _statusbox_script_, _webhook_script_:
mit diesen Scripten kann man eine alternative Darstellung realisieren.

Ein passendes Code-Fragment für ein Script:

```
$data = DevoloOverview_GetRawData($_IPS['InstanceID']);
if ($data) {
	$accesspoints = json_decode($r,true);
	...
	echo $result;
}
```
Die Beschreibung der Struktur siehe _NetatmoWeatherDevice_GetRawData()_.

Beispiel in module.php sind _Build_StatusBox()_ und _ProcessHook_Status()_.
### Variablenprofile

* Integer<br>
Devolo.TransferRate, Devolo.Timeout

## 6. Anhang

GUIDs
- Modul: `{17E46487-5653-4131-83E9-76E2A73D7DBA}`
- Instanzen:
  - DevoloAccesspoint: `{23D74FD6-2468-4239-9D37-83D39CC3FEC1}`
  - DevoloOverview: `{C3550FAA-C939-4E85-BA63-7C4DE72ED487}`
  - DevoloSplitter: `{122688B6-1306-4B99-A943-CBF39A6EFFD2}`
- Nachrichten:
  - `{28AFB779-2495-4462-BFEE-CC5E7CCFE4DB}`: an DevoloSplitter
  - `{232A0372-880F-4535-AF1E-8ECF0C7EEF00}`: an DevoloOverview
  - `{68DFE4E1-13BA-4CB0-97C7-3624436869F2}`: an DevoloAccesspoint

## 7. Versions-Historie

- 1.17 @ 18.12.2020 14:57 (beta)
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt
  - LICENSE.md hinzugefügt

- 1.16 @ 06.01.2020 11:17
  - Nutzung von RegisterReference() für im Modul genutze Objekte (Scripte, Kategorien etc)

- 1.15 @ 30.12.2019 10:56
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert

- 1.14 @ 10.10.2019 17:27
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer
- 1.13 @ 01.09.2019 18:22
  - Anpassung für IPS 5.2: bisherige Kommunikation zwischen DevoloOverview und DevoloAccesspoint wird nun über (den ansonten funktionslosen) DevoloSplitter kommuniziert<br>
  Achtung: nach dem Update muss ggfs. in den Modulen der Splitte als Gateway eingetragen wurden.

- 1.12 @ 09.08.2019 14:32
  - Schreibfehler korrigiert

- 1.11 @ 02.05.2019 18:27
  - Bugfix: Anlage von DevoloOverview war nicht mehr möglich

- 1.10 @ 06.04.2019 08:07
  - fix (IS_SERVERERROR)

- 1.9 @ 29.03.2019 16:19
  - SetValue() abgesichert

- 1.8 @ 20.03.2019 09:05
  - Anpassungen IPS 5, Abspaltung Branch _ips_4.4_
  - form.json in GetConfigurationForm() abgebildet
  - Schalter, um eine Instanz (temporär) zu deaktivieren

- 1.7 @ 23.01.2019 18:18
  - curl_errno() abfragen

- 1.6 @ 22.12.2018 09:21
  - Fehler in der http-Kommunikation nun nicht mehr mit _echo_ (also **ERROR**) sondern mit _LogMessage_ als **NOTIFY**
  - Fix: _undefined variable ap_dlan_type_

- 1.5 @ 21.12.2018 13:10
  - Standard-Konstanten verwenden

- 1.4 @ 23.11.2018 10:58
  - Zusätzliche Feld _Status_, das bei Nichterreichbarkeit des Accesspoint auf _true_ (Alarm) gesetzt wird.

- 1.3 @ 29.10.2018 18:10
  - Abfangen von leeren Datenfeldern ('connected_ts')

- 1.2 @ 13.10.2018 18:02
  - Umstellung der internen Speicherung zur Vermeidung der Warnung _Puffer > 8kb_.

- 1.1 @ 20.09.2018 17:18
  - Versionshistorie dazu,
  - define's der Variablentypen,
  - Schaltfläche mit Link zu README.md im Konfigurationsdialog

- 1.0 @ 03.04.2018 14:11
  - Initiale Version
