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
- Shows the indicies to the impulse sequence
 ****/

include_once( "./LogFile.php" );
$file = file_get_contents( $argv[1] );
$log = new LogFile( $file );

echo "Units:\n";
print_r( $log->get_units() );

echo "Impulses:";
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
            [added] => 0
            [basic] => ship
            [name] => Ballerina
            [removed] => 64
            [type] => Romulan TKR
        )

    [1] => Array
        (
            [added] => 0
            [basic] => ship
            [name] => liard
            [removed] => 64
            [type] => Gorn TCC
        )

    [2] => Array
        (
            [added] => 17
            [basic] => plasma
            [name] => liard-PA(60).1.17
            [removed] => 33
            [type] => Gorn Plasma
        )

    [3] => Array
        (
            [added] => 27
            [basic] => plasma
            [name] => Ballerina-PA(60).1.27
            [removed] => 33
            [type] => TKR Plasma
        )

    [4] => Array
        (
            [added] => 60
            [basic] => plasma
            [name] => liard-PB(30).2.28
            [removed] => 64
            [type] => Gorn Plasma
        )

    [5] => Array
        (
            [added] => 63
            [basic] => plasma
            [name] => Ballerina-PB(60).2.31
            [removed] => 64
            [type] => TKR Plasma
        )

)

Impulses:
Array
(
    [0] => Array
        (
            [0] => Array
                (
                    [facing] => D
                    [location] => 1712
                    [owner] => Ballerina
                    [turn] => move
                )

            [1] => Array
                (
                    [facing] => A
                    [location] => 2517
                    [owner] => liard
                    [turn] => side-slip
                )

        )

    [9] => Array
        (
            [0] => Array
                (
                    [owner] => Ballerina
                    [speed] => 30
                )

        )

    [32] => Array
        (
            [0] => Array
                (
                    [internals] => 0
                    [owner] => Ballerina
                    [reinforcement] => 0
                    [shields] => 1
                    [total] => 1
                )

        )

    [26] => Array
        (
            [0] => Array
                (
                    [arc] => FA+L
                    [id] => 1
                    [owner] => liard
                    [target] => Ballerina
                    [weapon] => Phaser-1
                )

            [1] => Array
                (
                    [arc] => FA+R
                    [id] => 2
                    [owner] => liard
                    [target] => Ballerina
                    [weapon] => Phaser-1
                )

        )

    [3] => Array
        (
            [0] => Array
                (
                    [facing] => F
                    [location] => 2216
                    [owner] => ECPA(30).1.17
                    [turn] => move
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
