#!/usr/bin/php -q
<?php
/*****
Extracts movement info from a SFBOL log file and creates a Blender python script
 
Actions per impulse go:
  - Number $FRAMESFORMOVE frames to animate movement
  - Number $FRAMESPERACTION frames to animate damage due to movement and also to note launches
  - Number $FRAMESPERACTION frames to animate fire and note damage
  - Total number of frames per impulse is ($FRAMESFORMOVE) + ($FRAMESPERACTION * 2)
    Unless the '-no_action' argument is given. Then each impulse is no less than $FRAMESFORMOVE long

This assumes that the python script will be run in a blender file that contains:
- A [hex-map] surface who's top layer is at z=0
- An existing model for everything that is needed (presumably off-screen.)
    See the MODEL_NAME constant for the names of those models
- The operator will supply camera and lights, plus animate those as needed
****/

###
# Lookup table for unit-type (per the log file) to named Blender model
# e.g. "Gozilla (Type:Gorn TCC) has been added at ..."
#                ^^^^^^^^^^^^^
###
define( 'MODEL_NAME', array(
# Ships
  'Andromedan Krait (Official)' => array( "name" => 'Andromedan KRA', 'no_rotate' => false ),
  'Archeo-Tholian TCC' => array( "name" => 'Tholian ATC', 'no_rotate' => false ),
  'Fed TCC (G-Rack) (Playtest)' => array( "name" => 'Federation TCA', 'no_rotate' => false ),
  'Fed TCC (Official' => array( "name" => 'Federation TCA', 'no_rotate' => false ),
  'Frax TC (Playtest)' => array( "name" => 'Frax TCA', 'no_rotate' => false ),
  'Gorn TCC' => array( "name" => 'Gorn TCA', 'no_rotate' => false ),
  'Hydran TLM' => array( "name" => 'Hydran TCC', 'no_rotate' => false ),
  'ISC TCC' => array( "name" => 'ISC CA', 'no_rotate' => false ),
  'Klingon TD7C' => array( "name" => 'Klingon D7CT', 'no_rotate' => false ),
  'Kzinti TCC' => array( "name" => 'Kzinti TCC', 'no_rotate' => false ),
  'LDR TCWL' => array( "name" => 'LDR TCWL', 'no_rotate' => false ),
  'Lyran TCC' => array( "name" => 'Lyran TCA', 'no_rotate' => false ),
  'Orion TBR' => array( "name" => 'Orion TBR', 'no_rotate' => false ),
  'Romulan TFH' => array( "name" => 'Romulan TFH', 'no_rotate' => false ),
  'Romulan TKE' => array( "name" => 'Romulan TKE', 'no_rotate' => false ),
  'Romulan TKR' => array( "name" => 'Romulan TKR', 'no_rotate' => false ),
  'Seltorian TCA' => array( "name" => 'Seltorian TCA', 'no_rotate' => false ),
  'Vudar TCA' => array( "name" => 'Vudar TCA', 'no_rotate' => false ),
  'Wyn GBS' => array( "name" => 'WYN GBS', 'no_rotate' => false ),
  'Wyn TAxBC' => array( "name" => 'WYN AuxBCT', 'no_rotate' => false ),
# Expendables
  'Andromedan Mine' => array( "name" => 'Mine', 'no_rotate' => true ),
  'Archeo-Tholian Web' => array( "name" => 'Web', 'no_rotate' => true ),
  'Archeo-Tholian Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Fed Drone' => array( "name" => 'Drone', 'no_rotate' => false ),
  'Fed Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Frax Drone' => array( "name" => 'Drone', 'no_rotate' => false ),
  'Frax Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Gorn Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'Gorn Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Hydran Fighter' => array( "name" => '', 'no_rotate' => false ), # No Stinger Model
  'Hydran Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'ISC Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'ISC Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Klingon Drone' => array( "name" => 'Drone', 'no_rotate' => false ),
  'Klingon Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'LDR ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'LDR Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Lyran ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'Lyran Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Orion Drone' => array( "name" => 'Drone', 'no_rotate' => false ),
  'Orion ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'Orion Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'Orion Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'TFE Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'TKE Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'TKR Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'Romulan Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Vudar Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
  'Wyn TAxBC Drone' => array( "name" => 'Drone', 'no_rotate' => false ),
  'WYN ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'WYN Plasma' => array( "name" => 'Plasma', 'no_rotate' => false ),
  'Wyn TAxBC Shuttle' => array( "name" => 'Federation Shuttle', 'no_rotate' => false ),
# Misc
  'Andromedan DisDev' => array( "name" => 'DisDev Marker', 'no_rotate' => true ),
  'Card' => array( "name" => 'Card', 'no_rotate' => true ),
  'CamCard' => array( "name" => 'Camera Title', 'no_rotate' => true ),
  'Front Shield' => array( "name" => 'shield.front', 'no_rotate' => false ),
  'Left Shield' => array( "name" => 'shield.left', 'no_rotate' => false ),
  'Rear Shield' => array( "name" => 'shield.rear', 'no_rotate' => false ),
  'Right Shield' => array( "name" => 'shield.right', 'no_rotate' => false ),
) );

# these are configuration variables
$FILESUFFIX = ".py";
$FRAMESPERACTION = 12; # How long to animate the actions. Launching is one action, fire is another action
$FRAMESFORMOVE = 12; # How long to animate the movement. Total impulse length is this plus two actions
$NOANIMATION = false; # if false, then wait the $FRAMESPERACTION time on impulses where there is no action
$XHEXSIZE = 0.9;
$XOFFSET = 0;
$YHEXSIZE = -1;
$YOFFSET = 0;

# Allowed command line options
$CLIoptions = "";
$CLIoptions .= "a::"; // adjust action animation time
$CLIoptions .= "m::"; // adjust move animation time
$CLIoptions .= "h"; // give command help
$CLIoptions .= "q"; // quiet the command line
$CLIoptions .= "x"; // remove action time for impulses without it
$CLIlong = array(
  "action::",
  "move::",
  "help",
  "no_action",
  "quiet",
);

$CLI = getopt( $CLIoptions, $CLIlong, $rest_index );
$CLIend = array_slice( $argv, $rest_index );
if( $CLI === false || empty($CLIend) ) errorOut( "Could not read command line arguments." );

# Some command-line arguments change the configuration
if( isset($CLI["a"]) )
  $FRAMESPERACTION = (int) $CLI["a"];
if( isset($CLI["action"]) )
  $FRAMESPERACTION = (int) $CLI["action"];
if( isset($CLI["h"]) || isset($CLI["help"]) )
  errorOut( "" );
if( isset($CLI["m"]) )
  $FRAMESFORMOVE = (int) $CLI["m"];
if( isset($CLI["move"]) )
  $FRAMESFORMOVE = (int) $CLI["move"];
if( isset($CLI["x"]) || isset($CLI["no_action"]) )
  $NOANIMATION = true;

# Internal variables
$flagWasActivity = false; # If true, add two $FRAMESPERACTION to $frameIncrement
$frame = 0; # last frame referenced
$frameIncrement = 0; # Increments the number of frames used already. Important for $NOANIMATION
$hexVertBump = 0; # Used to vertically offset alternating hexes
$impulseActivity = array(); # this holds the data from the current impulse
$LastLine = 0; # the last line of the log (extracted from the first (ship) unit)
$output = "import bpy\nimport mathutils\nfrom mathutils import *; from math import *\n";
$output .= "\n#####\n# Impulses are ";
if( $NOANIMATION )
  $output .= "either $FRAMESFORMOVE or ";
$output .= ($FRAMESFORMOVE + ($FRAMESPERACTION * 2))." frames long.\n";
$output .= "# - Movement takes $FRAMESFORMOVE frames.\n";
$output .= "# - Early-impulse actions are animated for $FRAMESPERACTION frames.\n";
$output .= "# - Weapons fire is animated for $FRAMESPERACTION frames.\n";
if( $NOANIMATION )
  $output .= "# - Impulses with no activity will skip the activity segments, but not the move segment.\n";
$output .= "#####\n\n";
$output .= "for obj in bpy.data.objects:\n   obj.select_set(False)\n\n";
$phaserMaterial = "Phaser Blast"; # Blender name of the texture to give the weapon blast
$readFile = $CLIend[0];
$Ships = array();
$ShipsFacings = array(); # track the unit facing before the current move
$ShipsNames = array();
$unitList = array(); # list of all units in the battle
$UnknownUnits = array(); # units added, but not defined. Collected for debug output
$UnknownUnitsImpulse = array(); # units added, but not defined. Collected for debug output
$writeFile = $readFile.$FILESUFFIX;

if( ! isset($readFile) || ! is_readable($readFile) ) errorOut( "Cannot read file '$readFile'." );

$FRAMESFORMOVE -= 1; # reduce this now, since it will be used in a reduced fashion for the rest of the script

include_once( "../LogFile.php" );

# read the input file
$input = file_get_contents( $readFile );
if( $input === false )
{
  echo "Could not retrieve contents of '$readFile'\n";
  exit(1);
}

# Parse the input file
$log = new LogFile( $input );
if( $log->error != "" )
{
  echo $log->error;
  exit(1);
}

###

# Get a list of units
# $unitCache[ index ][
#   "added" => impulse_created,
#   "basic" => "basic type",
#   "name" => "name in log file",
#   "removed" => impulse_destroyed,
#   "type" => "MODEL_NAME index"
# ]
$unitCache = $log->get_units();

# Get the python access method for each unit
foreach( $unitCache as &$entry )
{
  $entry["blender"] = "bpy.data.objects['".$entry["name"]."']";
  $ShipsFacings[ $entry["name"] ] = "D"; // This is the model's initial facing. 'A' is pointing in the +Y direction

  $entry["no_rotate"] = MODEL_NAME[$entry["type"]]["no_rotate"];

  # Assign $unitList from $unitCache, but indicies are the unit name
  $unitList[ $entry["name"] ] = $entry;

# $unitList[ "name in log file" ][
#   "added" => impulse_created,
#   "basic" => "basic type",
#   "blender" => "bpy.data.objects[ 'name in log file' ]",
#   "name" => "name in log file",
#   "no_rotate" => should this never turn: true/false,
#   "removed" => impulse_destroyed,
#   "type" => "MODEL_NAME index"
# ]

  # update $LastLine
  if( $LastLine < $entry["removed"] )
    $LastLine = $entry["removed"];

# duplicate templated items in Blender to create named items to be moved
  $output .= blender_duplicate( MODEL_NAME[$entry["type"]]["name"] );
  $output .= "obj.name = '".$entry["name"]."'\n";
  $output .= "obj.hide_render = False\n";
  $output .= "obj.select_set(False)\n\n";
}

# Create the phaser weapon-fire texture
$output .= "# Phaser weapon-fire texture\n";
$output .= "Phaser_Material = bpy.data.materials.new(name=\"$phaserMaterial\")\n";
$output .= "Phaser_Material.use_nodes = True\n";
$output .= "Phaser_nodes = Phaser_Material.node_tree.nodes\n";
$output .= "Phaser_links = Phaser_Material.node_tree.links\n";
$output .= "Phaser_nodes[\"Principled BSDF\"].inputs[0].default_value = (1, 0, 0, 1)\n";
$output .= "Phaser_nodes[\"Principled BSDF\"].inputs[26].default_value = (1, 0, 0, 1)\n";
$output .= "Phaser_nodes[\"Principled BSDF\"].inputs[27].default_value = 2\n\n";

# go through each impulse
for( $i=0; $i<=$LastLine; $i++ )
{
# Get the activity for this impulse
  $impulseActivity = $log->read( LogUnit::convertFromImp( $i ) );
  $frame = $frameIncrement;
  $flagWasActivity = false;

  $output .= "# Start of impulse ".LogUnit::convertFromImp( $i ).", animation frame $frame\n\n";

//  $output .= impulse_display( $i ); # Update the incrementing time display

  # skip if nothing happened here
  if( empty($impulseActivity) )
  {
    $frameIncrement += $FRAMESFORMOVE; # Every movement segment is tracked
    continue;
  }
# Iterate through the impulse sequences
  foreach( $impulseActivity as $sequence=>$actionSet )
  {

    if( $sequence <= LogFile::SEQUENCE_MOVEMENT_TAC )
    {
      foreach( $actionSet as $action )
      {
# Change location
        if( isset($action["location"]) )
        {
        # a movement order
# action [
#     [facing] => C
#     [location] => 2417
#     [owner] => peon
#     [turn] => side-slip
# ]
          list( $XLoc, $YLoc ) = locationPixels( $action["location"] );
          $rot = 0;

          # if the unit never turns, skip determining rotation amount
          if( ! $unitList[ $action["owner"] ]["no_rotate"] )
            $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);

          $output .= "# Move ".$action["owner"]."\n";

          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];
        }
# Change facing
        # if the facing changes between old and new facing
        else if( $ShipsFacings[ $action["owner"] ] != $action["facing"] && ! $unitList[ $action["owner"] ]["no_rotate"] )
        {
# action [
#     [facing] => C
#     [owner] => peon
#     [turn] => 5
# ]
          $output .= "# Change facing of ".$action["owner"]."\n";
          $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], null, null, $rot );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];

          # Add a Card to decribe TAC or HET
          if( isset($action["turn"]) && ( $action["turn"] == "TAC" || $action["turn"] == "HET" ))
            $output .= card_set( $action["turn"], $XLoc, $YLoc, $frame, $FRAMESFORMOVE );
        }
      }
    }
    else if( $sequence < LogFile::SEQUENCE_DIS_DEV_DECLARATION )
    {
# Launch items
      foreach( $actionSet as $action )
      {
      # if we are adding an unit to the map
        if( $sequence == LogFile::SEQUENCE_LAUNCH_PLASMA ||
            $sequence == LogFile::SEQUENCE_LAUNCH_DRONES ||
            $sequence == LogFile::SEQUENCE_ESGS ||
            $sequence == LogFile::SEQUENCE_TRANSPORTERS ||
            $sequence == LogFile::SEQUENCE_LAND_SHUTTLES ||
            $sequence == LogFile::SEQUENCE_LAUNCH_SHUTTLES
          )
        {
# $action [
#     [Add] => 34
#     [facing] => D
#     [location] => 2416
#     [speed] => 6
#     [type] => Lyran Shuttle
#     [owner] => S04.2.2
# ]
          $rot = 0;
          list( $XLoc, $YLoc ) = locationPixels( $action["location"] );

          $output .= "# Launch/land ".$unitList[ $action["owner"] ]["basic"]."\n";

          # Set the initial rotation/location
          # if the unit never turns, skip determining rotation amount
          if( ! $unitList[ $action["owner"] ]["no_rotate"] )
            $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot, $FRAMESFORMOVE, true );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];

          # Announce the action
          $output .= card_set( $unitList[ $action["owner"] ]["basic"]." Launch", $XLoc, $YLoc, $frame+$FRAMESFORMOVE, $FRAMESPERACTION );

          # Flag that we had impulse activity
          $flagWasActivity = true;
         }
# if we are tractoring a unit
        if( $sequence == LogFile::SEQUENCE_TRACTORS )
        {
# $action [
#     [owner] => Master blaster
#     [owner location] => 1234
#     [target] => D013(C).2.25
#     [tractorup] => 79
# ]
          # Get the blender locations of the aggressor and defender
          $targetLocation = $log->get_unit_location( $action["target"], $i );

          $output .= "# ".$action["owner"]." tractors ".$action["target"]."\n";
          $output .= make_tractor( $action["owner location"], $targetLocation, $frame+$FRAMESFORMOVE );

          # Announce the action
          $output .= card_set( "Tractor", $XLoc, $YLoc, $frame+$FRAMESFORMOVE, $FRAMESPERACTION );

          # Flag that we had impulse activity
          $flagWasActivity = true;
        }
# if we are cloaking a unit
        if( $sequence == LogFile::SEQUENCE_CLOAKING_DEVICE )
        {
# $action [
#     [owner] => Dancer
#     [owner location] => 1234
# ]
          $output .= "# ".$action["owner"]." cloaks\n";
          $output .= make_cloak( $action["owner location"], $frame+$FRAMESFORMOVE );

          # Announce the action
          $output .= card_set( "Cloak", $XLoc, $YLoc, $frame+$FRAMESFORMOVE, $FRAMESPERACTION );

          # Flag that we had impulse activity
          $flagWasActivity = true;
        }
      }
    }
# Firing
    else if( $sequence < LogFile::SEQUENCE_IMPULSE_END )
    {
      foreach( $actionSet as $action )
      {
        if( $sequence == LogFile::SEQUENCE_CAST_WEB )
        {
# $action [
#     [add] => 29
#     [facing] => A
#     [location] => 2823
#     [owner] => web-1.29-1
#     [speed] => 0
#     [type] => Archeo-Tholian Web
# ]
# $action [
#     [add] => 29
#     [owner] => web-1.29-1
#     [remove] => 110
#     [type] => Archeo-Tholian Web
# ]
          if( isset( $action["remove"] ) && $action["remove"] == $i ) # web is being removed
          {
            $output .= "# Remove web\n";

            # Set the initial location
            $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], 0, 0, 0, $FRAMESFORMOVE+$FRAMESPERACTION, true, -12 );
          }
          else # web is being added
          {
            $output .= "# Create web\n";

            # Set the initial location
            list( $XLoc, $YLoc ) = locationPixels( $action["location"] );
            $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot, $FRAMESFORMOVE+$FRAMESPERACTION, true );
          }

          # Flag that we had impulse activity
          $flagWasActivity = true;
        }
        else if( isset($action["weapon"]) )
        {
# $action [
#     [arc] => LS
#     [id] => 5
#     [owner] => peon
#     [owner location] => 1234
#     [range] => 2
#     [target] => Andy
#     [weapon] => Phaser-1
# ]
          # Exclude non-weapons fire: web, etc
          if( $action["weapon"] == "Web Caster" )
            break;

          # Get the blender locations of the aggressor and defender
          $targetLocation = $log->get_unit_location( $action["target"], $i );

          $output .= "# ".$action["owner"]." fires on ".$action["target"]."\n";
          $output .= make_phaser( $action["owner location"], $targetLocation, $frame+$FRAMESFORMOVE+$FRAMESPERACTION );

          # Flag that we had impulse activity
          $flagWasActivity = true;
        }
# Receive damage
        else if( isset($action["total"]) )
        {
# $action [
#     [direction] => [ "A"=>TRUE ]
#     [internals] => 7
#     [owner] => ncc1792
#     [owner location] => 1234
#     [reinforcement] => 0
#     [shields] => 3
#     [total] => 10
# ]
          list( $XLoc, $YLoc ) = locationPixels( $action["owner location"] );

          # Announce the action
          $msg = $action["shields"];
          if( $action["internals"] > 0 )
            $msg .= "+".$action["internals"];
          $output .= card_set( $msg, $XLoc, $YLoc, $frame+$FRAMESFORMOVE+$FRAMESPERACTION, $FRAMESPERACTION );

          # draw the shields
          if( $action["shields"] > 0 )
            foreach( $action["direction"] as $dir=>$value )
              make_shield( $XLoc, $YLoc, $dir , $frame+$FRAMESFORMOVE+$FRAMESPERACTION, $FRAMESPERACTION );
        }
      }
    }
# End of impulse
    else
    {
      foreach( $actionSet as $action )
      {
# $action [
#     [add] => 25
#     [owner] => D001(1).1.25
#     [remove] => 42
#     [type] => Wyn TAxBC Drone
# ]
        # "remove" the unit (move off the map)
        $output .= "# Remove ".$action["owner"]."\n";
        $rot = rotation($ShipsFacings[ $action["owner"] ], "A");
        $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot, $FRAMESFORMOVE + (2 * $FRAMESPERACTION), true, "-10.0" );
        $ShipsFacings[ $action["owner"] ] = "A";

        # Flag that we impulse activity
        $flagWasActivity = true;
      }
    }
  }

  $frameIncrement += $FRAMESFORMOVE; # Every movement segment is tracked
  if( $flagWasActivity || ! $NOANIMATION )
    $frameIncrement += ( 2 * $FRAMESPERACTION ); # Track impulse activity if there was some
}

# Handle the surrender
$output .= make_flag( "13", "-14", $frameIncrement, (2 * $FRAMESPERACTION) );
$frameIncrement += ( 2 * $FRAMESPERACTION ); # Add time for the flag waving

# set the length of the animation
# This is assuredly too large if $NOANIMATION is true
$output .= "bpy.context.scene.frame_end = $frameIncrement\n";
$output .= "bpy.context.scene.frame_set(0)\n";

if( ! isset($CLI["q"]) && ! isset($CLI["quiet"]) )
{
  echo "###\nDebug Info:\n###\n";
  echo "Unit List:\n";
  print_r( $unitList );
  echo "Animation length: $frameIncrement frames, ".floor( $frameIncrement / 24 )." seconds, lasts until T".LogUnit::convertFromImp( $LastLine )."\n\n";
}

# write the new file
$status = file_put_contents( $writeFile, $output );
if( ! $status )
{
  echo "Failed write of '$writeFile'\n\n";
  exit(1);
}

exit(0);

####
# Function Declarations
####

###
# Determines the amount of rotation, in degrees
###
# Args are:
# - (int) The original facing of the unit
# - (int) The new facing of the unit
# Returns:
# - (int) the amount of rotation in degrees. '+' is clockwise, '-' is CCW
###
function rotation( $old, $new )
{
  $oldNum = ord(strtolower($old))-96;
  $newNum = ord(strtolower($new))-96;
  $letterDistance = 5; # ord('f') - ord('a')
  $distance = $newNum - $oldNum;
  if( abs($distance) < 4 ) # handles any facings that don't cross the A/F division
    $amt = $distance;
  else if( $distance < 0 ) # CW changes across the A/F barrier
    $amt = $letterDistance + $distance +1;
  else if( $distance > 0 ) # CCW changes across the A/F barrier
    $amt = $letterDistance - $distance -1;

  # determine spin direction. '+' is clockwise, '-' is CCW
  # Each turn is 60 degrees. Flip the sign
  $amt *= -60;

  return $amt;
}

###
# Determines the X and Y blender units from the location string
###
# Args are:
# - (string) The 4-digit location of the unit. [row][column] format
# Returns:
# - (int) the column (X) value of the location
# - (int) the row (Y) value of the location
###
function locationPixels( $loc )
{
  global $XHEXSIZE, $YHEXSIZE, $XOFFSET, $YOFFSET;

  # error out on invalid entry
  if( $loc === NULL )
    return NULL;

  $hexVertBump = 0;

  $x = substr( $loc, 0, 2 );
  $y = substr( $loc, 2, 2 );

  if( $x % 2 == 0 )
    $hexVertBump = ($YHEXSIZE / 2); # even-numbered columns are vertically offset by half a hex      

  $xLoc = round( ($x * $XHEXSIZE) + $XOFFSET - $XHEXSIZE, 4);
  $yLoc = round( ($y * $YHEXSIZE) + $YOFFSET - $YHEXSIZE + $hexVertBump, 4);

  return array( $xLoc, $yLoc );
}

###
# Emits the python code to move and/or turn the unit
###
# Args are:
# - (string) The blender object name of the unit being moved
# - (float) The X-location to move to, in blender units
# - (float) The Y-location to move to, in blender units
# - (float) [optional] The degrees of rotation for the unit. '+' is clockwise, '-' is CCW
# - (int) [optional] Amount of frames to delay.
# - (boolean) [optional] Should the unit move to the new location without animation?
# - (float) [optional] The Z-location to move to, in blender units
# Returns:
# - (string) The python code to affect the move and turn
###
function keyframe_move( $unit, $X=null, $Y=null, $rotation=0, $delay=0, $suddenMove=FALSE, $Z="0.0" )
{
  global $frame, $FRAMESFORMOVE;
  $out = "";

  if( ! isset($unit) )
    return "# WARNING: Unit not set, frame $frame. Unit is '$unit'\n";

  # Select the $unit
  $out .= "$unit.select_set(True)\nbpy.context.view_layer.objects.active = $unit\n";

  # set the original location/rotation

  # No movement animation and no ability to mark the previous location
  if( $frame == 0 )
  {
    if( isset($X) && isset($Y) ) # do if movement is not missing
    {
      $out .= "bpy.context.object.location = ($X, $Y, $Z)\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=0)\n";
    }
    # set the original rotation to the frame before movement starts
    if( $rotation != "" && $rotation <> 0 ) # skip if rotation is missing or is 0 degrees
    {
      $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, radians($rotation))\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=0, index=2)\n";
    }
  }
  # No movement animation but mark the previous location
  else if( $suddenMove )
  {
    if( isset($X) && isset($Y) ) # do if movement is not missing
    {
      $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $frame + $delay - 1 ).")\n";
      # set the location of the new impulse
      $out .= "bpy.context.object.location = ($X, $Y, $Z)\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $frame + $delay ).")\n";
    }
    # set the original rotation to the frame before movement starts
    if( $rotation != "" && $rotation <> 0 ) # skip if rotation is missing or is 0 degrees
    {
      # post this at the end of movement.
      $out .= "rotation = degrees($unit.rotation_euler[2]) + ($rotation)\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=".( $frame + $delay - 1 ).", index=2)\n";
      # set the rotation of the new impulse
      $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, radians(rotation))\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=".( $frame + $delay ).", index=2)\n";
    }
  }
  # standard movement animation and mark the previous location
  else
  {
    if( isset($X) && isset($Y) ) # Movement may be missing (TACs, HETs, etc)
    {
      $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $frame + $delay - 1 ).")\n";
      # set the location of the new impulse
      $out .= "bpy.context.object.location = ($X, $Y, $Z)\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $frame + $FRAMESFORMOVE + $delay ).")\n";
    }
    if( $rotation != "" && $rotation <> 0 ) # skip if rotation is missing or is 0 degrees
    {
      $out .= "rotation = degrees($unit.rotation_euler[2]) + ($rotation)\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=".( $frame + $delay - 1 ).", index=2)\n";
      # set the rotation of the new impulse
      $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, radians(rotation))\n";
      $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=".( $frame + $FRAMESFORMOVE + $delay ).", index=2)\n";
    }
  }

  $out .= "$unit.select_set(False)\n\n";

  return $out;
}

###
# Emits the python code needed to duplicate the named model
# Leaves the duplicate model ready to be acted on (e.g. is selected)
###
# Args are:
# - (string) The model name in blender
# Returns:
# - (string) The python code to create the second model
###
function blender_duplicate( $modelName, $toCollection="Collection 1" )
{
  $out = "";
  $fromCollection = "Collection 2"; # Blender collection holding the original model

  $object = "bpy.data.objects[\"$modelName\"]";
  $out .= "$object.select_set(True)\nbpy.context.view_layer.objects.active = $object\n";
  $out .= "bpy.ops.object.duplicate(linked=0,mode='TRANSLATION')\n";
  # Could use 'bpy.context.collection.objects.link()', but best to be explicit
  $out .= "try:\n";
  $out .= "    bpy.data.collections[\"$toCollection\"].objects.link(bpy.context.active_object)\n";
  $out .= "except RuntimeError:\n";
  $out .= "    True\n\n";
  # remove (unlink) the duplicate model from the collection holding the original model
  # if we unlink the model from all collections, then the model is deleted
  if( $toCollection != $fromCollection )
  {
    $out .= "try:\n";
    $out .= "    bpy.data.collections[\"$fromCollection\"].objects.unlink(bpy.context.active_object)\n";
    $out .= "except RuntimeError:\n";
    $out .= "    True\n\n";
  }

  $out .= "obj = bpy.context.active_object\n";

  return $out;
}

###
# Emits the python code to create a notification card above a hex
###
# Args are:
# - (string) The announcement to make
# - (int) The X-location to move to, in blender units
# - (int) The Y-location to move to, in blender units
# - (int) How long to show the card, in frames
# - (int) [optional] The Z-location to move to, in blender units
# Returns:
# - (string) The python code to show the card
###
function card_set( $msg, $X, $Y, $time, $duration, $Z="1.5" )
{
  $out = "";

  # Duplicate and select the card
  $cardName = MODEL_NAME["Card"]["name"];
  $out .=  "# Add speech bubble '$msg'\n";
  $out .= blender_duplicate( $cardName );
  $out .= "obj.name = 'card $time'\n";

  # set the message
  $out .= "obj.modifiers[\"GeometryNodes\"][\"Socket_2\"] = \"$msg\"\n";
  $out .= "obj.data.update()\n";
  $out .= "obj.hide_render = False\n";
  # mark the off-map location
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".($time-1).")\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration + 1 ).")\n";
  # set the new location
  $out .= "obj.location = ($X, $Y, $Z)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=$time)\n";
  # remove after $duration
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration ).")\n";
  $out .= "obj.select_set(False)\n\n";

  return $out;
}

###
# Emits the python code to create a cloak effect
###
# Args are:
# - (string) The location of the cloaking unit, in [row][column] format
# - (string) The blender frame to begin
# Returns:
# - (string) The python code to create the phaser object and show it for the impulse
###
function make_cloak( $ownerLocation, $startFrame )
{
}

###
# Emits the python code to animate the surrender flag
###
# Args are:
# - (int) The X-location to move to, in blender units
# - (int) The Y-location to move to, in blender units
# - (int) How long to show the flag, in frames
# - (int) [optional] The Z-location to move to, in blender units
# Returns:
# - (string) The python code to animate the flag
###
function make_flag( $X, $Y, $time, $duration, $Z="1.5" )
{
  $out = "";
  $rotation = "180";
  $fabricName = "Flag Fabric";

  # make + move the flag pole
  $out = "# Move the Surrender Flag Pole\n";
  $out .= blender_duplicate( "Pole" );
  # mark the off-map location
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".($time-1).")\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration + 1 ).")\n";
  # set the new location
  $out .= "obj.location = ($X, $Y, $Z)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=$time)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration ).")\n";
  # initial rotation
  $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, 0.0)\n";
  $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=$time, index=2)\n";
  # the new rotation
  $out .= "rotation = degrees(obj.rotation_euler[2]) + ($rotation)\n";
  $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, radians(rotation))\n";
  $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=".( $time + $duration ).", index=2)\n";
  $out .= "obj.select_set(False)\n\n";

  # make + move the flag fabric
  $out .= "# Move the Surrender Flag\n";
  $out .= blender_duplicate( "Flag" );
  $out .= "obj.name = '$fabricName'\n";
  # mark the off-map location
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".($time-1).")\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration + 1 ).")\n";
  # set the new location
  $out .= "obj.location = ($X, $Y, $Z)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=$time)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration ).")\n";
  # initial rotation
  $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, 0.0)\n";
  $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=$time, index=2)\n";
  # the new rotation
  $out .= "rotation = degrees(obj.rotation_euler[2]) + ($rotation)\n";
  $out .= "bpy.context.object.rotation_euler = (0.0, 0.0, radians(rotation))\n";
  $out .= "bpy.context.object.keyframe_insert(data_path=\"rotation_euler\", frame=".( $time + $duration ).", index=2)\n";

  # animate the fabric
  $out .= "# Animate the flag\n";
  $out .= "bpy.ops.object.modifier_add(type='CLOTH')\n";
  $out .= "bpy.data.objects[\"$fabricName\"].modifiers[\"Cloth\"].point_cache.frame_start=$time\n";
  $out .= "bpy.data.objects[\"$fabricName\"].modifiers[\"Cloth\"].point_cache.frame_end=".( $time + $duration )."\n";
  $out .= "bpy.data.objects[\"$fabricName\"].modifiers[\"Cloth\"].settings.vertex_group_mass='Flag Pins'\n";
  $out .= "# Normally Bake the flag waving, here.\n# However, moving a baked simulation messes up the simulation.\n";
  $out .= "# bpy.ops.ptcache.bake_all(bake=True)\n";

  $out .= "obj.select_set(False)\n\n";

  return $out;
}

###
# Emits the python code to create a phaser beam between two hexes
###
# Args are:
# - (string) The location of the aggressor, in [row][column] format
# - (string) The location of the defender, in [row][column] format
# - (string) The blender frame to begin
# Returns:
# - (string) The python code to create the phaser object and show it for the impulse
###
function make_phaser( $ownerLocation, $targetLocation, $startFrame )
{
  global $FRAMESPERACTION, $phaserMaterial;
  $offMapLocation = "0, 0, -15"; # Where to put the phaser when done
  $out = "";

  list( $ownXLoc, $ownYLoc ) = locationPixels( $ownerLocation );
  list( $targXLoc, $targYLoc ) = locationPixels( $targetLocation );

  # Figure the parts of a cylinder between the aggressor and defender
  $dx = $targXLoc - $ownXLoc;
  $dy = $targYLoc - $ownYLoc;
  $dz = 0.6;
  $rad = 0.03;
  $dist = round( sqrt( $dx**2 + $dy**2 ), 4 );
  $phi = round( atan2( $dy, $dx ), 4 );

  # draw a cylinder between the aggressor and defender
  $out .= "bpy.ops.mesh.primitive_cylinder_add( radius = $rad, depth = $dist, ";
  $out .= "location = (".( $dx/2 + $ownXLoc ).", ".( $dy/2 + $ownYLoc ).", $dz ) )\n";
  $out .= "bpy.context.object.rotation_euler = ( $phi, -1.5708, 0 )\n";
  # mark this frame as the start of showing the phaser
  $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=$startFrame)\n";
  # mark the duration to show the phaser
  $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $startFrame + $FRAMESPERACTION ).")\n";
  # mark the hiding place of the phaser
  $out .= "bpy.context.object.location = ($offMapLocation)\n";
  $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $startFrame - 1 ).")\n";
  $out .= "bpy.context.object.keyframe_insert(data_path=\"location\", frame=".( $startFrame + $FRAMESPERACTION + 1 ).")\n";

  # Add texture to phaser
  $out .= "bpy.context.object.data.materials.append(bpy.data.materials.get(\"$phaserMaterial\"))\n";
  $out .= "bpy.context.object.name = 'Phaser $startFrame'\n";
  $out .= "bpy.ops.object.select_all(action=\"DESELECT\")\n\n"; # clunky way to deselect the one item just created

  return $out;
}

###
# Emits the python code to create a shield around the unit
###
# Args are:
# - (int) The X-location to move to, in blender units
# - (int) The Y-location to move to, in blender units
# - (string) Which side of shield to show: A-F
#            Where A is front, clockwise, to F is front-left
# - (int) How long to show the shield, in frames
# - (int) [optional] The Z-location to move to, in blender units
# Returns:
# - (string) The python code to show the shield
###
function make_shield( $X, $Y, $side, $time, $duration, $Z="1.5" )
{
  $out = "";

  # Select the shield
  switch( strtoupper($side) )
  {
  case "A":
  $shieldName = MODEL_NAME["Shield A"]["name"];
    break;
  case "B":
  $shieldName = MODEL_NAME["Shield B"]["name"];
    break;
  case "C":
  $shieldName = MODEL_NAME["Shield C"]["name"];
    break;
  case "D":
  $shieldName = MODEL_NAME["Shield D"]["name"];
    break;
  case "E":
  $shieldName = MODEL_NAME["Shield E"]["name"];
    break;
  case "F":
  $shieldName = MODEL_NAME["Shield F"]["name"];
    break;
  }

  # Duplicate the shield
  $out .=  "# Shield the unit\n";
  $out .= blender_duplicate( $shieldName );
  $out .= "obj.name = 'shield $time'\n";
  $out .= "obj.hide_render = False\n";

  # mark the off-map location
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".($time-1).")\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration + 1 ).")\n";
  # set the new location
  $out .= "obj.location = ($X, $Y, $Z)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=$time)\n";
  # remove after $duration
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration ).")\n";
  $out .= "obj.select_set(False)\n\n";

  return $out;
}

###
# Emits the python code to create a tractor beam between two hexes
###
# Args are:
# - (string) The location of the aggressor, in [row][column] format
# - (string) The location of the defender, in [row][column] format
# - (string) The blender frame to begin
# Returns:
# - (string) The python code to create the tractor object and show it, starting on the impulse
###
function make_tractor( $ownerLocation, $targetLocation, $startFrame )
{
}

###
# Emits the python code to update the turn/impulse card in front of the camera
###
# Args are:
# - (string) The impulse being displayed, in either format
# Returns:
# - (string) The python code to affect the move and turn
###
function impulse_display( $time )
{
#####
# Need to animate the text input
#####

  $out = "";

  # get the turn and impulse from the time
  if( ! str_contains( $time, "." ) )
    $time = LogUnit::convertFromImp( $time );
  list( $turn, $impulse) = explode( ".", $time );

  $out = "# Display \"T".$turn."i".$impulse."\" at camera.\n";
  # select the impulse card
  $cardName = MODEL_NAME["CamCard"]["name"];
  $cardObject = "bpy.data.objects[\"$cardName\"]";
  $out .= "$cardObject.select_set(True)\nbpy.context.view_layer.objects.active = $cardObject\n";

  # set the message
  $out .= "$cardObject.modifiers[\"GeometryNodes\"][\"Socket_2\"] = \"T".$turn."i".$impulse."\"\n";
  $out .= "$cardObject.data.update()\n\n";
  $out .= "$cardObject.select_set(False)\n";

  return $out;
}

###
# Emits the debug information for the current frame
###
# Args are:
# - (int) the impulse number to use
# Returns:
# - None
###
function debug( $impulse )
{
  global $log;
  $impulse = LogUnit::convertFromImp( $impulse );
  echo "#####\nimpulse $impulse\n";
  print_r( $log->read( $impulse ) );
  echo "#####\n";
}

function errorOut( $message )
{
  global $argv, $FILESUFFIX, $FRAMESPERACTION, $FRAMESFORMOVE;
  echo "\n";
  if( $message !== null && $message != "" )
    echo $message."\n\n";
  echo "Extract an SFBOL log file into a Blender script\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." [OPTIONS..] /path/to/log\n";
  echo "  Creates/overwrites a file appended with '$FILESUFFIX'\n";
  echo "\n";
  echo "OPTIONS:\n";
  echo "-a, --action\n";
  echo "   Change the frames per action-segment to this. Currently $FRAMESPERACTION frames.\n";
  echo "-h, --help\n";
  echo "   Give this help dialog.\n";
  echo "-m, --move\n";
  echo "   Change the frames per move-segment to this. Currently $FRAMESFORMOVE frames.\n";
  echo "-q, --quiet\n";
  echo "   On success, do not print anything to the terminal.\n";
  echo "-x, --no_action\n";
  echo "   Remove the wait time for any impulses where there is no action to animate.\n";
  exit(1);
}

?>
