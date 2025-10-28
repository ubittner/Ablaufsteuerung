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
        $queueList = json_decode($this->ReadAttributeString('Queue'), true); //array
        $queueList[] = $Commands;
        $this->WriteAttributeString('Queue', json_encode($queueList)); //string
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

        $commands = null;

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
        $queueList = json_decode($this->ReadAttributeString('Queue'), true); //array
        if (array_key_exists(0, $queueList)) {
            $commands = $queueList[0];
            unset($queueList[0]);
            $queueList = array_values($queueList);
            $this->WriteAttributeString('Queue', json_encode($queueList));
        }

        //Leave semaphore for reading the queue
        $this->UnlockSemaphore('Queue');

        //Execute commands
        if (isset($commands)) {
            foreach (json_decode($commands, true) as $command) { //array
                $this->SendDebug(__FUNCTION__, 'Befehl: ' . $command, 0);
                IPS_RunScriptText($command);
                if ($this->ReadPropertyBoolean('UseCommandLog')) {
                    $now = new DateTime();
                    $time = $now->format('d.m.Y, H:i:s.v'); // 'v' = milliseconds (PHP 7.0+)
                    $this->UpdateLastCommands($time . ' ' . $command);
                }
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

    ##### Command Logging

    private function UpdateLastCommands(string $Command): void
    {
        if ($this->CheckMaintenance()) {
            return;
        }
        //Check the number of commands to be displayed.
        $amountOfCommands = $this->ReadPropertyInteger('AmountOfCommands');
        if ($amountOfCommands > 0) {
            if ($amountOfCommands == 1) {
                $this->SetValue('LastCommands', $Command);
            } else {
                $content = array_merge(array_filter(explode("\n", $this->GetValue('LastCommands'))));
                foreach ($content as $key => $command) {
                    //Delete empty message hint
                    if (strpos($command, 'Keine Befehle vorhanden!') !== false) {
                        unset($content[$key]);
                    }
                }
                $entries = $amountOfCommands - 1;
                array_splice($content, $entries);
                array_unshift($content, $Command);
                $newContent = implode("\n", $content);
                $this->SetValue('LastCommands', $newContent);
            }
        }
    }
}