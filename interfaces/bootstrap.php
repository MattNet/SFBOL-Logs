#!/usr/bin/php -q
<?php
/*****
Sample file to use as a template for bootstrapping a new interface of the 
LogFile/LogUnit libraries.

*****/

###
# Initialization
###
include_once("../LogFile.php");


###
# CLI Configuration
###

$CLIoptions = "";
$CLIoptions .= "o::h";
$CLIlong = array("out::", "help");

$CLI = getopt($CLIoptions, $CLIlong, $rest_index);
$CLIend = array_slice($argv, $rest_index);

# Adjust configuration from CLI
if (isset($CLI["o"])) $OUTFILE = (int)$CLI["o"];
if (isset($CLI["out"])) $OUTFILE = (int)$CLI["out"];
if (isset($CLI["h"]) || isset($CLI["help"])) errorOut("");

if ($CLI === false || empty($CLIend)) errorOut("Could not read command line arguments.");

###
# Input file handling
###
$readFile = $CLIend[0];
if (!isset($readFile) || !is_file($readFile) || !is_readable($readFile)) errorOut("Cannot read file '$readFile'.");

$input = file_get_contents($readFile);
if ($input === false) errorOut("Failed to read '$readFile'.");

###
# Initialize parser
###
$log = new LogFile($input);
if ($log->error != "") {
  echo $log->error;
  exit(1);
}

$unitCache = $log->get_units();
$unitList = array();
$LastLine = 0;

// Get the last line of the log sequence
foreach ($unitCache as $unit)
  if ($unit["removed"] > $LastLine) $LastLine = $unit["removed"];

/***
The output of $log->get_units() depends on the logfile, but is similar to this:

Array
(
    [0] => Array (
            [added] => 0
            [basic] => ship
            [name] => Anchor Wax
            [removed] => 37
            [type] => Wyn TAxBC
        )
    [1] => Array (
            [added] => 0
            [basic] => ship
            [name] => Vudoo
            [removed] => 37
            [type] => Vudar TCA (Playtest)
        )
    [2] => Array (
            [added] => 19
            [basic] => drone
            [name] => D001(1).1.19
            [removed] => 29
            [type] => Wyn TAxBC Drone
        )
    [3] => Array (
            [added] => 19
            [basic] => drone
            [name] => D002(A).1.19
            [removed] => 29
            [type] => Wyn TAxBC Drone
        )
    [4] => Array (
            [added] => 32
            [basic] => plasma
            [name] => DC(20).1.32
            [removed] => 36
            [type] => Wyn TAxBC Plasma
        )
    [5] => Array (
            [added] => 33
            [basic] => shuttle
            [name] => S01.2.1
            [removed] => 37
            [type] => Wyn TAxBC Shuttle
        )
)
***/

$sequence = $log->get_sequence();
/***
Value of $sequence never varies between log files and is:
Array
(
    [0] => SEQUENCE_MOVEMENT_SHIPS
    [1] => SEQUENCE_MOVEMENT_SHUTTLES
    [2] => SEQUENCE_MOVEMENT_SEEKERS
    [3] => SEQUENCE_MOVEMENT_TAC
    [4] => SEQUENCE_ESG_DAMAGE
    [5] => SEQUENCE_ENVELOPER_DAMAGE
    [6] => SEQUENCE_SEEKER_DAMAGE
    [7] => SEQUENCE_WEB_DAMAGE
    [8] => SEQUENCE_BREAKDOWNS
    [9] => SEQUENCE_SPEED_CHANGES
    [10] => SEQUENCE_THOLIAN_WEB_PASS
    [11] => SEQUENCE_EMER_DECEL_EFFECT
    [12] => SEQUENCE_VOLUNTARY_FIRE_CONTROL
    [13] => SEQUENCE_CLOAKING_DEVICE
    [14] => SEQUENCE_TRACTORS
    [15] => SEQUENCE_LABS
    [16] => SEQUENCE_LAUNCH_PLASMA
    [17] => SEQUENCE_LAUNCH_DRONES
    [18] => SEQUENCE_ESGS
    [19] => SEQUENCE_DROP_SHIELDS
    [20] => SEQUENCE_TRANSPORTERS
    [21] => SEQUENCE_MINES_ACTIVE
    [22] => SEQUENCE_LAND_SHUTTLES
    [23] => SEQUENCE_LAUNCH_SHUTTLES
    [24] => SEQUENCE_ANNOUNCE_EMER_DECEL
    [25] => SEQUENCE_DIS_DEV_DECLARATION
    [26] => SEQUENCE_FIRE_DECLARATION
    [27] => SEQUENCE_PPDS
    [28] => SEQUENCE_FIRST_HELLBORES
    [29] => SEQUENCE_DIRECT_FIRE
    [30] => SEQUENCE_SECOND_HELLBORES
    [31] => SEQUENCE_CAST_WEB
    [32] => SEQUENCE_DAMAGE_ALLOCATION
    [33] => SEQUENCE_DIS_DEV_OPERATE
    [34] => SEQUENCE_IMPULSE_END
)
***/


$weapons = $log->get_weapons( $unitCache[1]["name"] );
/***
Value of $weapons depends on the unit argument, but is like this:
Array
(
    [0] => Array
        (
            [arc] => RS
            [id] => 9
            [weapon] => Phaser-3
        )
    [1] => Array
        (
            [arc] => RS
            [id] => 10
            [weapon] => Phaser-3
        )
    [2] => Array
        (
            [arc] => FA
            [id] => A
            [weapon] => Ion Cannon
        )
    [3] => Array
        (
            [arc] => FA
            [id] => B
            [weapon] => Ion Cannon
        )
    [4] => Array
        (
            [arc] => FX
            [id] => C
            [weapon] => Ion Cannon
        )
    [5] => Array
        (
            [arc] => FX
            [id] => D
            [weapon] => Ion Cannon
        )
    [6] => Array
        (
            [arc] => FA
            [id] => 1
            [weapon] => Phaser-1
        )
    [7] => Array
        (
            [arc] => FA
            [id] => 2
            [weapon] => Phaser-1
        )
    [8] => Array
        (
            [arc] => FA
            [id] => 3
            [weapon] => Phaser-1
        )
    [9] => Array
        (
            [arc] => FA
            [id] => 4
            [weapon] => Phaser-1
        )
    [10] => Array
        (
            [arc] => FA+L
            [id] => 5
            [weapon] => Phaser-1
        )
    [11] => Array
        (
            [arc] => FA+R
            [id] => 6
            [weapon] => Phaser-1
        )
    [12] => Array
        (
            [arc] => LS
            [id] => 7
            [weapon] => Phaser-3
        )
    [13] => Array
        (
            [arc] => LS
            [id] => 8
            [weapon] => Phaser-3
        )
    [14] => Array
        (
            [arc] => RA+L
            [id] => 11
            [weapon] => Phaser-2
        )
)
***/

###
# Iterate through impulses
###
$frameIteration = 0;
for ($i = 0; $i <= $LastLine; $i++) { // $i = impulse in numeric notation
  $impulse = LogUnit::convertFromImp($i); // $impulse = impulse in 'turn.impulse" notation
  $impulseData = $log->read($impulse);

/***
Put impulse-by-impulse code here
***/

/***
$impulse Data format varies, but is similar to this:

Array
(
    [0] => Array // Key per $log->get_sequence() is SEQUENCE_MOVEMENT_SHIPS
        (
            [0] => Array // unit moves forward (direction 'A')
                (
                    [facing] => A
                    [location] => 3216
                    [owner] => Anchor Wax
                    [speed] => 30
                    [turn] => move
                ),
            [1] => Array // unit side-slipping (moving sideways without a facing change.) Direction of sideslip is known by comparing to last location
                (
                    [facing] => D
                    [location] => 2726
                    [owner] => Vudoo
                    [speed] => 16
                    [turn] => side-slip
                )
        )
    [2] => Array // Key per $log->get_sequence() is SEQUENCE_MOVEMENT_SEEKERS
        (
            [0] => Array // seeking unit changes facing to 'F' then moves forward
                (
                    [facing] => F
                    [location] => 3111
                    [owner] => D005(3).1.24
                    [speed] => 20
                    [turn] => turn
                )
        )
    [9] => Array // Key per $log->get_sequence() is SEQUENCE_SPEED_CHANGES
        (
            [0] => Array // unit changing speed. Next impulse moves at the new speed
                (
                    [owner] => Anchor Wax
                    [speed] => 24
                )
        )
    [17] => Array // Key per $log->get_sequence() is SEQUENCE_LAUNCH_DRONES
        (
            [0] => Array // drone being launched
                (
                    [add] => 19
                    [facing] => A
                    [location] => 3216
                    [speed] => 20
                    [type] => Wyn TAxBC Drone
                    [owner] => D001(1).1.19
                )
        )
    [23] => Array // Key per $log->get_sequence() is SEQUENCE_LAUNCH_SHUTTLES
        (
            [0] => Array // Shuttle being launched
                (
                    [add] => 33
                    [facing] => E
                    [location] => 3507
                    [speed] => 6
                    [type] => Wyn TAxBC Shuttle
                    [owner] => S01.2.1
                )
        )
    [26] => Array // Key per $log->get_sequence() is SEQUENCE_FIRE_DECLARATION
        (
            [0] => Array // weapons fire from unit
                (
                    [arc] => RS
                    [id] => 9
                    [owner] => Vudoo
                    [owner location] => 3010
                    [range] => 1
                    [target] => D005(3).1.24
                    [weapon] => Phaser-3
                )
        )
    [32] => Array // Key per $log->get_sequence() is SEQUENCE_DAMAGE_ALLOCATION
        (
            [0] => Array // damage to unit
                (
                    [direction] => Array
                        (
                            [F] => 1
                        )
                    [internals] => 9
                    [owner] => Anchor Wax
                    [owner location] => 3507
                    [reinforcement] => 5
                    [shields] => 30
                    [total] => 44
                )
        )
    [34] => Array // Key per $log->get_sequence() is SEQUENCE_IMPULSE_END
        (
            [0] => Array // removes unit
                (
                    [add] => 24
                    [owner] => D005(3).1.24
                    [remove] => 19
                    [type] => Wyn TAxBC Drone
                )
        )
)
***/

  echo $log->get_unit_facing( $unitCache[1]["name"], $impulse ); // result is "D"
  echo $log->get_unit_location( $unitCache[1]["name"], $impulse ); // result is "1701"
  echo $log->get_unit_range( $unitCache[0]["name"], $unitCache[1]["name"], $impulse ); // result is "33"

  $trailLength = 4;
  print_r( $log->get_unit_location_trail( $unitCache[1]["name"], $impulse, $trailLength ) );
/***
Output of $log->get_unit_location_trail is in this format, where number of entries is equal to $trailLength
Array
   (
     [0] => "1701" // Impulse -3 Location
     [1] => "1701" // Impulse -2 Location
     [2] => "1702" // Impulse -1 Location
     [3] => "1703" // Latest Impulse Location
   )
***/

}

###
# CLI help output
###
function errorOut($message) {
  global $argv, $FRAMESPERACTION, $FRAMESFORMOVE, $MOVEMENTTRAILS;
  echo "\n";
  if ($message !== null && $message != "") echo $message . "\n\n";
  echo "Usage:\n  {$argv[0]} [OPTIONS..] /path/to/log\n";
  echo "Options:\n";
  echo "  -0, --out <FILE>    Write to this file\n";
  echo "  -h, --help          Show this help message\n";
  exit(1);
}
?>

