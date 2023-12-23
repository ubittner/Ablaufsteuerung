<?php

/**
 * @project       Ablaufsteuerung/Ablaufsteuerung/helper/
 * @file          AST_Commands.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

trait AST_Commands
{
    /**
     * Executes the commands.
     *
     * @param string $Commands
     * @throws Exception
     */
    public function ExecuteCommands(string $Commands): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SendDebug(__FUNCTION__, 'Befehle: ' . $Commands, 0);

        if ($this->CheckMaintenance()) {
            return;
        }

        //Enter semaphore
        if (!$this->LockSemaphore('Commands')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore für die Annahme von Befehlen wurde erreicht!', 0);
            $this->SendDebug(__FUNCTION__, 'Der Befehl konnte nicht mehr angenommen werden!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore für die Annahme von Befehlen wurde erreicht!', KL_WARNING);
            $this->UnlockSemaphore('Commands');
            return;
        }

        ########## Queue

        //Enter semaphore for adding commands to the queue
        if (!$this->LockSemaphore('Queue')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore für die Warteschlange wurde erreicht!', 0);
            $this->SendDebug(__FUNCTION__, 'Die Daten konnten nicht mehr zur Warteschlange hinzugefügt werden!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore für die Warteschlange wurde erreicht!', KL_WARNING);
            $this->UnlockSemaphore('Queue');
            return;
        }
        //Add to queue
        $queueList = json_decode($this->ReadAttributeString('Queue'), true);
        $queueList[] = $Commands;
        $this->WriteAttributeString('Queue', json_encode($queueList));
        //Leave semaphore for adding commands to queue
        $this->UnlockSemaphore('Queue');

        //Leave semaphore
        $this->UnlockSemaphore('Commands');

        if (!$this->ReadAttributeBoolean('ProcessingQueue')) {
            $this->SetTimerInterval('ProcessQueue', $this->ReadPropertyInteger('SwitchingDelay'));
        } else {
            $this->SendDebug(__FUNCTION__, 'Die Befehlsabarbeitung läuft bereits!', 0);
        }
    }

    /**
     * Processes the queue.
     *
     * @return void
     * @throws Exception
     */
    public function ProcessQueue(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);

        if ($this->CheckMaintenance()) {
            return;
        }

        $this->WriteAttributeBoolean('ProcessingQueue', true);
        $this->SendDebug(__FUNCTION__, 'Die Befehlsabarbeitung wird gestartet', 0);

        $commands = '';

        ########## Queue

        //Enter semaphore for reading the queue
        if (!$this->LockSemaphore('Queue')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore für die Warteschlange wurde erreicht!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore für die Warteschlange wurde erreicht!', KL_WARNING);
            $this->UnlockSemaphore('Queue');
            $this->WriteAttributeBoolean('ProcessingQueue', false);
            return;
        }

        //Read queue
        $queueList = json_decode($this->ReadAttributeString('Queue'), true);
        if (array_key_exists(0, $queueList)) {
            $commands = $queueList[0];
            unset($queueList[0]);
            $queueList = array_values($queueList);
            $this->WriteAttributeString('Queue', json_encode($queueList));
        }

        //Leave semaphore for reading the queue
        $this->UnlockSemaphore('Queue');

        //Execute commands
        if ($commands != '') {
            foreach (json_decode($commands, true) as $command) {
                $this->SendDebug(__FUNCTION__, 'Befehl: ' . $command, 0);
                IPS_RunScriptText($command);
                IPS_Sleep(50);
            }
        }

        $this->WriteAttributeBoolean('ProcessingQueue', false);
        $this->SendDebug(__FUNCTION__, 'Die Befehlsabarbeitung wurde durchgeführt', 0);

        //Check queue for more commands
        $queueList = json_decode($this->ReadAttributeString('Queue'), true);
        if (!$queueList) {
            $this->SendDebug(__FUNCTION__, 'Die Warteschlange wurde komplett abgearbeitet', 0);
            $this->SetTimerInterval('ProcessQueue', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'Die Abarbeitung der Warteschlange wird fortgesetzt', 0);
            $this->SetTimerInterval('ProcessQueue', $this->ReadPropertyInteger('SwitchingDelay'));
        }
    }

    /**
     * Attempts to set the semaphore and repeats this up to multiple times.
     *
     * @param $Name
     * @return bool
     * @throws Exception
     */
    private function LockSemaphore($Name): bool
    {
        for ($i = 0; $i < $this->ReadPropertyInteger('Attempts'); $i++) {
            if (IPS_SemaphoreEnter(__CLASS__ . '.' . $this->InstanceID . '.' . $Name, 1)) {
                $this->SendDebug(__FUNCTION__, 'Semaphore ' . $Name . ' locked', 0);
                return true;
            } else {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    /**
     * Leaves the semaphore.
     *
     * @param $Name
     * @return void
     */
    private function UnlockSemaphore($Name): void
    {
        @IPS_SemaphoreLeave(__CLASS__ . '.' . $this->InstanceID . '.' . $Name);
        $this->SendDebug(__FUNCTION__, 'Semaphore ' . $Name . ' unlocked', 0);
    }
}