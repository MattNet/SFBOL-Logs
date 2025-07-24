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
  'Andromedan Krait (Official)' => 'Andromedan KRA',
  'Archeo-Tholian TCC' => 'Tholian ATC',
  'Fed TCC (G-Rack) (Playtest)' => 'Federation TCA',
  'Fed TCC (Official' => 'Federation TCA',
  'Frax TC (Playtest)' => 'Frax TCA',
  'Gorn TCC' => 'Gorn TCA',
  'Hydran TLM' => 'Hydran TCC',
  'ISC TCC' => 'ISC CA',
  'Klingon TD7C' => 'Klingon D7CT',
  'Kzinti TCC' => 'Kzinti TCC',
  'LDR TCWL' => 'LDR TCWL',
  'Lyran TCC' => 'Lyran TCA',
  'Orion TBR' => 'Orion TBR',
  'Romulan TFH' => 'Romulan TFH',
  'Romulan TKE' => 'Romulan TKE',
  'Romulan TKR' => 'Romulan TKR',
  'Seltorian TCA' => 'Seltorian TCA',
  'Vudar TCA' => 'Vudar TCA',
  'Wyn GBS' => 'WYN GBS',
  'Wyn TAxBC' => 'WYN AuxBCT',
# Expendables
  'Andromedan Mine' => 'Mine',
  'Archeo-Tholian Web' => 'Web',
  'Archeo-Tholian Shuttle' => 'Shuttle',
  'Fed Drone' => 'Drone',
  'Fed Shuttle' => 'Federation Shuttle',
  'Frax Drone' => 'Drone',
  'Frax Shuttle' => 'Federation Shuttle',
  'Gorn Plasma' => 'Plasma',
  'Gorn Shuttle' => 'Federation Shuttle',
  'Hydran Fighter' => '',
  'Hydran Shuttle' => 'Federation Shuttle',
  'ISC Plasma' => 'Plasma',
  'ISC Shuttle' => 'Federation Shuttle',
  'Klingon Drone' => 'Drone',
  'Klingon Shuttle' => 'Federation Shuttle',
  'LDR ESG' => 'ESG',
  'LDR Shuttle' => 'Federation Shuttle',
  'Lyran ESG' => 'ESG',
  'Lyran Shuttle' => 'Federation Shuttle',
  'Orion Drone' => 'Drone',
  'Orion ESG' => 'ESG',
  'Orion Plasma' => 'Plasma',
  'Orion Shuttle' => 'Federation Shuttle',
  'TFE Plasma' => 'Plasma',
  'TKE Plasma' => 'Plasma',
  'TKR Plasma' => 'Plasma',
  'Romulan Shuttle' => 'Federation Shuttle',
  'Vudar Shuttle' => 'Federation Shuttle',
  'Wyn TAxBC Drone' => 'Drone',
  'WYN ESG' => 'ESG',
  'WYN Plasma' => 'Plasma',
  'Wyn TAxBC Shuttle' => 'Federation Shuttle',
# Misc
  'Andromedan DisDev' => '',
  'Card' => 'Card',
  'CamCard' => 'Camera Title',
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
$CLIoptions .= "q"; // quiet the command line
$CLIoptions .= "x"; // remove action time for impulses without it
$CLIlong = array(
  "action::",
  "move::",
  "quiet",
  "no_action",
);

$CLI = getopt( $CLIoptions, $CLIlong, $rest_index );
$CLIend = array_slice( $argv, $rest_index );
if( $CLI === false || empty($CLIend) ) errorOut( "Could not read command line arguments." );

# Some command-line arguments change the configuration
if( isset($CLI["a"]) )
  $FRAMESPERACTION = (int) $CLI["a"];
if( isset($CLI["action"]) )
  $FRAMESPERACTION = (int) $CLI["action"];
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
$output .= "# - Weapons fire is animated for $FRAMESPERACTION frames.";
if( $NOANIMATION )
  $output .= "# - Impulses with no activity will skip the activity segment.";
$output .= "\n#####\n\n";
$output .= "for obj in bpy.data.objects:\n   obj.select_set(False)\n";
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
# format is $unitCache[ index ] = [
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

  # Set $unitList to $unitCache, but indicies are the unit name
  $unitList[ $entry["name"] ] = $entry;

  # update $LastLine
  if( $LastLine < $entry["removed"] )
    $LastLine = $entry["removed"];

# duplicate templated items in Blender to create named items to be moved
  $active_object = MODEL_NAME[$entry["type"]];
  # select the $entry item and make active
  $output .= "bpy.context.view_layer.objects.active = bpy.data.objects['$active_object']\n";
  $output .= "obj = bpy.data.objects['$active_object'].copy()\n";
  $output .= "bpy.context.collection.objects.link(obj)\n";
  $output .= "obj.name = '".$entry["name"]."'\n";
  $output .= "obj.hide_render = False\n";
}

$log->read( LogUnit::convertFromImp( 25 ) );

# go through each impulse
for( $i=0; $i<$LastLine; $i++ )
{
# Get the activity for this impulse
  $impulseActivity = $log->read( LogUnit::convertFromImp( $i ) );
//  $frame = $i * ($FRAMESPERACTION * 2 + $FRAMESFORMOVE);
  $frame = $frameIncrement;
  $flagWasActivity = false;

  $output .= "\n# Start of impulse ".LogUnit::convertFromImp( $i ).", animation frame $frame\n\n";

//  $output .= impulse_display( $i );

  # skip if nothing happened here
  if( empty($impulseActivity) )
    continue;

# Iterate through the movement sequences
  foreach( $impulseActivity as $sequence=>$actionSet )
  {

# movement items
    if( $sequence <= LogFile::SEQUENCE_MOVEMENT_TAC )
    {
      foreach( $actionSet as $action )
      {
      # Change location
        if( isset($action["location"]) ) # a movement order
        {
          $hexVertBump = 0;
          $X = substr( $action["location"], 0, 2 );
          $Y = substr( $action["location"], 2, 2 );
          if( $X % 2 == 0 )
            $hexVertBump = ($YHEXSIZE / 2); # even-numbered columns are vertically offset by half a hex      

          $XLoc = ( ($X * $XHEXSIZE) + $XOFFSET - $XHEXSIZE);
          $YLoc = ( ($Y * $YHEXSIZE) + $YOFFSET - $YHEXSIZE + $hexVertBump);

          $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];
        }
      # Change facing
        # if the facing changes between old and new facing
        else if( $ShipsFacings[ $action["owner"] ] != $action["facing"] )
        {
          $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], null, null, $rot );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];

          # Add a Card to decribe TAC or HET
          if( isset($action["turn"]) && ( $action["turn"] == "TAC" || $action["turn"] == "HET" ))
            $output .= card_set( $action["turn"], $XLoc, $YLoc, $frame, $FRAMESFORMOVE );
        }
      }
    }
# Launch items
    else if( $sequence < LogFile::SEQUENCE_DIS_DEV_DECLARATION )
    {
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
          $hexVertBump = 0;
          $X = substr( $action["location"], 0, 2 );
          $Y = substr( $action["location"], 2, 2 );
          if( $X % 2 == 0 )
            $hexVertBump = ($YHEXSIZE / 2); # even-numbered columns are vertically offset by half a hex      

          $XLoc = ( ($X * $XHEXSIZE) + $XOFFSET - $XHEXSIZE);
          $YLoc = ( ($Y * $YHEXSIZE) + $YOFFSET - $YHEXSIZE + $hexVertBump);

          $output .= "# Launch/land ".$unitList[ $action["owner"] ]["basic"]."\n";

          # Set the initial rotation/location
          $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot, $FRAMESFORMOVE, true );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];

          # Announce the action
          $output .= card_set( $unitList[ $action["owner"] ]["basic"]." Launch", $XLoc, $YLoc, $frame+$FRAMESFORMOVE, $FRAMESPERACTION );

          # Flag that we impulse activity
          $flagWasActivity = true;
         }
      # if we are tractoring a unit
        if( $sequence == LogFile::SEQUENCE_TRACTORS )
        {
          # Flag that we impulse activity
          $flagWasActivity = true;
        }
      # if we are cloaking a unit
        if( $sequence == LogFile::SEQUENCE_CLOAKING_DEVICE )
        {
          # Announce the action
          $output .= card_set( "Cloak", $XLoc, $YLoc, $frame+$FRAMESFORMOVE, $FRAMESPERACTION );

          # Flag that we impulse activity
          $flagWasActivity = true;
        }
      }
    }
# Firing
    else if( $sequence < LogFile::SEQUENCE_IMPULSE_END )
    {
//      foreach( $actionSet as $action )
      {
          # Flag that we impulse activity
          $flagWasActivity = true;
      }
    }
# End of impulse
    else
    {
      foreach( $actionSet as $action )
      {
        # "remove" the unit (move off the map)
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

# set the length of the animation
# This is assuredly too large if $NOANIMATION is true
//$output .= "bpy.context.scene.frame_end = ".(($LastLine+1)*$frameIncrement)."\n";
$output .= "bpy.context.scene.frame_end = $frameIncrement\n";
$output .= "bpy.context.scene.frame_set(0)\n";


if( ! isset($CLI["q"]) && ! isset($CLI["quiet"]) )
{
  echo "###\nDebug Info:\n###\n";
//  echo "Animation length: ".(($LastLine+1)*($FRAMESPERACTION*2+$FRAMESFORMOVE))." frames\n";
  echo "Animation length: $frameIncrement frames\n";
  echo "Unit List:\n";
  print_r( $unitList );
  echo "\n";
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
function keyframe_move( $unit, $X=null, $Y=null, $rotation="", $delay=0, $suddenMove=FALSE, $Z="0.0" )
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
      $out .= "$unit.location = ($X, $Y, $Z)\n";
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=0)\n";
    }
    # set the original rotation to the frame before movement starts
    if( $rotation != "" ) # do if rotation is not missing
    {
      $out .= "$unit.rotation_euler = (0.0, 0.0, radians($rotation))\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=0, index=2)\n";
    }
  }
  # No movement animation but mark the previous location
  else if( $suddenMove )
  {
    if( isset($X) && isset($Y) ) # do if movement is not missing
    {
      # post this at the end of movement.
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=".($frame+$delay-1).")\n";
      # set the location of the new impulse
      $out .= "$unit.location = ($X, $Y, $Z)\n";
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=".($frame+$delay).")\n";
    }
    # set the original rotation to the frame before movement starts
    if( $rotation != "" ) # do if rotation is not missing
    {
      # post this at the end of movement.
      $out .= "rotation = degrees($unit.rotation_euler[2]) + ($rotation)\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=".($frame+$delay-1).", index=2)\n";
      # set the rotation of the new impulse
      $out .= "$unit.rotation_euler = (0.0, 0.0, radians(rotation))\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=".($frame+$delay).", index=2)\n";
    }
  }
  # standard movement animation and mark the previous location
  else
  {
    if( isset($X) && isset($Y) ) # Movement may be missing (TACs, HETs, etc)
    {
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=".($frame+$delay-1).")\n";
      # set the location of the new impulse
      $out .= "$unit.location = ($X, $Y, $Z)\n";
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=".($frame+$FRAMESFORMOVE+$delay).")\n";
    }
    if( $rotation != "" && $rotation <> 0 ) # skip if rotation is missing or is 0 degrees
    {
      $out .= "rotation = degrees($unit.rotation_euler[2]) + ($rotation)\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=".($frame+$delay-1).", index=2)\n";
      # set the rotation of the new impulse
      $out .= "$unit.rotation_euler = (0.0, 0.0, radians(rotation))\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=".($frame+$FRAMESFORMOVE+$delay).", index=2)\n";
    }
  }

  return $out."\n";
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
# - (string) The python code to affect the move and turn
###
function card_set( $msg, $X, $Y, $time, $duration, $Z="3.0" )
{
  $offMapLocation = "0, 0, -8"; # Where to put the card when done
  $out = "";

  # Duplicate and select the card
  $cardName = MODEL_NAME["Card"];
  $cardObject = "bpy.data.objects[\"$cardName\"]";
  $out .=  "# Add speech bubble '$msg'\n";
  $out .= "$cardObject.select_set(True)\nbpy.context.view_layer.objects.active = $cardObject\n";
  $out .= "obj = bpy.data.objects[\"$cardName\"].copy()\n";
  $out .= "bpy.context.collection.objects.link(obj)\n";

  # set the message
  $out .= "obj.modifiers[\"GeometryNodes\"][\"Socket_2\"] = \"$msg\"\n";
  $out .= "obj.data.update()\n";
  $out .= "obj.hide_render = False\n";
  # Move the new card
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".($time-1).")\n";
  # set the new location
  $out .= "obj.location = ($X, $Y, $Z)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=$time)\n";
  # remove after $time
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration ).")\n";
  $out .= "obj.location = ($offMapLocation)\n";
  $out .= "obj.keyframe_insert(data_path=\"location\", frame=".( $time + $duration + 1 ).")\n";

  return $out;
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
  $cardName = MODEL_NAME["CamCard"];
  $cardObject = "bpy.data.objects[\"$cardName\"]";
  $out .= "$cardObject.select_set(True)\nbpy.context.view_layer.objects.active = $cardObject\n";

  # set the message
  $out .= "$cardObject.modifiers[\"GeometryNodes\"][\"Socket_2\"] = \"T".$turn."i".$impulse."\"\n";
  $out .= "$cardObject.data.update()\n\n";

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
  echo $message."\n\n";
  echo "Extract an SFBOL log file into a Blender script\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." [OPTIONS..] /path/to/log\n";
  echo "  Creates/overwrites a file appended with '$FILESUFFIX'\n";
  echo "\n";
  echo "OPTIONS:\n";
  echo "-a, --action\n";
  echo "   Change the frames per action-segment to this. Currently $FRAMESPERACTION frames.\n";
  echo "-m, --move\n";
  echo "   Change the frames per move-segment to this. Currently $FRAMESFORMOVE frames.\n";
  echo "-q, --quiet\n";
  echo "   On success, do not print anything to the terminal.\n";
  echo "-x, --no_action\n";
  echo "   Remove the wait time for any impulses where there is no action to animate.\n";
  exit(1);
}

?>
