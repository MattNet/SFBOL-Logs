#!/usr/bin/php -q
<?php
/*****
Extracts movement info from a SFBOL log file and creates a Blender python script
 
Actions per impulse go:
  - Number $FRAMESFORMOVE frames to animate movement
  - Number $FRAMESPERACTION frames to animate damage due to movement and also to note launches
  - Number $FRAMESPERACTION frames to animate fire and note damage
  - Total number of frames per impulse is ($FRAMESFORMOVE) + ($FRAMESPERACTION * 2)

This assumes that the python script will be run in a blender file that contains:
- A [hex-map] surface who's top layer is at z=0
- An existing model for everything that is needed (presumably off-screen)
- The operator will supply camera and lights, plus animate the same as needed

****/

###
# Unit type (in the logs) to Model name (in blender) conversion
###
define( 'MODEL_NAME', array(
# Ships
  'Andromedan Krait (Official)' => 'Andromedan Krait',
  'Archeo-Tholian TCC' => 'Tholian ATC',
  'Fed TCC (G-Rack) (Playtest)' => 'Federation CA',
  'Frax TC (Playtest)' => 'Frax TCA',
  'Gorn TCC' => 'Gorn TCA',
  'Hydran TLM' => 'Hydran TCC',
  'ISC TCC' => 'ISC CA',
  'Klingon TD7C' => 'Klingon D7CT',
  'LDR TCWL' => 'LDR TCWL',
  'Lyran TCC' => 'Lyran TCA',
  'Orion TBR' => 'Orion BR',
  'Romulan TFH' => 'Romulan TFH',
  'Romulan TKE' => 'Romulan TKE',
  'Romulan TKR' => 'Romulan TKR',
  'Vudar TCA' => 'Vudar TCA',
  'Wyn GBS' => 'WYN GBS',
  'Wyn TAxBC' => 'WYN AuxBCT',
# Expendables
  'Andromedan Mine' => 'Mine',
  'Archeo-Tholian Web' => 'Web',
  'Archeo-Tholian Shuttle' => 'Shuttle',
  'Federation Drone' => 'Drone',
  'Federation Shuttle' => 'Shuttle',
  'Frax Drone' => 'Drone',
  'Frax Shuttle' => 'Shuttle',
  'Gorn Plasma' => 'Plasma',
  'Gorn Shuttle' => 'Shuttle',
  'Hydran Fighter' => '',
  'Hydran Shuttle' => 'Shuttle',
  'ISC Plasma' => 'Plasma',
  'ISC Shuttle' => 'Shuttle',
  'Klingon Drone' => 'Drone',
  'Klingon Shuttle' => 'Shuttle',
  'LDR ESG' => 'ESG',
  'LDR Shuttle' => 'Shuttle',
  'Lyran ESG' => 'ESG',
  'Lyran Shuttle' => 'Shuttle',
  'Orion Drone' => 'Drone',
  'Orion ESG' => 'ESG',
  'Orion Plasma' => 'Plasma',
  'Orion Shuttle' => 'Shuttle',
  'TFE Plasma' => 'Plasma',
  'TKE Plasma' => 'Plasma',
  'TKR Plasma' => 'Plasma',
  'Romulan Shuttle' => 'Shuttle',
  'Vudar Shuttle' => 'Shuttle',
  'WYN Drone' => 'Drone',
  'WYN ESG' => 'ESG',
  'WYN Plasma' => 'Plasma',
  'WYN Shuttle' => 'Shuttle',
) );

# these are configuration variables
$FRAMESPERACTION = 12; # How long to animate the actions. Launching is one action, fire is another action
$FRAMESFORMOVE = 12; # How long to animate the movement. Total impulse length is this plus action
$XHEXSIZE = 0.9;
$XOFFSET = 0;
$YHEXSIZE = -1;
$YOFFSET = 0;
$FILESUFFIX = ".py";

# Internal variables
$frame = 0; # last frame referenced
$hexVertBump = 0; # Used to vertically offset alternating hexes
$impulseActivity = array(); # this holds the data from the current impulse
$LastLine = 0; # the last line of the log (extracted from the first (ship) unit)
$output = "import bpy\nimport mathutils\nfrom mathutils import *; from math import *\n";
$output .= "\n#####\n# Impulses are ".($FRAMESFORMOVE + ($FRAMESPERACTION * 2))." frames long.\n";
$output .= "# - Movement takes $FRAMESFORMOVE frames.\n";
$output .= "# - Early-impulse actions are animated for $FRAMESPERACTION frames.\n";
$output .= "# - Weapons fire is animated for $FRAMESPERACTION frames.\n#####\n\n";
$output .= "for obj in bpy.data.objects:\n   obj.select_set(False)\n";
$readFile = $argv[1];
$Ships = array();
$ShipsFacings = array(); # track the unit facing before the current move
$ShipsNames = array();
$unitList = array(); # list of all units in the battle
$UnknownUnits = array(); # units added, but not defined. Collected for debug output
$UnknownUnitsImpulse = array(); # units added, but not defined. Collected for debug output
$writeFile = $readFile.$FILESUFFIX;
$FRAMESFORMOVE -= 1; # reduce this now, since it will be used in a reduced fashion for the rest of the script

if( ! isset($readFile) || ! is_readable($readFile) )
{
  echo "\n";
  if( isset($readFile) )
    echo "Cannot read '".$readFile."'.\n\n";
  echo "Extract an SFBOL log file into a Blender script\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." /path/to/log\n";
  echo "  Creates/overwrites a file appended with '$FILESUFFIX'\n\n";
  exit(1);
}

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
# Get the python access method for each unit
# format is [] = [ "name"=>"name in log file", "type"=>"MODEL_NAME index", "basic"=>"basic type" ]
$unitCache = $log->get_units();

foreach( $unitCache as $entry )
{
  $entry["blender"] = "bpy.data.objects['".$entry["name"]."']";
  $ShipsFacings[ $entry["name"] ] = "A";
  $unitList[ $entry["name"] ] = $entry;
/*
# $unitList format:
    [added] => 0
    [basic] => ship
    [name] => Ballerina
    [removed] => 64
    [type] => Romulan TKR
    [blender] => bpy.data.objects['Romulan TKR']
*/

  # populate $LastLine
  if( $LastLine < $entry["removed"] )
    $LastLine = $entry["removed"];

# duplicate templated items in Blender to create named items to be moved
  $active_object = MODEL_NAME[$entry["type"]];
  # select the $entry item and make active
  $output .= "bpy.context.view_layer.objects.active = bpy.data.objects['$active_object']\n";
  $output .= "obj = bpy.data.objects['$active_object'].copy()\n";
  $output .= "bpy.context.collection.objects.link(obj)\n";
  $output .= "obj.name = '".$entry["name"]."'\n";
}

# set the length of the animation
$output .= "py.context.scene.frame_end = ".(($LastLine+1)*($FRAMESPERACTION*2+$FRAMESFORMOVE))."\n";

# go through each line of the input file
for( $i=0; $i<$LastLine; $i++ )
{
# Get the activity for this impulse
  $impulseActivity = $log->read( LogUnit::convertFromImp( $i ) );
  $frame = $i * ($FRAMESPERACTION *2 + $FRAMESFORMOVE);

  $output .= "\n# Start of impulse ".LogUnit::convertFromImp( $i ).", animation frame $frame\n\n";

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
/*
# $action format is:
    [facing] => D
    [location] => 1514
    [owner] => Ballerina
    [turn] => side-slip
*/
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
        else
        {
          $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];
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
/*
  [facing] => C
  [location] => 1910
  [speed] => 32
  [type] => TKR Plasma
  [owner] => Ballerina-PB(60).2.31
*/
          $hexVertBump = 0;
          $X = substr( $action["location"], 0, 2 );
          $Y = substr( $action["location"], 2, 2 );
          if( $X % 2 == 0 )
            $hexVertBump = ($YHEXSIZE / 2); # even-numbered columns are vertically offset by half a hex      

          $XLoc = ( ($X * $XHEXSIZE) + $XOFFSET - $XHEXSIZE);
          $YLoc = ( ($Y * $YHEXSIZE) + $YOFFSET - $YHEXSIZE + $hexVertBump);

          $rot = rotation($ShipsFacings[ $action["owner"] ], $action["facing"]);
          $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot, true );
          $ShipsFacings[ $action["owner"] ] = $action["facing"];
        }
      # if we are tractoring a unit
        if( $sequence == LogFile::SEQUENCE_TRACTORS )
        {
        }
      # if we are cloaking a unit
        if( $sequence == LogFile::SEQUENCE_CLOAKING_DEVICE )
        {
        }
      }
    }
# Firing
    else if( $sequence < LogFile::SEQUENCE_IMPULSE_END )
    {
      foreach( $actionSet as $action )
      {
      }
    }
# End of impulse
    else
    {
      foreach( $actionSet as $action )
      {
        # "remove" the unit (move off the map)
        $rot = rotation($ShipsFacings[ $action["owner"] ], "A");
        $output .= keyframe_move( $unitList[ $action["owner"] ]["blender"], $XLoc, $YLoc, $rot, true, "-10.0" );
        $ShipsFacings[ $action["owner"] ] = "A";
      }
    }
  }
}

echo "###\nDebug Info:\n###\n";
echo "Animation length: ".(($LastLine+1)*($FRAMESPERACTION*2+$FRAMESFORMOVE))." frames\n";
echo "Unit List:\n";
print_r( $unitList );
echo "\n";

# write the new file
$status = file_put_contents( $writeFile, $output );
if( ! $status )
  echo "Failed write of '$writeFile'\n\n";

####
# Function Declarations
####

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

function keyframe_move( $unit, $X, $Y, $rotation="", $suddenMove=FALSE, $Z="0.0" )
{
  global $frame, $FRAMESFORMOVE;
  $out = "";

  if( ! isset($unit) )
    return "# WARNING: Unit not set, frame $frame\n";

  # Select the $unit
  $out .= "$unit.select_set(True)\nbpy.context.view_layer.objects.active = $unit\n";

  # set the original location/rotation
  if( $frame == 0 || $suddenMove )
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
  else
  {
    if( isset($X) && isset($Y) ) # Movement may be missing (TACs, HETs, etc)
    {
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=".($frame-1).")\n";
      # set the location of the new impulse
      $out .= "$unit.location = ($X, $Y, $Z)\n";
      $out .= "$unit.keyframe_insert(data_path=\"location\", frame=".($frame+$FRAMESFORMOVE).")\n";
    }
    if( $rotation != "" && $rotation <> 0 ) # skip if rotation is missing or is 0 degrees
    {
      $out .= "rotation = degrees($unit.rotation_euler[2]) + ($rotation)\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=".($frame-1).", index=2)\n";
      # set the rotation of the new impulse
      $out .= "$unit.rotation_euler = (0.0, 0.0, radians(rotation))\n";
      $out .= "$unit.keyframe_insert(data_path=\"rotation_euler\", frame=".($frame+$FRAMESFORMOVE).", index=2)\n";
    }
  }

  return $out;
}
?>
