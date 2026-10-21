import os
from drawio import DrawioFile
OUTDIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "docs", "diagrams") + os.sep
WRAP = "whiteSpace=wrap;"
BOX = "rounded=0;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;"
RBOX = "rounded=1;arcSize=12;" + WRAP + "fillColor=#dae8fc;strokeColor=#6c8ebf;"
UTIL = "rounded=0;" + WRAP + "fillColor=#e1d5e7;strokeColor=#9673a6;"
NOTE = "shape=note;size=14;" + WRAP + "align=left;verticalAlign=top;spacingLeft=6;spacingTop=4;fillColor=#fff2cc;strokeColor=#d6b656;"
TEXT = "text;strokeColor=none;fillColor=none;" + WRAP + "align=left;verticalAlign=top;"
ORTHO = "edgeStyle=orthogonalEdgeStyle;orthogonalLoop=1;jettySize=auto;endArrow=block;endFill=1;"
ORTHO_OPEN = "edgeStyle=orthogonalEdgeStyle;orthogonalLoop=1;jettySize=auto;endArrow=open;endFill=0;"
DASH = "edgeStyle=orthogonalEdgeStyle;dashed=1;endArrow=open;endFill=0;"
LINE = "edgeStyle=orthogonalEdgeStyle;endArrow=none;"
