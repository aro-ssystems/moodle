<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language pack for mod_quiz — timer strings.
 * Other strings fall back to English.
 *
 * @package   mod_quiz
 * @copyright 2026 SSYSTEMS GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['timestring'] = '%%HH%% Std. %%MM%% Min.';
$string['timerstages'] = 'Phasen';
$string['timerstageshelp'] = 'Zeitphasen für diesen Quizversuch';
$string['timersyncerror'] = 'Timer-Synchronisation fehlgeschlagen — Anzeige kann ungenau sein';
$string['timersyncstale'] = 'Timer wird synchronisiert…';

// Multi-stage timer strings.
$string['timergrantedextratime'] = 'Zusätzliche Zeit gewährt';
$string['timerstageconfirmstart'] = 'Ihr Versuch hat ein Zeitlimit. Wenn Sie starten, beginnt der Timer herunterzuzählen und kann nicht pausiert werden. Sie müssen Ihren Versuch <strong>abgeben</strong>, bevor die Zeit abläuft. Möchten Sie jetzt starten?';
$string['timerstageextra'] = '(Ihnen wurde zusätzliche Zeit von {$a} gewährt)';
$string['timerstagelimits'] = 'Zeitlimits';
$string['timerstageline'] = '{$a->name}: {$a->duration}';
$string['timerstagemustsubmitby'] = 'Abgabe bis {$a}';
$string['timerstageparseerror'] = 'Zeile {$a->line} (\'{$a->input}\') hat nicht das erwartete Format.';
$string['timerstagesetting'] = 'Zeitlimit-Phasen';
$string['timerstagesetting_help'] = 'Jede Zeile definiert in der Reihenfolge eine Zeitphase. Die Zeile beginnt mit der Zeit im Format HH:MM:SS, gefolgt von einem Leerzeichen; der Rest der Zeile ist der Name dieser Phase.
Beispiel:

03:00:00 Hauptzeit<br>
00:30:00 Abgabezeit<br>
00:30:00 Notfall-Zusatzzeit';
$string['timerstageunavailable'] = 'Nicht verfügbar';
