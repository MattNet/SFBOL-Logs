This takes the output from the SFBOL-Logs scripts and creates a [Blender](https://www.blender.org/) [python script](https://docs.blender.org/api/current/index.html).
This script is then opened in Blender and run. It will duplicate the units used, from primitives given in the file. Then it will move those units as described by the SFBOL log file. It will "launch" and remove units as described in the log file. It will (in later versions) also perform weapons fire and display damage taken.

# Usage
```
$ ./blender_interface.php ../test_files/test-Gor_TKR.log 

###
Debug Info:
###
Animation length: 2275 frames
Unit List:
Array
(
...
)
```

```
import bpy
import mathutils
from mathutils import *; from math import *

#####
# Impulses are 36 frames long.
# - Movement takes 12 frames.
# - Early-impulse actions are animated for 12 frames.
# - Weapons fire is animated for 12 frames.
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
bpy.context.view_layer.objects.active = bpy.data.objects['Plasma']
obj = bpy.data.objects['Plasma'].copy()
bpy.context.collection.objects.link(obj)
obj.name = 'Ballerina-PA(60).1.27'
bpy.context.view_layer.objects.active = bpy.data.objects['Plasma']
obj = bpy.data.objects['Plasma'].copy()
bpy.context.collection.objects.link(obj)
obj.name = 'ECPB(30).2.28'
bpy.context.view_layer.objects.active = bpy.data.objects['Plasma']
obj = bpy.data.objects['Plasma'].copy()
bpy.context.collection.objects.link(obj)
obj.name = 'Ballerina-PB(60).2.31'
py.context.scene.frame_end = 2275

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

# Start of impulse 1.1, animation frame 35

...
```
