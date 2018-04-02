# DevoloWifi

Modul für IP-Symcon ab Version 4.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguartion)  
6. [Anhang](#6-anhang)  

## 1. Funktionsumfang

## 2. Voraussetzungen

 - IPS 4.x
 - 

## 3. Installation

### a. Laden des Moduls

Die IP-Symcon (min Ver. 4.x) Konsole öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.
 
In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconDevoloWifi.git`
    
und mit _OK_ bestätigen.    
        
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_    

### b. Einrichtung in IPS

In IP-Symcon nun unterhalb von _IO-Symcon_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Devolo_ und als Gerät _DevoloWifi_ auswählen.

In dem Konfigurationsdialog die Zugangsdaten ...... eintragen.

Zu den Geräte-Instanzen werden im Rahmen der Konfiguration Modultyp-abhängig Variablen angelegt. Zusätzlich kann man in den Modultyp-spezifischen Konfigurationsdialog weitere Variablen aktivieren.

Die Instanzen können dann in gewohnter Weise im Objektbaum frei positioniert werden.

## 4. PHP-Befehlsreferenz

### zentrale Funktion

### Hilfsfunktionen

## 5. Konfiguration:

### I/O-Modul

#### Variablen

#### Schaltflächen

### Konfigurator

#### Variablen

#### Schaltflächen

### Geräte

#### Properties

#### Variablen

### Statusvariablen

### Variablenprofile

## 6. Anhang

GUID: `{17E46487-5653-4131-83E9-76E2A73D7DBA}` 
