#!/bin/bash

# OpenLinkMap Copyright (C) 2010 Alexander Matheisen
# This program comes with ABSOLUTELY NO WARRANTY.
# This is free software, and you are welcome to redistribute it under certain conditions.
# See openlinkmap.org for details.


# set up database
su postgres
createuser olm


createdb -E UTF8 -O olm olm
createlang plpgsql olm
psql -d olm -f /usr/share/pgsql/contrib/postgis-64.sql
psql -d olm -f /usr/share/pgsql/contrib/postgis-1.5/spatial_ref_sys.sql
psql -d olm -f /usr/share/pgsql/contrib/hstore.sql
psql -d olm -f /usr/share/pgsql/contrib/_int.sql

echo "ALTER TABLE geometry_columns OWNER TO olm; ALTER TABLE spatial_ref_sys OWNER TO olm;"  | psql -d olm
echo "ALTER TABLE geography_columns OWNER TO olm;"  | psql -d olm


createdb -E UTF8 -O olm nextobjects
createlang plpgsql nextobjects

psql -d nextobjects -f /usr/share/pgsql/contrib/postgis-64.sql
psql -d nextobjects -f /usr/share/pgsql/contrib/postgis-1.5/spatial_ref_sys.sql
psql -d nextobjects -f /usr/share/pgsql/contrib/hstore.sql
psql -d nextobjects -f /usr/share/pgsql/contrib/_int.sql

echo "ALTER TABLE geometry_columns OWNER TO olm; ALTER TABLE spatial_ref_sys OWNER TO olm;"  | psql -d nextobjects
echo "ALTER TABLE geography_columns OWNER TO olm;"  | psql -d nextobjects


echo "CREATE TABLE nodes (id bigint, tags hstore);" | psql -d olm
echo "SELECT AddGeometryColumn('nodes', 'geom', 4326, 'POINT', 2);" | psql -d olm
echo "CREATE INDEX geom_index_nodes ON nodes USING GIST(geom);" | psql -d olm
echo "CLUSTER nodes USING geom_index_nodes;" | psql -d olm
echo "CREATE INDEX id_index_nodes ON nodes (id);" | psql -d olm
echo "CLUSTER nodes USING id_index_nodes;" | psql -d olm
echo "CREATE INDEX tag_index_nodes ON nodes USING GIST (tags);" | psql -d olm
echo "CLUSTER nodes USING tag_index_nodes;" | psql -d olm

echo "CREATE TABLE ways (id bigint, tags hstore);" | psql -d olm
echo "SELECT AddGeometryColumn('ways', 'geom', 4326, 'POINT', 2);" | psql -d olm
echo "CREATE INDEX geom_index_ways ON ways USING GIST(geom);" | psql -d olm
echo "CLUSTER ways USING geom_index_ways;" | psql -d olm
echo "CREATE INDEX id_index_ways ON ways (id);" | psql -d olm
echo "CLUSTER ways USING id_index_ways;" | psql -d olm
echo "CREATE INDEX tag_index_ways ON ways USING GIST (tags);" | psql -d olm
echo "CLUSTER ways USING tag_index_ways;" | psql -d olm

echo "GRANT all ON nodes TO olm;" | psql -d olm
echo "GRANT all ON ways TO olm;" | psql -d olm

echo "GRANT truncate ON nodes TO olm;" | psql -d olm
echo "GRANT truncate ON ways TO olm;" | psql -d olm

echo "ALTER TABLE nodes OWNER TO olm;" | psql -d olm
echo "ALTER TABLE ways OWNER TO olm;" | psql -d olm


echo "CREATE TABLE nextobjects (name varchar(255), type varchar(255));" | psql -d nextobjects
echo "SELECT AddGeometryColumn('nextobjects', 'geom', 4326, 'POINT', 2);" | psql -d nextobjects
echo "CREATE INDEX geom_index_nextobjects ON nextobjects USING GIST(geom);" | psql -d nextobjects
echo "CLUSTER nextobjects USING geom_index_nextobjects;" | psql -d nextobjects
echo "CREATE INDEX type_index_nextobjects ON nextobjects (type);" | psql -d nextobjects
echo "CLUSTER nextobjects USING type_index_nextobjects;" | psql -d nextobjects

echo "GRANT all ON nextobjects TO olm;" | psql -d nextobjects
echo "GRANT truncate ON nextobjects TO olm;" | psql -d nextobjects
echo "ALTER TABLE nextobjects OWNER TO olm;" | psql -d nextobjects

echo "CREATE ROLE apache;" | psql -d olm

echo "GRANT SELECT ON nextobjects TO apache;" | psql -d nextobjects
echo "GRANT SELECT ON nodes TO apache;" | psql -d olm
echo "GRANT SELECT ON ways TO apache;" | psql -d olm