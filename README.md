# SFBOL-Logs
This converts a log file and chat transcript from the Star Fleet Battles online game and normalizes it as a PHP object. This allows for the output of the object to be used in other scripts.
Expected use-cases would be: 
* Create an interface to some user interface software (not to play a game, but to show a game)
* Interface with art software to graphically show each game (such as to recreate certain rules interactions or unit interactions)

# Star Fleet Battles Online
[Star Fleet Battles Online](http://www.sfbonline.com/) is a subscription service to play Star Fleet Battles across the internet. It is currently the single largest Star Fleet Battles community that I know of. Officially sanctioned tournaments are often held there and campaign games are regularly played on that site.
It also hosts Federation Commander Online.

# Star Fleet Battles
[Star Fleet Battles](http:www.starfleetgames.com "Amarillo Design Board website") is a Star Trek wargame that pits several of the empires that people know and love, against eachother. Originally released in the '70s, it has a long game history that diverges from the 'official' paramount history at the animated series and before the movies. This has freed them to add new dynamics between empires, create their own empires, and give a rich breadth to the wargaming experience.

# Usage
```
#!/usr/bin/php -q
<?php
/*****
Extracts log info from a SFBOL log file.
- Shows the units created in the file
- Shows the events of impulse 25 of turn 1
 ****/

include_once( "./LogFile.php" );
$file = file_get_contents( $argv[1] );
$log = new LogFile( $file );

echo "Units:\n";
print_r( $log->get_units() );
print_r( $log->read("1.25") );
print_r( $log->get_sequence() );

?>
```

```
Units:
Array
(
    [0] => Array
        (
            [name] => Big Cat
            [type] => Lyran TCC
            [basic] => ship
        )
    [1] => Array
        (
            [name] => closerun
            [type] => Hydran TLM
            [basic] => ship
        )
    [2] => Array
        (
            [name] => S01.1.7
            [type] => Hydran Fighter
            [basic] => shuttle
        )
    [3] => Array
        (
            [name] => S02.1.8
            [type] => Hydran Fighter
            [basic] => shuttle
        )
    [4] => Array
        (
            [name] => ESG #A
            [type] => Lyran ESG
            [basic] => esg
        )
    [5] => Array
        (
            [name] => 
            [type] => Hydran Fighter
            [basic] => shuttle
        )
)
Array
(
    [0] => Array
        (
            [0] => Array
                (
                    [0] => D
                    [1] => side-slip
                    [owner] => Big Cat
                )
            [1] => Array
                (
                    [0] => 1814
                    [owner] => Big Cat
                )
            [2] => Array
                (
                    [0] => A
                    [1] => move
                    [owner] => closerun
                )
            [3] => Array
                (
                    [0] => 1722
                    [owner] => closerun
                )
        )
    [26] => Array
        (
            [0] => Array
                (
                    [0] => Array
                        (
                            [0] => Disruptor #A
                            [1] => closerun
                        )
                    [1] => Array
                        (
                            [0] => Disruptor #B
                            [1] => closerun
                        )
                    [2] => Array
                        (
                            [0] => Disruptor #C
                            [1] => closerun
                        )
                    [3] => Array
                        (
                            [0] => Disruptor #D
                            [1] => closerun
                        )
                    [4] => Array
                        (
                            [0] => Phaser-1 #1
                            [1] => S01.1.7
                        )
                    [5] => Array
                        (
                            [0] => Phaser-1 #2
                            [1] => S01.1.7
                        )
                    [6] => Array
                        (
                            [0] => Phaser-1 #5
                            [1] => S01.1.7
                        )
                    [7] => Array
                        (
                            [0] => Phaser-1 #6
                            [1] => S02.1.8
                        )
                    [8] => Array
                        (
                            [0] => Phaser-1 #7
                            [1] => S02.1.8
                        )
                    [9] => Array
                        (
                            [0] => Phaser-1 #8
                            [1] => S02.1.8
                        )
                    [owner] => Big Cat
                )
        )
    [32] => Array
        (
            [0] => Array
                (
                    [0] => 19
                    [1] => 0
                    [owner] => closerun
                )
        )
    [34] => Array
        (
            [0] => Array
                (
                    [0] => S02.1.8
                    [owner] => S02.1.8
                )
        )
)
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
```
