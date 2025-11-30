#!/usr/bin/php -q
<?php
/*****
Converts an SFBOL log file into a sequence of SVG map frames.
Each frame represents a discrete impulse segment:
  1) Movement
  2) Impulse Activity (tractors, cloaks, ESGs, launches)
  3) Weapons Fire & Damage

Each frame shows all units that exist at that impulse, with position, facing,
and optional movement trails for display continuity.
*****/

###
# Include dependencies
###
include_once("../LogFile.php");

## TODO: Duplicate frames based on FRAMESFORMOVE and FRAMESPERACTION
## TODO: remove unused action and weapons fire frames if $NOANIMATION = true
## TODO: Verify movement trails. Use trails from LOGFILE instead of cached trails in this code

###
# Default Values
###
$FRAMESFORMOVE = 1;
$FRAMESPERACTION = 1;
$NOANIMATION = false;
$SHOWNUMBERS = true; // Optional: show hex numbers on map
$TRAIL_LENGTH = 0; // Optional: number of past impulses to retain as a trail

$FILEPREFIX = "frame_";
$FRAMEFILE = "svg_frames";
$ICONDIR = "./icons";

###
# Constants
###
define('BACKGROUND_COLOR', '#E0E0E0');
define('HEX_RADIUS', 22.5);   // Inner radius of a hex, in pixels
define('MAP_COLS', 42);
define('MAP_ROWS', 30);
define('MARKER_WIDTH', 45);
define('MARKER_HEIGHT', 45);

define('HEX_WIDTH', HEX_RADIUS * 2);
define('HEX_HEIGHT', round(sqrt(3) * HEX_RADIUS, 5));
define('HEX_HALF_H', HEX_HEIGHT / 2); // half height. Put here to prevent constant recomputation
define('HEX_3Q_W', (3 * HEX_RADIUS) / 2); // horizontal spacing. Put here to prevent constant recomputation
define('MAP_MARGIN_HORIZ', HEX_RADIUS);
define('MAP_MARGIN_VERT', HEX_RADIUS);

# Colors and stroke widths for various effects
define('COLOR_TRACTOR', '#00BFFF');
define('COLOR_WEAPON', '#FF4500');
define('COLOR_DAMAGE', '#3333FF');
define('COLOR_TRAIL', '#AAAAAA');

###
# Lookup table for unit-type (per the log file) to named Blender model
# e.g. "Gozilla (Type:Gorn TCC) has been added at ..."
#                ^^^^^^^^^^^^^
###
define( 'MODEL_NAME', array(
# Ships
  'Andromedan Krait (Official)' => array( "name" => 'andcoq', 'no_rotate' => false ),
  'Archeo-Tholian TCC' => array( "name" => 'thoca', 'no_rotate' => false ),
  'Fed TCC (G-Rack) (Playtest)' => array( "name" => 'fedca', 'no_rotate' => false ),
  'Fed TCC (Official' => array( "name" => 'fedca', 'no_rotate' => false ),
  'Frax TC (Playtest)' => array( "name" => 'fraca', 'no_rotate' => false ),
  'Gorn TCC' => array( "name" => 'gorca', 'no_rotate' => false ),
  'Hydran TLM' => array( "name" => 'hydcc', 'no_rotate' => false ),
  'ISC TCC' => array( "name" => 'iscca', 'no_rotate' => false ),
  'Klingon TD7C' => array( "name" => 'klid6', 'no_rotate' => false ),
  'Kzinti TCC' => array( "name" => 'kzica', 'no_rotate' => false ),
  'LDR TCWL' => array( "name" => 'ldrcw', 'no_rotate' => false ),
  'Lyran TCC' => array( "name" => 'lyrca', 'no_rotate' => false ),
  'Orion TBR' => array( "name" => 'oribr', 'no_rotate' => false ),
  'Romulan TFH' => array( "name" => 'romfh', 'no_rotate' => false ),
  'Romulan TKE' => array( "name" => 'romwe', 'no_rotate' => false ),
  'Romulan TKR' => array( "name" => 'romkr', 'no_rotate' => false ),
  'Seltorian TCA' => array( "name" => 'selca', 'no_rotate' => false ),
  'Vudar TCA' => array( "name" => 'vudca', 'no_rotate' => false ),
  'Wyn GBS' => array( "name" => 'wynca', 'no_rotate' => false ),
  'Wyn TAxBC' => array( "name" => 'wynaxbc', 'no_rotate' => false ),
# Expendables
  'Andromedan Mine' => array( "name" => 'allsmallmine', 'no_rotate' => true ),
  'Archeo-Tholian Web' => array( "name" => '', 'no_rotate' => true ),
  'Archeo-Tholian Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Fed Drone' => array( "name" => 'alldrone', 'no_rotate' => false ),
  'Fed Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Frax Drone' => array( "name" => 'alldrone', 'no_rotate' => false ),
  'Frax Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Gorn Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'Gorn Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Hydran Fighter' => array( "name" => 'hydfighter', 'no_rotate' => false ),
  'Hydran Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'ISC Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'ISC Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Klingon Drone' => array( "name" => 'alldrone', 'no_rotate' => false ),
  'Klingon Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'LDR ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'LDR Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Lyran ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'Lyran Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Orion Drone' => array( "name" => 'alldrone', 'no_rotate' => false ),
  'Orion ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'Orion Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'Orion Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'TFE Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'TKE Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'TKR Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'Romulan Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Vudar Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
  'Wyn TAxBC Drone' => array( "name" => 'alldrone', 'no_rotate' => false ),
  'WYN ESG' => array( "name" => 'ESG', 'no_rotate' => true ),
  'WYN Plasma' => array( "name" => 'allplasma', 'no_rotate' => false ),
  'Wyn TAxBC Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false ),
# Misc
  'Andromedan DisDev' => array( "name" => 'DisDev Marker', 'no_rotate' => true ),
  'Card' => array( "name" => 'Card', 'no_rotate' => true ),
  'CamCard' => array( "name" => 'Camera Title', 'no_rotate' => true ),
  'Front Shield' => array( "name" => 'shield.front', 'no_rotate' => false ),
  'Left Shield' => array( "name" => 'shield.left', 'no_rotate' => false ),
  'Rear Shield' => array( "name" => 'shield.rear', 'no_rotate' => false ),
  'Right Shield' => array( "name" => 'shield.right', 'no_rotate' => false ),
) );

###
# CLI Configuration
###

$CLIoptions = "";
$CLIoptions .= "a::m::hxq";
$CLIlong = array("action::", "move::", "help", "no_action", "quiet");

$CLI = getopt($CLIoptions, $CLIlong, $rest_index);
$CLIend = array_slice($argv, $rest_index);
if ($CLI === false || empty($CLIend)) errorOut("Could not read command line arguments.");

# Adjust configuration from CLI
if (isset($CLI["a"])) $FRAMESPERACTION = (int)$CLI["a"];
if (isset($CLI["action"])) $FRAMESPERACTION = (int)$CLI["action"];
if (isset($CLI["m"])) $FRAMESFORMOVE = (int)$CLI["m"];
if (isset($CLI["move"])) $FRAMESFORMOVE = (int)$CLI["move"];
if (isset($CLI["x"]) || isset($CLI["no_action"])) $NOANIMATION = true;
if (isset($CLI["h"]) || isset($CLI["help"])) errorOut("");

###
# Input file handling
###
$readFile = $CLIend[0];
if (!isset($readFile) || !is_readable($readFile)) errorOut("Cannot read file '$readFile'.");

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

foreach ($unitCache as $unit) {
  $name = $unit["name"];
  $unitList[$name] = array_merge($unit, [
    "facing" => "A",
    "trail" => array(),
  ]);
  if ($unit["removed"] > $LastLine) $LastLine = $unit["removed"];
}

###
# Prepare output directory
###
$outDir = dirname($readFile) . "/" . $FRAMEFILE;
if (!is_dir($outDir))
  if (!mkdir($outDir, 0755, true)) {
    echo "Could not make '$outDir'.\n";
    exit;
  }

###
# Iterate through impulses
###
$frameIteration = 0;
for ($i = 0; $i <= $LastLine; $i++) {
  $impulse = LogUnit::convertFromImp($i);
  $impulseData = $log->read($impulse);

  # Segment 1 — Movement
  $svg = svg_init();
  $svg .= draw_map_background();
  $svg .= show_clock($i);
  $svg .= process_movement($impulseData, $unitList, $i);
  $svg .= draw_all_units($unitList, $i);
  $file = "{$outDir}/{$FILEPREFIX}" . sprintf("%03d",$frameIteration++) . ".svg";
  write_svg($svg, $file);

  # Segment 2 — Activity
  $svg = svg_init();
  $svg .= draw_map_background();
  $svg .= show_clock($i);
  $svg .= draw_all_units($unitList, $i);
  $svg .= process_activity($impulseData, $unitList, $i);
  $file = "{$outDir}/{$FILEPREFIX}" . sprintf("%03d",$frameIteration++) . ".svg";
  write_svg($svg, $file);

  # Segment 3 — Weapons
  $svg = svg_init();
  $svg .= draw_map_background();
  $svg .= show_clock($i);
  $svg .= draw_all_units($unitList, $i);
  $svg .= process_weapons($impulseData, $i);
  $file = "{$outDir}/{$FILEPREFIX}" . sprintf("%03d",$frameIteration++) . ".svg";
  write_svg($svg, $file);

  if ($log->error != "") {
    echo $impulse."\n";
    echo $log->error;
    exit(1);
  }
}

if (!isset($CLI["q"]) && !isset($CLI["quiet"])) {
  echo "Created {$frameIteration} SVG frames for ".($LastLine+1)." impulses.\n";
  echo "Wrote '$file'\n\n";
  echo "Render the SVG files with FFMPEG like so:\n";
  echo "   ffmpeg -framerate " . ($FRAMESFORMOVE + $FRAMESPERACTION * 2) . " -pattern_type glob";
  echo " -i '{$outDir}/{$FILEPREFIX}*.svg' -vf 'scale=3840:-1:flags=lanczos,format=yuv420p'";
  echo " -c:v libx264 -crf 18 -preset slow -an output.mp4\n\n";

  if ($log->error != "")
    echo $log->error;
}

exit(0);

#######################################################
# Function Definitions
#######################################################

###
# Initialize SVG canvas
###
function svg_init() {
  $width = HEX_WIDTH * ( MAP_COLS * 0.75) + (HEX_RADIUS / 2) + (MAP_MARGIN_HORIZ * 2);
  $height = HEX_HEIGHT * MAP_ROWS + HEX_HALF_H + (MAP_MARGIN_VERT * 2);
  $textScale = round(HEX_RADIUS / 2, 3);
  $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}'>\n";
  $svg .= "<style>
    text { font-size: $textScale" . "px }
    .hex { fill:none; stroke:#CCCCCC; stroke-width:1; }
    .unit { stroke:black; stroke-width:1; }
    .trail { stroke:" . COLOR_TRAIL . "; stroke-width:3; fill:none; opacity:0.5; }
    .clock { font-size:large; font-weight:bold; }
  </style>\n";
  $svg .= "<rect width='100%' height='100%' fill='" . BACKGROUND_COLOR . "' />";
  return $svg;
}

###
# Finalize and write SVG file
###
function write_svg($content, $filename) {
  $content .= "</svg>\n";
  if (!file_put_contents($filename, $content))
    echo "Failed write of '$filename'\n";
}

###
# Returns Center of the hex
###
function hex_to_pixel($col, $row) {
    // horizontal position (standard axial offset)
    $x = ($col - 1) * HEX_3Q_W + HEX_RADIUS + MAP_MARGIN_HORIZ;

    // odd-numbered columns are higher; even-numbered are 1/2-hex lower
    $y = $row * HEX_HEIGHT + (($col % 2 == 0) ? HEX_HALF_H : 0) - HEX_HALF_H + MAP_MARGIN_VERT;

    // We return the TOP vertex of the column spine, not the hex center.
    // Column Spine would be: ($y - HEX_HALF_H)
    return [$x, $y];
}


###
# Column-spine path generator
###
# Produces vertical path for an entire column of hexes.
# It draws ONE SIDE of each hex in the column (left edge), producing
# a continuous spine with 3 increments per hex.
function build_hex_column_path($col, $rows) {
  global $SHOWNUMBERS;
  $fontSize = 11; // for text placement

  $text = "\n";
  $path = "";
  list($xF, $yF) = hex_to_pixel($col, 1);
  $xF -= (HEX_RADIUS / 2);
  $yF -= HEX_HALF_H;

  // Move to the first top vertex of this column
  $d = "M$xF $yF";

  // Precompute increments
  $dx1 = -HEX_RADIUS / 2;
  $dy1 =  HEX_HALF_H;

  $dx2 =  HEX_RADIUS / 2;
  $dy2 =  HEX_HALF_H;

  $dx3 =  HEX_RADIUS;
  $dy3 =  0;

  $dx4 = -HEX_RADIUS;
  $dy4 =  0;

  for ($r = 1; $r <= $rows; $r++) {
    $d .= " l$dx1 $dy1";
    $d .= " $dx2 $dy2";
    $d .= " $dx3 $dy3";
    $d .= " m$dx4 $dy4";
    if ($r % 3 == 0) $d .= "\n";

    if ($SHOWNUMBERS) {
      $CCRR = sprintf("%02d%02d", $col, $r);
      list($xT, $yT) = hex_to_pixel($col, $r); // $xT is equal to $col. Call this to get $yT
      $xT -= (HEX_RADIUS / 2);
      $yT += $fontSize - HEX_HALF_H;

      $text .= "<text x='" . ($xT) . "' y='" . ($yT) . "'>$CCRR</text>";
      if ($r % 3 == 0) $text .= "\n";
    }
  }

  $path = "<path d=\"$d\"\n fill=\"none\" stroke=\"gray\" stroke-width=\"1\" />";
  return $path . $text . "\n";
}

###
# Column-spine path generator
###
# Produces map edges for a map of $col x $row size
# It draws in the missing parts of the map edges: all edges except the left.
function build_hex_edges($cols, $rows) {
  $path = "";

  // Precompute increments
  // Top row increments
  $dx1 =  HEX_RADIUS / 2;
  $dy1 =  -HEX_HALF_H;

  $dx2 =  HEX_RADIUS;
  $dy2 =  0;

  $dx3 =  HEX_RADIUS / 2;
  $dy3 =  HEX_HALF_H;

  // right side increments
  $dx5 =  HEX_RADIUS / 2;
  $dy5 =  HEX_HALF_H;

  $dx6 = -HEX_RADIUS / 2;
  $dy6 =  HEX_HALF_H;

  // bottom row increments
  $dx10 =  HEX_RADIUS * 2.5;
  $dy10 =  HEX_HALF_H;

  // Move to the first top vertex of this column
  list($xF, $yF) = hex_to_pixel(1, 1);
  $xF -= (HEX_RADIUS / 2);
  $yF -= HEX_HALF_H;
  $d = "M$xF $yF l";

  // the first hex (0101) is special
  $d .= " $dx2 $dy2";
  $d .= " $dx3 $dy3\n";

  // Move to the first bottom vertex of this column
  list($xL, $yL) = hex_to_pixel(1, $rows);
  $xL -= (HEX_RADIUS / 2);
  $yL += HEX_HEIGHT - HEX_HALF_H;
  $bottom = "\n M$xL $yL";

  // draw the top edge
  for ($c = 2; $c <= $cols; $c++) {
    if ($c % 2 == 0) { // if an even-numbered hex
      $d .= " $dx2 $dy2";
    } else {
      $d .= " $dx1 $dy1";
      $d .= " $dx2 $dy2";
      $d .= " $dx3 $dy3";
    }
    if ($c % 4 == 0) $d .= "\n";

    // draw the bottom edge
    if ($c % 2 == 0) { // if an even-numbered hex
      $bottom .= " m$dx10 $dy10 l$dx1 $dy1";
      if ($c % 8 == 0) $bottom .= "\n";
    }
  }

  // draw the right edge
  $d .= "\n";
  for ($r = 1; $r <= $rows; $r++) {
    $d .= " $dx5 $dy5";
    $d .= " $dx6 $dy6";
    if ($r % 4 == 0) $d .= "\n";
  }

  $d .= $bottom;
  $path = "<path d=\"$d\"\n fill=\"none\" stroke=\"gray\" stroke-width=\"1\" />\n";
  return $path;
}

###
# Full background renderer — draws MAP_COLS column-paths
###
function draw_map_background() {
    $out = "";

    for ($col = 1; $col <= MAP_COLS; $col++) {
      $out .= build_hex_column_path($col, MAP_ROWS);
    }
    $out .= build_hex_edges(MAP_COLS, MAP_ROWS);
    return $out;
}

###
# Draw all visible units at current impulse
###
function draw_all_units($unitList, $impulse) {
  global $TRAIL_LENGTH;
  $svg = "<g id='units'>\n";
  foreach ($unitList as $unit) {
    if (!isset($unit["location"])) continue;
    if ($unit["added"] > $impulse || $unit["removed"] < $impulse ) continue;
    list($x, $y) = locationPixels($unit["location"]);

    $svg .= draw_unit_marker($x, $y, $unit["facing"], $unit["type"]);

    if ($TRAIL_LENGTH > 0 && count($unit["trail"]) > 1) {
      $trailPoints = implode(" ", array_map(fn($p) => "{$p[0]},{$p[1]}", $unit["trail"]));
      $svg .= "<polyline class='trail' points='$trailPoints' />\n";
    }
  }
  $svg .= "</g>\n";
  return $svg;
}

###
# Convert CCRR location string to pixel position
###
function locationPixels($loc) {
  if (empty($loc) || strlen((string)$loc) < 4) return [0,0];

  $col = substr($loc, 0, 2);
  $row = substr($loc, 2, 2);
  return hex_to_pixel((int)$col, (int)$row);
}

###
# Draw a marker for a unit, rotated to match facing
###
function draw_unit_marker($x, $y, $facing, $type) {
  global $ICONDIR;

  static $markerCache = [];

  $halfHeight = MARKER_HEIGHT / 2;
  $halfWidth = MARKER_WIDTH / 2;
  $markerScale = round(HEX_RADIUS / $halfHeight, 3);

  if (!isset(MODEL_NAME[$type]) || empty(MODEL_NAME[$type]["name"])) {
    echo "Could not find entry for '$type' in lookup.\n";
    return null;
  }

  $angle = facingToAngle($facing);
  $markerPrefix = "<g transform='translate(" . ($x-$halfWidth) . "," . ($y-$halfHeight) . ") rotate($angle,$halfWidth,$halfHeight)'>";
  $markerPrefix .= "<g transform='scale($markerScale)'>";
  $markerSuffix = "</g></g>\n";

  if (isset($markerCache[$type]))
    return $markerPrefix . $markerCache[$type] . $markerSuffix;

  // prepare for marker file
  $iconFile = $ICONDIR . "/" . MODEL_NAME[$type]["name"] . ".svg";
  if (!is_readable($iconFile)) {
    echo "Could not read file '$iconFile' for $type.\n";
    return null;
  }

  // get the marker file
  $marker = file_get_contents($iconFile);
  if ($marker === false) {
    echo "Could not retrieve file '$iconFile' for $type.\n";
    return null;
  }

  // Munge the marker file:
  // Remove opening <svg ...> tag
  $marker = preg_replace('/<svg[^>]*>/i', '', $marker);
  // Remove closing </svg>
  $marker = preg_replace('/<\/svg>/i', '', $marker);
  $marker = trim($marker);

//$marker = "<rect width='45' height='45' />".$marker;
  $markerCache[$type] = $marker;
  return $markerPrefix . $marker . $markerSuffix;
}

###
# Convert facing (A–F) to degrees
###
function facingToAngle($facing) {
  switch (strtoupper($facing)) {
    case 'A': return 0;
    case 'B': return 60;
    case 'C': return 120;
    case 'D': return 180;
    case 'E': return 240;
    case 'F': return 300;
  }
  return 0;
}

###
# Process activity segment: tractors, cloaks, launches, etc.
###
function show_clock($impulse) {
  $time = LogUnit::convertFromImp($impulse);
  $x = MAP_MARGIN_HORIZ / 2;
  $y = "1em";
  $svg = "<text x='$x' y='$y' class='clock'>Turn $time</text>\n";
  return $svg;
}

###
# Process movement segment: update locations, facings, and trails
###
function process_movement($impulseData, &$unitList, $i) {
  $svg = "<g id='movement'>\n";
  foreach ($impulseData as $sequence => $actions) {
    if ($sequence > LogFile::SEQUENCE_MOVEMENT_TAC) continue;
    foreach ($actions as $action) {
      $name = $action["owner"];
      if (!isset($unitList[$name])) continue;

      if (isset($action["location"])) {
        $unitList[$name]["location"] = $action["location"];
        list($x, $y) = locationPixels($action["location"]);
        $unitList[$name]["trail"][] = array($x, $y);
        if (count($unitList[$name]["trail"]) > 16)
          array_shift($unitList[$name]["trail"]);
      }
      if (isset($action["facing"]))
        $unitList[$name]["facing"] = $action["facing"];
    }
  }
  $svg .= "</g>\n";
  return $svg;
}

###
# Process activity segment: tractors, cloaks, launches, etc.
###
function process_activity($impulseData, &$unitList, $impulse) {
  $svg = "<g id='activity'>\n";
  foreach ($impulseData as $sequence => $actions) {
    if ($sequence <= LogFile::SEQUENCE_MOVEMENT_TAC ||
        $sequence >= LogFile::SEQUENCE_DIS_DEV_DECLARATION) continue;

    foreach ($actions as $action) {
      if ($sequence == LogFile::SEQUENCE_TRACTORS) {
        $svg .= draw_effect_line($action["owner location"], $action["target"], COLOR_TRACTOR, $impulse);
      }
      if ($sequence == LogFile::SEQUENCE_CLOAKING_DEVICE) {
        list($x, $y) = locationPixels($action["owner location"]);
        $svg .= "<circle cx='$x' cy='$y' r='20' fill='none' stroke='gray' stroke-dasharray='5,3' />\n";
      }
      if ($sequence == LogFile::SEQUENCE_LAUNCH_PLASMA ||
          $sequence == LogFile::SEQUENCE_LAUNCH_DRONES ||
          $sequence == LogFile::SEQUENCE_LAUNCH_SHUTTLES
         ) {
        list($x, $y) = locationPixels($action["location"]);
        $svg .= draw_unit_marker($x, $y, $action["facing"], $action["type"]);

        $name = $action["name"] ?? $action["owner"];
        if (!isset($unitList[$name])) {
          echo "Tried to draw '$name' on impulse $impulse. Could not find in unit list.\n";
          continue;
        }
        $unitList[$name]["location"] = $action["location"];
        $unitList[$name]["facing"] = $action["facing"];
        list($x, $y) = locationPixels($action["location"]);
        if (count($unitList[$name]["trail"]) > 16)
          array_shift($unitList[$name]["trail"]);
      }
    }
  }
  $svg .= "</g>\n";
  return $svg;
}

###
# Process weapons fire segment
###
function process_weapons($impulseData, $impulse) {
  global $log;
  $svg = "<g id='weapons'>\n";

  foreach ($impulseData as $sequence => $actions) {
    if ($sequence < LogFile::SEQUENCE_DIS_DEV_DECLARATION ||
        $sequence >= LogFile::SEQUENCE_IMPULSE_END) continue;
    foreach ($actions as $action) {
      // weapon effect
      if (isset($action["weapon"])) {
        $svg .= draw_effect_line($action["owner location"], $action["target"], COLOR_WEAPON, $impulse);
      }
      // damage
      if (isset($action["total"]))
        foreach (array_keys($action["direction"]) as $dir) {
          list($x, $y) = locationPixels($action["owner location"]);
          $shieldDamage = $action["total"] - $action["internals"];
          $internalDamage = $action["internals"];
          $shieldRadius = HEX_RADIUS * 0.75;
          $yS = $y - ($shieldRadius * 0.866025);
          $xS = $x - ($shieldRadius * 0.5);
          $yE = $y - ($shieldRadius * 0.866025);
          $xE = $x + ($shieldRadius * 0.5);
          $angle = facingToAngle($dir) + facingToAngle($log->get_unit_facing( $action['owner'], $impulse ));

          if ($shieldDamage > 0 && $internalDamage <= 0)
            $dmgString = $shieldDamage;
          else if ($internalDamage > 0 && $shieldDamage <= 0)
            $dmgString = $internalDamage;
          else
            $dmgString = "$shieldDamage + $internalDamage";

          if ($shieldDamage > 0) {
            $svg .= "<path d='M $xS $yS A " . $shieldRadius . " " . $shieldRadius . " 0 0 1 $xE $yE' fill='none' ";
            $svg .= "stroke='" . COLOR_DAMAGE . "' stroke-width='3' transform='rotate($angle $x $y)' />";
          }
          $svg .= "<text x='$x' y='" . ($y + HEX_HALF_H / 2) . "' stroke='" . COLOR_DAMAGE . "'>$dmgString</text>";
        }
    }
  }
  $svg .= "</g>\n";
  return $svg;
}

###
# Draw a line between two units or hexes (for tractor/weapon)
###
function draw_effect_line($fromLoc, $toUnit, $color, $impulse) {
  global $log;
  list($x1, $y1) = locationPixels($fromLoc);
  $targetLoc = $log->get_unit_location($toUnit, $impulse); // current impulse
  list($x2, $y2) = locationPixels($targetLoc);
  return "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' stroke='$color' stroke-width='2' />\n";
}

###
# CLI help output
###
function errorOut($message) {
  global $argv, $FRAMESPERACTION, $FRAMESFORMOVE;
  echo "\n";
  if ($message !== null && $message != "") echo $message . "\n\n";
  echo "Usage:\n  {$argv[0]} [OPTIONS..] /path/to/log\n";
  echo "Options:\n";
  echo "  -a, --action <n>    Frames per action segment (default $FRAMESPERACTION)\n";
  echo "  -m, --move <n>      Frames per movement segment (default $FRAMESFORMOVE)\n";
  echo "  -x, --no_action     Skip impulses with no activity\n";
  echo "  -q, --quiet         Suppress console output\n";
  echo "  -h, --help          Show this help message\n";
  exit(1);
}
?>

