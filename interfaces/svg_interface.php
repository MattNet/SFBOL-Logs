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

## TODO: Implement no-rotation marker effect

###
# Default Values
###
$FRAMESFORMOVE = 1;
$FRAMESPERACTION = 1;
$NOANIMATION = false;
$SHOWNUMBERS = true; // Optional: show hex numbers on map
$MOVEMENTTRAILS = 0; // Optional: number of past impulses to retain as a trail

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
define('TRAIL_OPACITY', 0.75);

define('HEX_LABEL_FONT_SIZE', 11);
define('CARD_LABEL_FONT_SIZE', 11);

###
# Lookup table for unit-type (per the log file) to named Blender model
# e.g. "Gozilla (Type:Gorn TCC) has been added at ..."
#                ^^^^^^^^^^^^^
###
$MODEL_NAME = array(
# Ships
  'Andromedan Krait (Official)' => array( "name" => 'andcoq', 'no_rotate' => false, 'colorSource' => '' ),
  'Archeo-Tholian TCC' => array( "name" => 'thoca', 'no_rotate' => false, 'colorSource' => '' ),
  'Fed TCC (G-Rack) (Playtest)' => array( "name" => 'fedca', 'no_rotate' => false, 'colorSource' => '' ),
  'Fed TCC (Official' => array( "name" => 'fedca', 'no_rotate' => false, 'colorSource' => '' ),
  'Frax TC (Playtest)' => array( "name" => 'fraca', 'no_rotate' => false, 'colorSource' => '' ),
  'Gorn TCC' => array( "name" => 'gorbc', 'no_rotate' => false, 'colorSource' => '' ),
  'Hydran Tarter (Playtest)' => array( "name" => 'hydmng', 'no_rotate' => false, 'colorSource' => '' ),
  'Hydran TLM' => array( "name" => 'hydcc', 'no_rotate' => false, 'colorSource' => '' ),
  'ISC TCC' => array( "name" => 'iscca', 'no_rotate' => false, 'colorSource' => '' ),
  'Klingon TD7C' => array( "name" => 'klid6', 'no_rotate' => false, 'colorSource' => '' ),
  'Kzinti TCC' => array( "name" => 'kzica', 'no_rotate' => false, 'colorSource' => '' ),
  'LDR TCWL' => array( "name" => 'ldrcw', 'no_rotate' => false, 'colorSource' => '' ),
  'Lyran TCC' => array( "name" => 'lyrca', 'no_rotate' => false, 'colorSource' => '' ),
  'Orion TBR' => array( "name" => 'oribr', 'no_rotate' => false, 'colorSource' => '' ),
  'Romulan TFH' => array( "name" => 'romfh', 'no_rotate' => false, 'colorSource' => '' ),
  'Romulan TKE' => array( "name" => 'romwe', 'no_rotate' => false, 'colorSource' => '' ),
  'Romulan TKR' => array( "name" => 'romkr', 'no_rotate' => false, 'colorSource' => '' ),
  'Seltorian TCA' => array( "name" => 'selca', 'no_rotate' => false, 'colorSource' => '' ),
  'Vudar TCA' => array( "name" => 'vudca', 'no_rotate' => false, 'colorSource' => '' ),
  'Wyn GBS' => array( "name" => 'wynca', 'no_rotate' => false, 'colorSource' => '' ),
  'Wyn TAxBC' => array( "name" => 'wynaxbc', 'no_rotate' => false, 'colorSource' => '' ),
# Expendables
  'Andromedan Mine' => array( "name" => 'allsmallmine', 'no_rotate' => true, 'colorSource' => 'Andromedan Krait (Official)' ),
  'Archeo-Tholian Web' => array( "name" => 'allweb', 'no_rotate' => true, 'colorSource' => 'Archeo-Tholian TCC' ),
  'Archeo-Tholian Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Archeo-Tholian TCC' ),
  'Fed Drone' => array( "name" => 'alldrone', 'no_rotate' => false, 'colorSource' => 'Fed TCC (G-Rack) (Playtest)' ),
  'Fed Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Fed TCC (G-Rack) (Playtest)' ),
  'Frax Drone' => array( "name" => 'alldrone', 'no_rotate' => false, 'colorSource' => 'Frax TC (Playtest)' ),
  'Frax Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Frax TC (Playtest)' ),
  'Gorn Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'Gorn TCC' ),
  'Gorn Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Gorn TCC' ),
  'Hydran Fighter' => array( "name" => 'hydfighter', 'no_rotate' => false, 'colorSource' => '' ),
  'Hydran Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Hydran TLM' ),
  'ISC Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'ISC TCC' ),
  'ISC Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'ISC TCC' ),
  'Klingon Drone' => array( "name" => 'alldrone', 'no_rotate' => false, 'colorSource' => 'Klingon TD7C' ),
  'Klingon Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Klingon TD7C' ),
  'LDR ESG' => array( "name" => 'ESG', 'no_rotate' => true, 'colorSource' => 'LDR TCWL' ),
  'LDR Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'LDR TCWL' ),
  'Lyran ESG' => array( "name" => 'ESG', 'no_rotate' => true, 'colorSource' => 'Lyran TCC' ),
  'Lyran Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Lyran TCC' ),
  'Orion Drone' => array( "name" => 'alldrone', 'no_rotate' => false, 'colorSource' => 'Orion TBR' ),
  'Orion ESG' => array( "name" => 'ESG', 'no_rotate' => true, 'colorSource' => 'Orion TBR' ),
  'Orion Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'Orion TBR' ),
  'Orion Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Orion TBR' ),
  'TFE Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'Romulan TFH' ),
  'TKE Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'Romulan TKE' ),
  'TKR Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'Romulan TKR' ),
  'Romulan Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Romulan TKR' ),
  'Vudar Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Vudar TCA' ),
  'Wyn TAxBC Drone' => array( "name" => 'alldrone', 'no_rotate' => false, 'colorSource' => 'Wyn TAxBC' ),
  'Wyn GBS Drone' => array( "name" => 'alldrone', 'no_rotate' => false, 'colorSource' => 'Wyn GBS' ),
  'WYN ESG' => array( "name" => 'ESG', 'no_rotate' => true, 'colorSource' => '' ),
  'Wyn TAxBC Plasma' => array( "name" => 'allplasma', 'no_rotate' => false, 'colorSource' => 'Wyn TAxBC' ),
  'Wyn TAxBC Shuttle' => array( "name" => 'alladmin', 'no_rotate' => false, 'colorSource' => 'Wyn TAxBC' ),
# Misc
  'Andromedan DisDev' => array( "name" => 'DisDev Marker', 'no_rotate' => true, 'colorSource' => '' ),
  'Card' => array( "name" => 'Card', 'no_rotate' => true, 'colorSource' => '' ),
  'CamCard' => array( "name" => 'Camera Title', 'no_rotate' => true, 'colorSource' => '' ),
  'Front Shield' => array( "name" => 'shield.front', 'no_rotate' => false, 'colorSource' => '' ),
  'Left Shield' => array( "name" => 'shield.left', 'no_rotate' => false, 'colorSource' => '' ),
  'Rear Shield' => array( "name" => 'shield.rear', 'no_rotate' => false, 'colorSource' => '' ),
  'Right Shield' => array( "name" => 'shield.right', 'no_rotate' => false, 'colorSource' => '' ),
);

###
# CLI Configuration
###

$CLIoptions = "";
$CLIoptions .= "a::m::t::hxq";
$CLIlong = array("action::", "move::", "trails::", "help", "no_action", "quiet");

$CLI = getopt($CLIoptions, $CLIlong, $rest_index);
$CLIend = array_slice($argv, $rest_index);

# Adjust configuration from CLI
if (isset($CLI["a"])) $FRAMESPERACTION = (int)$CLI["a"];
if (isset($CLI["action"])) $FRAMESPERACTION = (int)$CLI["action"];
if (isset($CLI["m"])) $FRAMESFORMOVE = (int)$CLI["m"];
if (isset($CLI["move"])) $FRAMESFORMOVE = (int)$CLI["move"];
if (isset($CLI["t"])) $MOVEMENTTRAILS = (int)$CLI["t"];
if (isset($CLI["trails"])) $MOVEMENTTRAILS = (int)$CLI["trails"];
if (isset($CLI["x"]) || isset($CLI["no_action"])) $NOANIMATION = true;
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
  $skipActivity = true;

  for ($m = 0; $m < $FRAMESFORMOVE; $m++) {
    # Segment 1 — Movement
    $svg = svg_init();
    $svg .= draw_map_background();
    $svg .= show_clock($i);
    $svg .= process_movement($impulseData, $unitList, $i);
    $svg .= draw_all_units($unitList, $i);
    $file = "{$outDir}/{$FILEPREFIX}" . sprintf("%03d",$frameIteration++) . ".svg";
    write_svg($svg, $file);
  }

  if ($NOANIMATION) {
    // Detect if there is any more activity
    foreach ($impulseData as $sequence => $actions) {
      if ($sequence > LogFile::SEQUENCE_MOVEMENT_TAC && !empty($actions)) {
        $skipActivity = false; // there is an activity or weapons fire
        break;
      }
    }
  }

  // If skipping the action segment of impulses is enabled AND nothing happened, skip the rest of the impulse
  if ($NOANIMATION && $skipActivity) continue; // only Segment 1 is written

  for ($a = 0; $a < $FRAMESPERACTION; $a++) {
    # Segment 2 — Activity
    $svg = svg_init();
    $svg .= draw_map_background();
    $svg .= show_clock($i);
    $svg .= draw_all_units($unitList, $i);
    $svg .= process_activity($impulseData, $unitList, $i);
    $file = "{$outDir}/{$FILEPREFIX}" . sprintf("%03d",$frameIteration++) . ".svg";
    write_svg($svg, $file);
  }

  for ($a = 0; $a < $FRAMESPERACTION; $a++) {
    # Segment 3 — Weapons
    $svg = svg_init();
    $svg .= draw_map_background();
    $svg .= show_clock($i);
    $svg .= draw_all_units($unitList, $i);
    $svg .= process_weapons($impulseData, $unitList, $i);
    $file = "{$outDir}/{$FILEPREFIX}" . sprintf("%03d",$frameIteration++) . ".svg";
    write_svg($svg, $file);
  }

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
  $fontSize = HEX_LABEL_FONT_SIZE; // for text placement

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
  global $MOVEMENTTRAILS;
  $svg = "<g id='units'>\n";
  foreach ($unitList as $unit) {
    if (!isset($unit["location"])) continue;
    if ($unit["added"] > $impulse || $unit["removed"] < $impulse ) continue;
    list($x, $y) = locationPixels($unit["location"]);

    // Render trail from logfile data
    if ($MOVEMENTTRAILS > 0) {
      $trailSvg = renderUnitTrail($unit, $impulse);
      if (!empty($trailSvg)) $svg .= $trailSvg;
    }

    $svg .= draw_unit_marker($x, $y, $unit["facing"], $unit["type"]);
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
# Also extract stroke and fill colors of marker. Place in $MODEL_NAME
###
function draw_unit_marker($x, $y, $facing, $type) {
  global $ICONDIR, $MODEL_NAME;

  static $markerCache = [];

  $halfHeight = MARKER_HEIGHT / 2;
  $halfWidth = MARKER_WIDTH / 2;
  $markerScale = round(HEX_RADIUS / $halfHeight, 3);
  $useColorSource = false; // Determine whether we apply colorSource logic

  if (!isset($MODEL_NAME[$type]) || empty($MODEL_NAME[$type]["name"])) {
    echo "Could not find entry for '$type' in lookup or unit has no icon file.\n";
    return null;
  }

  $angle = facingToAngle($facing);
  $markerPrefix = "<g transform='translate(" . ($x-$halfWidth) . "," . ($y-$halfHeight) . ") rotate($angle,$halfWidth,$halfHeight)'>";
  $markerPrefix .= "<g transform='scale($markerScale)'>";
  $markerSuffix = "</g></g>\n";

  if (isset($markerCache[$type]))
    return $markerPrefix . $markerCache[$type] . $markerSuffix;

  // prepare for marker file
  $iconFile = $ICONDIR . "/" . $MODEL_NAME[$type]["name"] . ".svg";
  if (!is_readable($iconFile)) {
    echo "Could not read file '$iconFile' for $type.\n";
    return null;
  }

  // get the marker file
  $marker = file_get_contents($iconFile);
  if ($marker === false) {
    echo "Could not retrieve file '$iconFile' for '$type'.\n";
    return null;
  }

  // Munge the marker file:
  // Remove opening <svg ...> tag
  $marker = preg_replace('/<svg[^>]*>/i', '', $marker);
  // Remove closing </svg>
  $marker = preg_replace('/<\/svg>/i', '', $marker);
  $marker = trim($marker);

  $colorSourceType = $MODEL_NAME[$type]['colorSource'];
  if ($colorSourceType !== '') {
    // Source unit must exist and have color data (or be loadable)
    if (!isset($colorSourceType)) {
      echo "Source of color ($colorSourceType) does not exist for '$type'.\n";
      return null;
    }

    // If source unit has no stored colors yet, trigger loading
    if (!isset($MODEL_NAME[$colorSourceType]['fill']) ||
      !isset($MODEL_NAME[$colorSourceType]['stroke'])) {

      // This call reuses your existing mechanism to load the marker,
      // extract colors, and populate $MODEL_NAME for the source.
      draw_unit_marker($x, $y, $facing, $colorSourceType);
    }

    // After this, source MUST have fill/stroke to use
    if (!isset($MODEL_NAME[$colorSourceType]['fill']) ||
        !isset($MODEL_NAME[$colorSourceType]['stroke'])) {
      echo "Colors do not exist for '$colorSourceType'.\n";
      return null;
    }

    // Record the new colors of the marker
    $MODEL_NAME[$type]['fill'] = $MODEL_NAME[$colorSourceType]['fill'];
    $MODEL_NAME[$type]['stroke'] = $MODEL_NAME[$colorSourceType]['stroke'];

    // replace the stroke and fill colors of the marlker
    $marker = preg_replace('/(style="[^"]*?\bfill:\s*)([^;"]+)(;)/', '$1' . $MODEL_NAME[$type]['fill'] . '$3', $marker,1);
    $marker = preg_replace('/(style="[^"]*?\bstroke:\s*)([^;"]+)(;)/', '$1' . $MODEL_NAME[$type]['stroke'] . '$3', $marker, 1);
  } else {
    // Extract the fill and stroke colors of the marker
    // Used for marker trails and other effects
    if (preg_match('/fill:\s*([^;]+);/i', $marker, $m))
      $MODEL_NAME[$type]['fill'] = trim($m[1]);
    if (preg_match('/stroke:\s*([^;]+);/i', $marker, $m))
      $MODEL_NAME[$type]['stroke'] = trim($m[1]);
  }

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
# Show what turn / impulse that the current frame is showing
###
function show_clock($impulse) {
  $time = LogUnit::convertFromImp($impulse);
  $x = MAP_MARGIN_HORIZ / 2;
  $y = "1em";
  $svg = "<text x='$x' y='$y' class='clock'>Turn $time</text>\n";
  return $svg;
}

###
# Process movement segment: update locations and facings
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
      }
      if (isset($action["facing"]))
        $unitList[$name]["facing"] = $action["facing"];
      if (isset($action['turn']) && $action['turn'] == 'HET') {
        list($x, $y) = locationPixels($action["location"]);
        $svg .= drawBalloon($x, $y, "HET");
      }
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
        list($x, $y) = locationPixels($action["owner location"]);
        $svg .= draw_effect_line($action["owner location"], $action["target"], COLOR_TRACTOR, $impulse);
        $svg .= drawBalloon($x, $y, "Tractor");
      }
      if ($sequence == LogFile::SEQUENCE_CLOAKING_DEVICE) {
        list($x, $y) = locationPixels($action["owner location"]);
        $svg .= "<circle cx='$x' cy='$y' r='20' fill='none' stroke='gray' stroke-dasharray='5,3' />\n";
        $svg .= drawBalloon($x, $y, "Cloak");
      }
      // launched shuttles or seeking weapons
      $svg .= process_unit_adds($unitList, $sequence, $action);
    }
  }
  $svg .= "</g>\n";
  return $svg;
}

###
# Process weapons fire segment
###
function process_weapons($impulseData, &$unitList, $impulse) {
  global $log;
  $svg = "<g id='weapons'>\n";

  foreach ($impulseData as $sequence => $actions) {
    if ($sequence < LogFile::SEQUENCE_DIS_DEV_DECLARATION ||
        $sequence >= LogFile::SEQUENCE_IMPULSE_END) continue;
    foreach ($actions as $action) {
      // cast web
      $svg .= process_unit_adds($unitList, $sequence, $action);
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
# Set up launched units for being drawn
###
function process_unit_adds(&$units, $segment, $impulseAction) {
  $svg = "";
  if ($segment == LogFile::SEQUENCE_LAUNCH_PLASMA ||
      $segment == LogFile::SEQUENCE_LAUNCH_DRONES ||
      $segment == LogFile::SEQUENCE_LAUNCH_SHUTTLES ||
      $segment == LogFile::SEQUENCE_CAST_WEB
  ) {
    list($x, $y) = locationPixels($impulseAction["location"]);
    $svg .= draw_unit_marker($x, $y, $impulseAction["facing"], $impulseAction["type"]);

    $name = $impulseAction["name"] ?? $impulseAction["owner"];
    if (!isset($units[$name])) {
      echo "Tried to draw '$name' in process_unit_adds(). Could not find in unit list.\n";
      return "";
    }
    $units[$name]["location"] = $impulseAction["location"];
    $units[$name]["facing"] = $impulseAction["facing"];
    list($x, $y) = locationPixels($impulseAction["location"]);
    if (count($units[$name]["trail"]) > 16)
      array_shift($units[$name]["trail"]);
    switch ($segment) {
    case LogFile::SEQUENCE_LAUNCH_PLASMA:
      $svg .= drawBalloon($x, $y, "Launch Plasma");
      break;
    case LogFile::SEQUENCE_LAUNCH_DRONES:
      $svg .= drawBalloon($x, $y, "Launch Drone");
      break;
    case LogFile::SEQUENCE_LAUNCH_SHUTTLES:
      $svg .= drawBalloon($x, $y, "Launch Shuttle");
      break;
    default:
      break;
    }
  }
  return $svg;
}

###
# Draw a text-balloon to give a short message of activity (launch, HET, etc)
###
function drawBalloon(float $x, float $y, string $message): string
{
  $fontSize = CARD_LABEL_FONT_SIZE;
  $padding = 0.5;
  $lineHeight = 2.0;

  // Derived dimensions
  $textLength = mb_strlen($message);
  $textWidth = $textLength * 0.6 * $fontSize; // approx width per character
  $rectWidth = $textWidth + (2 * $padding * $fontSize);
  $rectHeight = $lineHeight * $fontSize;

  // Balloon geometry
  $cornerRadius = 0.5 * $fontSize;
  $tipHeight = 0.75 * $fontSize;
  $tipWidth = 1.0 * $fontSize;

  // Positioning: balloon above the hex center
  $rectX = $x - ($rectWidth / 2);
  $rectY = $y - (MARKER_HEIGHT / 3) - $rectHeight - $tipHeight;

  // Triangle points
  $tipX1 = $x - ($tipWidth / 2);
  $tipX2 = $x + ($tipWidth / 2);
  $tipY1 = $rectY + $rectHeight;
  $tipY2 = $tipY1 + $tipHeight;

  $svg = "<g class='activity-balloon'>\n";
  $svg .= "<rect x='$rectX' y='$rectY' rx='$cornerRadius' ry='$cornerRadius' width='$rectWidth' height='$rectHeight' fill='#ffffff' stroke='#000000' stroke-width='1' />\n";
  $svg .= "<polygon points='$tipX1,$tipY1 $tipX2,$tipY1 $x,$tipY2' fill='#ffffff' stroke='#000000' stroke-width='1' />\n";
  $svg .= "<text x='$x' y='". ($rectY + ($rectHeight / 2)) . "' font-size='$fontSize' text-anchor='middle' dominant-baseline='middle' fill='#000000'>$message</text>\n";
  $svg .= "</g>\n";

  return $svg;
}

###
# Build trail segments for a unit for the current frame using LogUnit trail data.
# This combines extracting movement steps and building consecutive segments.
###
function buildTrailSegmentsForFrame($unitName, $impulse, $amt) {
  global $log;
  $timeStr = LogUnit::convertFromImp($impulse);
  $raw = $log->get_unit_location_trail($unitName, $timeStr, $amt);
  if ($raw === null || count($raw) < 2) return array();

  // raw[0] == latest. We want chronological order oldest -> newest
  $steps = array_reverse($raw);

  $segments = array();
  for ($i = 1; $i < count($steps); $i++) {
    $prev = $steps[$i-1];
    $cur  = $steps[$i];
    if ($prev[0] == "" || $cur[0] == "") continue; // skip empty (offboard) steps

    // Each entry: [ location, facing, moveType ]
    $segments[] = array(
      'from_hex'    => $prev[0],
      'to_hex'      => $cur[0],
      'from_facing' => $prev[1],
      'to_facing'   => $cur[1],
      'type'        => ($cur[2] === "" ? "move" : $cur[2])
    );
  }
  return $segments;
}

###
# Convert a sequence of segments into a single continuous SVG path (polygonal ribbon).
# Uses locationPixels() to convert hex -> pixel center.
###
function convertSegmentsToSvgPath($segments) {
  if (empty($segments)) return "";

  // Build list of center points in order
  $points = array();
  // first point: from_hex of first segment
  $first = $segments[0]['from_hex'];
  $p = locationPixels($first);
  $points[] = array('x'=>$p[0],'y'=>$p[1]);
  foreach ($segments as $seg) {
    $p = locationPixels($seg['to_hex']);
    $points[] = array('x'=>$p[0],'y'=>$p[1]);
  }

  $n = count($points);
  if ($n < 2) return "";

  // Determine per-segment half-widths
  $NORMAL_W = defined('TRAIL_NORMAL_W') ? TRAIL_NORMAL_W : 6;
  $SIDESLIP_W = defined('TRAIL_SIDESLIP_W') ? TRAIL_SIDESLIP_W : 12;

  $halfWidths = array();
  for ($i = 0; $i < $n-1; $i++) {
    $type = $segments[$i]['type'];
    $w = ($type === 'side-slip') ? $SIDESLIP_W : $NORMAL_W;
    $halfWidths[$i] = $w / 2.0;
  }

  // For each point compute offset vectors using adjacent segment directions.
  $left_pts = array();
  $right_pts = array();
  for ($i = 0; $i < $n; $i++) {
    if ($i == 0) {
      $p1 = $points[0];
      $p2 = $points[1];
      $dx = $p2['x'] - $p1['x'];
      $dy = $p2['y'] - $p1['y'];
      $len = sqrt($dx*$dx + $dy*$dy) ?: 1;
      $nx = -$dy / $len;
      $ny = $dx / $len;
      $w = $halfWidths[0];
    } elseif ($i == $n-1) {
      $p1 = $points[$n-2];
      $p2 = $points[$n-1];
      $dx = $p2['x'] - $p1['x'];
      $dy = $p2['y'] - $p1['y'];
      $len = sqrt($dx*$dx + $dy*$dy) ?: 1;
      $nx = -$dy / $len;
      $ny = $dx / $len;
      $w = $halfWidths[$n-2];
    } else {
      // junction: use min of adjacent half widths to avoid spikes
      $p_prev = $points[$i-1];
      $p_next = $points[$i+1];
      $dx = $p_next['x'] - $p_prev['x'];
      $dy = $p_next['y'] - $p_prev['y'];
      $len = sqrt($dx*$dx + $dy*$dy) ?: 1;
      $nx = -$dy / $len;
      $ny = $dx / $len;
      $w = min($halfWidths[$i-1], $halfWidths[$i]);
    }
    $left_pts[]  = array($points[$i]['x'] + $nx * $w, $points[$i]['y'] + $ny * $w);
    $right_pts[] = array($points[$i]['x'] - $nx * $w, $points[$i]['y'] - $ny * $w);
  }

  // Build path: left points in order, then right points in reverse, close.
  $d = "";
  $d .= "M " . round($left_pts[0][0],2) . " " . round($left_pts[0][1],2) . " ";
  for ($i = 1; $i < count($left_pts); $i++) {
    $d .= "L " . round($left_pts[$i][0],2) . " " . round($left_pts[$i][1],2) . " ";
  }
  for ($i = count($right_pts)-1; $i >= 0; $i--) {
    $d .= "L " . round($right_pts[$i][0],2) . " " . round($right_pts[$i][1],2) . " ";
  }
  $d .= "Z";
  return $d;
}

###
# Render the unit's trail as a single SVG <path> element.
# Color is chosen via fill color of the marker
###
function renderUnitTrail($unit, $impulse) {
  global $MOVEMENTTRAILS, $MODEL_NAME, $log;

  if ($MOVEMENTTRAILS <= 0) return "";
  if (!isset($unit['name']) || !isset($unit['type'])) return "";
  if (!isset($MODEL_NAME[$unit['type']]['fill'])) return "";

  $color = $MODEL_NAME[$unit['type']]['fill'];
  $prefix = "<!-- Trail for {$unit['type']} -->";

  $segments = buildTrailSegmentsForFrame($unit['name'], $impulse, $MOVEMENTTRAILS);
  if (empty($segments)) return "";

  $pathD = convertSegmentsToSvgPath($segments);
  if (empty($pathD)) return "";

  return $prefix . "<path d='${pathD}' fill='${color}' stroke='${color}' opacity='" . TRAIL_OPACITY . "' />\\n";
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
  global $argv, $FRAMESPERACTION, $FRAMESFORMOVE, $MOVEMENTTRAILS;
  echo "\n";
  if ($message !== null && $message != "") echo $message . "\n\n";
  echo "Usage:\n  {$argv[0]} [OPTIONS..] /path/to/log\n";
  echo "Options:\n";
  echo "  -a, --action <n>    Frames per action segment (default $FRAMESPERACTION)\n";
  echo "  -m, --move <n>      Frames per movement segment (default $FRAMESFORMOVE)\n";
  echo "  -t, --trails <n>    Show a movement trail for <n> impulses. 0 for none. (default $MOVEMENTTRAILS)\n";
  echo "  -x, --no_action     Skip impulses with no activity\n";
  echo "  -q, --quiet         Suppress console output\n";
  echo "  -h, --help          Show this help message\n";
  exit(1);
}
?>

