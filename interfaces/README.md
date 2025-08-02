This takes the output from the SFBOL-Logs scripts and creates a [Blender](https://www.blender.org/) [python script](https://docs.blender.org/api/current/index.html).
This script is then opened in Blender and run. It will duplicate the units used, from primitives given in the file. Then it will move those units as described by the SFBOL log file. It will "launch" and remove units, show weapons fire and damage taken, and display some other activities as described in the log file.

# Usage
The basic program flow is thus:
- Find your game logfile from [Star Fleet Battles Online ](https://sfbonline.com/index.jsp)
- Run it through this script
- Take the resulting Python script into Blender and execute it there
- Modify some aspects of the scene (such as camera locations, fix visual errors in the logfile, etc..)
- Render into a series of stills
- Create a video

## The Game Logfile
The SFB Online log file is usally found in the 'log' directory off of the program root directory. These files are text files with the '.log' extension. They are the contents of the chat window of a SFBonline game. The below serves as an example for a Linux system.
```
~$ cd .sfu_online_client/log/
~/.sfu_online_client/log$ ls
AWJohnson.log                        SFBCadet_Game5.log
 Eric_the_Silent.log                 SFB_Game1.log
 MaxSpeed.log                        SFB_LoadTestGame1.log
 Neonpico.log                        SFB_Tom_v_Matt.log
 SFB_500-Tourney-Feds-vs-Selts.log   SFBTourn_Matt_v_Tom.log
 SFB_Bullpen.log                     Tokimonsta.log
~/.sfu_online_client/log$ cat SFBTourn_Matt_v_Tom.log 
---There is no topic for #SFBTourn_Matt_v_Tom.
---Eric_the_Silent joined the channel.
Rocky (Type:Archeo-Tholian TCC) has been added at 1701, direction D, speed 0
Eric_the_Silent has selected Klingon TD7C
Neonpico has selected Archeo-Tholian TCC
BumpyHead (Type:Klingon TD7C) has been added at 2530, direction A, speed 0
---Neonpico has given Eric_the_Silent operator status.
Neonpico has started Energy Allocation
Eric_the_Silent has started Energy Allocation
Neonpico has finished Energy Allocation
...
```

## Usage of this script
Having identified the script to convert, execute this script to convert the logfile into a Blender script.
```
$ ./blender_interface.php -h -a24

Extract an SFBOL log file into a Blender script

Called by:
  ./blender_interface.php [OPTIONS..] /path/to/log
  Creates/overwrites a file appended with '.py'

OPTIONS:
-a, --action
   Change the frames per action-segment to this. Currently 24 frames.
-h, --help
   Give this help dialog.
-m, --move
   Change the frames per move-segment to this. Currently 12 frames.
-q, --quiet
   On success, do not print anything to the terminal.
-x, --no_action
   Remove the wait time for any impulses where there is no action to animate.

$ ./blender_interface.php -a24 ../test_files/test-Gor_TKR.log
```

It will generate a python script very similar to the following:

```
import bpy
import mathutils
from mathutils import *; from math import *

#####
# Impulses are 60 frames long.
# - Movement takes 12 frames.
# - Early-impulse actions are animated for 24 frames.
# - Weapons fire is animated for 24 frames.
#####

for obj in bpy.data.objects:
   obj.select_set(False)
bpy.context.view_layer.objects.active = bpy.data.objects['Romulan TKR']
obj = bpy.data.objects['Romulan TKR'].copy()
bpy.context.collection.objects.link(obj)
obj.name = 'Ballerina'
bpy.context.view_layer.objects.active = bpy.data.objects['Gorn TCA']
obj = bpy.data.objects['Gorn TCA'].copy()
bpy.context.collection.objects.link(obj)
obj.name = 'liard'
bpy.context.view_layer.objects.active = bpy.data.objects['Plasma']
obj = bpy.data.objects['Plasma'].copy()
bpy.context.collection.objects.link(obj)
obj.name = 'ECPA(30).1.17'
...

# Start of impulse 0.32, animation frame 0

bpy.data.objects['Ballerina'].select_set(True)
bpy.context.view_layer.objects.active = bpy.data.objects['Ballerina']
bpy.data.objects['Ballerina'].location = (14.4, 0, 0.0)
bpy.data.objects['Ballerina'].keyframe_insert(data_path="location", frame=0)
bpy.data.objects['Ballerina'].rotation_euler = (0.0, 0.0, radians(-180))
bpy.data.objects['Ballerina'].keyframe_insert(data_path="rotation_euler", frame=0, index=2)
bpy.data.objects['liard'].select_set(True)
bpy.context.view_layer.objects.active = bpy.data.objects['liard']
bpy.data.objects['liard'].location = (21.6, -29, 0.0)
bpy.data.objects['liard'].keyframe_insert(data_path="location", frame=0)
bpy.data.objects['liard'].rotation_euler = (0.0, 0.0, radians(0))
bpy.data.objects['liard'].keyframe_insert(data_path="rotation_euler", frame=0, index=2)

# Start of impulse 1.1, animation frame 59

...
```

## Run inside of Blender

The python script then needs to be imported into Blender and run. It will duplicate the models used in during the game, generate the needed keyframes, and basically set up the scene for rendering (minus any lighting or camera work.)

To import a python script, select from the 'Editor Type' the 'Text Editor' screen. Then use the 'Open' button to select the script. The contents of your script should show in the text editor window. Then use the play button (known as 'Run Script') to generate the scene.
![Import script in Blender](interfaces/README1.png)

At this point the user needs to modify the scene for camera, lighting, and to fix any errors in the chat log that got transferred into the scene.

## Render the scene

Within blender, render each frame of the scene. This will create a series of still images.

## Create the video.

Once all of the images have been rendered, they can be collected into a video. Your software of choice may vary from mine. My preference is to use [FFMPEG](https://en.wikipedia.org/wiki/FFmpeg):
```
$ ffmpeg -f image2 -framerate 24 -pattern_type glob -i $RENDER_DIR/'*.png' -an output.mp4
```
