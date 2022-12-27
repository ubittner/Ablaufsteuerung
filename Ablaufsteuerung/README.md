# Ablaufsteuerung

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

### Inhaltsverzeichnis

1. [Modulbeschreibung](#1-modulbeschreibung)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Schaubild](#3-schaubild)
4. [PHP-Befehlsreferenz](#4-php-befehlsreferenz)
   1. [Befehle ausführen](#41-befehle-ausführen)

### 1. Modulbeschreibung

Dieses Modul steuert einen Ablauf von Schaltbefehlen in [IP-Symcon](https://www.symcon.de).  

Müssen mehrere Schaltbefehle gleichzeitig ausgeführt werden, so muss sichergestellt werden,  
dass zusammenhängende Befehle nicht mit anderen zeitgleichen Befehlen kollidieren und  
gegebenenfalls die Logik unterbrechen.  
Dieses Modul setzt einen Semaphore für Schaltbefehle, bis sie abgearbeitet wurden.  
Erst danach werden die nächsten Schaltbefehle abgearbeitet.

### 2. Voraussetzungen

- IP-Symcon ab Version 6.1

### 3. Schaubild

```
                             +-------------------------+
Externe Aktion ------------->| Ablaufsteuerung (Modul) | 
                             | Befehle                 |
                             |   1. Befehl             |
                             |     2. Befehl           |
                             |       3. Befehl         |
                             |        4. Befehl        |  
                             +------------+------------+
                                          | 
                                          | 
                                          |   
                                          v  
                                +-------------------+
                                | Befehlsausführung |
                                +-------------------+
```

### 4. PHP-Befehlsreferenz

#### 4.1 Befehle ausführen

```
AST_ExecuteCommands(integer integer INSTANCE_ID, string COMMANDS);
```

Der Befehl liefert keinen Rückgabewert.

| Parameter     | Beschreibung                            |
|---------------|-----------------------------------------|
| `INSTANCE_ID` | ID der Instanz                          |
| `COMMANDS`    | Json-codierter String mit den Befehlen. |

Beispiel für einen json-codierten String mit den Befehlen:  

```
$id = 98765;
$AcousticSignal = 0;
$OpticalSignal = 0;
$DurationUnit = 0;
$DurationValue = 5;

$commands = [];
$commands[] = '@HM_WriteValueInteger(' . $id . ", 'ACOUSTIC_ALARM_SELECTION', " . $AcousticSignal . ');';
$commands[] = '@HM_WriteValueInteger(' . $id . ", 'OPTICAL_ALARM_SELECTION', " . $OpticalSignal . ');';
$commands[] = '@HM_WriteValueInteger(' . $id . ", 'DURATION_UNIT', " . $DurationUnit . ');';
$commands[] = '@HM_WriteValueInteger(' . $id . ", 'DURATION_VALUE', " . $DurationValue . ');';

```

Beispiel:  

>AST_ExecuteCommands(12345, json_encode($commands));

---                       