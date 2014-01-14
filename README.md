shpParser
=========

PHP Parser for ESRI Shape Files.
The structure of the Shape Files is defined in the official paper distributed by ESRI which can be found here:
www.esri.com/library/whitepapers/pdfs/shapefile.pdf

The Parser is set up to store the data of the shape file in a MySQL database.

This script will support all common shape types (available shape types are marked):

- [x] Null Shape
- [x] Point
- [x] PointZ
- [x] PointM
- [x] MultiPoint
- [x] MultiPointZ
- [x] MultiPointM
- [x] PolyLine
- [] PolyLineZ
- [] PolyLineM
- [] Polygon
- [] PolygonZ
- [] PolygonM
- [] MultiPatch

TODOs
-----

- [] Add the missing shape types
- [] Add methods to work with the data in the database
- [] Add export functions for different formats (e.g. .kml, .xml, ...)


Copyright
---------

Copyright &copy; 2014 Sebastian Paulmichl. See LICENSE.md for further details.
